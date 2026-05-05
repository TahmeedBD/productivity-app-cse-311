<?php
declare(strict_types=1);

require_once __DIR__ . '/service.php';

function update_time_entry(
    \PDO $pdo,
    string $userId,
    int $entryId,
    array $payload,
): array {
    $entry = find_time_entry_for_user($pdo, $entryId, $userId);

    if ($entry === null) {
        throw new \InvalidArgumentException('Time entry not found.');
    }

    $classification = validate_time_entry_classification(
        $pdo,
        $userId,
        array_key_exists('activity_id', $payload)
            ? ($payload['activity_id'] === null
                ? null
                : (int) $payload['activity_id'])
            : ($entry['activity_id'] === null
                ? null
                : (int) $entry['activity_id']),
        array_key_exists('activity_subtype_id', $payload)
            ? ($payload['activity_subtype_id'] === null
                ? null
                : (int) $payload['activity_subtype_id'])
            : ($entry['activity_subtype_id'] === null
                ? null
                : (int) $entry['activity_subtype_id']),
        true,
    );

    $startTime = trim(
        (string) ($payload['start'] ??
            extract_time_component((string) $entry['start'])),
    );
    $requestedEnd = array_key_exists('end', $payload)
        ? $payload['end']
        : extract_time_component((string) ($entry['end'] ?? ''));
    $endTime = $requestedEnd === null ? null : trim((string) $requestedEnd);
    $notes = array_key_exists('notes', $payload)
        ? (string) $payload['notes']
        : (string) ($entry['notes'] ?? '');
    $date = (string) $entry['date'];
    $wakeTime = (string) $entry['wake_time'];
    $sleepTime = (string) $entry['sleep_time'];
    $isRunning = $entry['end'] === null;

    if ($startTime === '') {
        throw new \InvalidArgumentException('start is required.');
    }

    if ($endTime === '') {
        $endTime = null;
    }

    if (!$isRunning && $endTime === null) {
        throw new \InvalidArgumentException(
            'Completed entries must include an end time.',
        );
    }

    if ($endTime === null) {
        if (
            !is_entry_start_within_awake_window(
                $startTime,
                $wakeTime,
                $sleepTime,
            )
        ) {
            throw new \InvalidArgumentException(
                'Entry start must be inside the awake window.',
            );
        }
    } elseif (
        !is_entry_within_awake_window(
            $startTime,
            $endTime,
            $wakeTime,
            $sleepTime,
        )
    ) {
        throw new \InvalidArgumentException(
            'Entry must be fully within the awake window.',
        );
    }

    $entries = list_time_entries_for_daily_log_in_order(
        $pdo,
        (int) $entry['daily_log_id'],
    );
    $entryIndex = null;

    foreach ($entries as $index => $listedEntry) {
        if ((int) $listedEntry['id'] === $entryId) {
            $entryIndex = $index;
            break;
        }
    }

    if ($entryIndex === null) {
        throw new \RuntimeException('Failed to locate time entry ordering.');
    }

    $previousEntry = $entryIndex > 0 ? $entries[$entryIndex - 1] : null;
    $nextEntry =
        $entryIndex < count($entries) - 1 ? $entries[$entryIndex + 1] : null;

    $startSeconds = time_to_seconds($startTime);
    $endSeconds = $endTime === null ? null : time_to_seconds($endTime);

    if ($startSeconds === null || ($endTime !== null && $endSeconds === null)) {
        throw new \InvalidArgumentException('Invalid time value provided.');
    }

    if ($previousEntry !== null) {
        $previousStartTime = extract_time_component(
            (string) $previousEntry['start'],
        );
        $previousEndTime = extract_time_component(
            (string) ($previousEntry['end'] ?? ''),
        );
        $previousStartSeconds =
            $previousStartTime === null
                ? null
                : time_to_seconds($previousStartTime);
        $previousEndSeconds =
            $previousEndTime === null
                ? null
                : time_to_seconds($previousEndTime);

        if (
            $previousStartSeconds === null ||
            $previousEndSeconds === null ||
            $startSeconds <= $previousStartSeconds
        ) {
            throw new \InvalidArgumentException(
                'Entry start overlaps too far into the previous entry.',
            );
        }
    }

    if ($nextEntry !== null && $endTime !== null) {
        $nextStartTime = extract_time_component((string) $nextEntry['start']);
        $nextEndTime = extract_time_component(
            (string) ($nextEntry['end'] ?? ''),
        );
        $nextStartSeconds =
            $nextStartTime === null ? null : time_to_seconds($nextStartTime);
        $nextEndSeconds =
            $nextEndTime === null ? null : time_to_seconds($nextEndTime);

        if ($nextStartSeconds === null) {
            throw new \InvalidArgumentException(
                'Next entry has an invalid start time.',
            );
        }

        if (
            ($nextEndTime !== null && $nextEndSeconds === null) ||
            ($nextEndSeconds !== null && $endSeconds >= $nextEndSeconds)
        ) {
            throw new \InvalidArgumentException(
                'Entry end overlaps too far into the next entry.',
            );
        }

        if (
            $nextEndTime === null &&
            !is_entry_start_within_awake_window($endTime, $wakeTime, $sleepTime)
        ) {
            throw new \InvalidArgumentException(
                'Entry end overlaps too far into the next entry.',
            );
        }
    }

    if ($nextEntry !== null && $endTime === null) {
        throw new \InvalidArgumentException(
            'Only the latest entry can remain running.',
        );
    }

    $pdo->beginTransaction();

    try {
        if ($previousEntry !== null) {
            $previousEndTime = extract_time_component(
                (string) $previousEntry['end'],
            );

            if (
                $previousEndTime !== null &&
                time_to_seconds($previousEndTime) !== null &&
                $startSeconds < time_to_seconds($previousEndTime)
            ) {
                update_time_entry_boundary(
                    $pdo,
                    (int) $previousEntry['id'],
                    'end',
                    combine_date_and_time($date, $startTime),
                );
            }
        }

        if ($nextEntry !== null && $endTime !== null) {
            $nextStartTime = extract_time_component(
                (string) $nextEntry['start'],
            );

            if (
                $nextStartTime !== null &&
                time_to_seconds($nextStartTime) !== null &&
                $endSeconds > time_to_seconds($nextStartTime)
            ) {
                update_time_entry_boundary(
                    $pdo,
                    (int) $nextEntry['id'],
                    'start',
                    combine_date_and_time($date, $endTime),
                );
            }
        }

        update_time_entry_record(
            $pdo,
            $entryId,
            $classification['activity_id'],
            $classification['activity_subtype_id'],
            combine_date_and_time($date, $startTime),
            $endTime === null ? null : combine_date_and_time($date, $endTime),
            $endTime === null ? 'running' : 'completed',
            $notes,
        );

        $pdo->commit();
    } catch (\Throwable $throwable) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $throwable;
    }

    $updatedEntry = find_time_entry_by_id($pdo, $entryId);

    if ($updatedEntry === null) {
        throw new \RuntimeException('Failed to reload the updated entry.');
    }

    return $updatedEntry;
}

function build_update_time_entry_response(
    \PDO $pdo,
    array $currentUser,
    array $payload,
): array {
    $userId = (string) ($currentUser['id'] ?? '');
    $entryId = filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);
    $activityId = filter_var(
        $payload['activity_id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]],
    );
    $activitySubtypeId = filter_var(
        $payload['activity_subtype_id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]],
    );

    if ($userId === '') {
        throw new \InvalidArgumentException('Current user id is required.');
    }

    if ($entryId === false) {
        return [
            'status' => 422,
            'body' => ['ok' => false, 'error' => 'id is required.'],
        ];
    }

    if (array_key_exists('activity_id', $payload) && $activityId === false) {
        return [
            'status' => 422,
            'body' => ['ok' => false, 'error' => 'activity_id is invalid.'],
        ];
    }

    if (
        array_key_exists('activity_subtype_id', $payload) &&
        $payload['activity_subtype_id'] !== null &&
        $activitySubtypeId === false
    ) {
        return [
            'status' => 422,
            'body' => [
                'ok' => false,
                'error' => 'activity_subtype_id is invalid.',
            ],
        ];
    }

    $updatePayload = [];

    if (array_key_exists('activity_id', $payload)) {
        $updatePayload['activity_id'] =
            $activityId === false ? null : $activityId;
    }

    if (array_key_exists('activity_subtype_id', $payload)) {
        $updatePayload['activity_subtype_id'] =
            $activitySubtypeId === false ? null : $activitySubtypeId;
    }

    if (array_key_exists('start', $payload)) {
        $updatePayload['start'] = trim((string) $payload['start']);
    }

    if (array_key_exists('end', $payload)) {
        $updatePayload['end'] =
            $payload['end'] === null ? null : trim((string) $payload['end']);
    }

    if (array_key_exists('notes', $payload)) {
        $updatePayload['notes'] = (string) $payload['notes'];
    }

    try {
        $entry = update_time_entry($pdo, $userId, $entryId, $updatePayload);
    } catch (\InvalidArgumentException $exception) {
        return [
            'status' => 422,
            'body' => ['ok' => false, 'error' => $exception->getMessage()],
        ];
    }

    return [
        'status' => 200,
        'body' => ['ok' => true, 'entry' => $entry],
    ];
}
