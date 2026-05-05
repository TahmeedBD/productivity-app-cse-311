<?php
declare(strict_types=1);

require_once __DIR__ . '/service.php';

function end_time_entry(
    \PDO $pdo,
    string $userId,
    string $date,
    string $endTime,
    array $currentEntryPayload = [],
): array {
    $dailyLog = get_or_create_daily_log($pdo, $userId, $date);
    $sleepTime = (string) $dailyLog['sleep_time'];

    $endSeconds = time_to_seconds($endTime);
    $sleepSeconds = time_to_seconds($sleepTime);

    if (
        $endSeconds === null ||
        $sleepSeconds === null ||
        $endSeconds > $sleepSeconds
    ) {
        throw new \InvalidArgumentException(
            'End time must be within the awake window.',
        );
    }

    $latestEntry = find_running_time_entry_for_daily_log(
        $pdo,
        (int) $dailyLog['id'],
    );

    if ($latestEntry === null) {
        throw new \InvalidArgumentException('No running entry to end.');
    }

    $entryStartTime = extract_time_component((string) $latestEntry['start']);
    $entryStartSeconds =
        $entryStartTime !== null ? time_to_seconds($entryStartTime) : null;

    if ($entryStartSeconds === null || $endSeconds <= $entryStartSeconds) {
        throw new \InvalidArgumentException(
            'End time must be after the running entry\'s start time.',
        );
    }

    $endTimestamp = combine_date_and_time($date, $endTime);

    apply_time_entry_details(
        $pdo,
        $userId,
        (int) $latestEntry['id'],
        $currentEntryPayload,
    );

    $statement = $pdo->prepare(
        'UPDATE time_entries
         SET end = :end, state = :state
         WHERE id = :id',
    );
    $statement->execute([
        ':end' => $endTimestamp,
        ':state' => 'completed',
        ':id' => $latestEntry['id'],
    ]);

    $updated = find_time_entry_by_id($pdo, (int) $latestEntry['id']);

    if ($updated === null) {
        throw new \RuntimeException('Failed to reload the ended entry.');
    }

    mark_matching_checklist_item_complete_from_entry($pdo, $userId, $updated);

    return $updated;
}

function build_end_time_entry_response(
    \PDO $pdo,
    array $currentUser,
    array $payload,
    ?string $date = null,
    ?string $currentTime = null,
): array {
    $userId = (string) ($currentUser['id'] ?? '');

    if ($userId === '') {
        throw new \InvalidArgumentException('Current user id is required.');
    }

    $endTime = trim((string) ($payload['end'] ?? ''));
    $notes = (string) ($payload['notes'] ?? '');
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

    if ($endTime === '') {
        $endTime = $currentTime ?? date('H:i:s');
    }

    $resolvedDate = $date ?? date('Y-m-d');

    try {
        $entry = end_time_entry($pdo, $userId, $resolvedDate, $endTime, [
            'activity_id' => $activityId === false ? null : $activityId,
            'activity_subtype_id' =>
                $activitySubtypeId === false ? null : $activitySubtypeId,
            'notes' => $notes,
        ]);
    } catch (\InvalidArgumentException $exception) {
        return [
            'status' => 422,
            'body' => [
                'ok' => false,
                'error' => $exception->getMessage(),
            ],
        ];
    }

    return [
        'status' => 200,
        'body' => [
            'ok' => true,
            'entry' => $entry,
        ],
    ];
}
