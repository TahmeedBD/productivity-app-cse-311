<?php
declare(strict_types=1);

require_once __DIR__ . '/service.php';

function add_past_time_entry(
    \PDO $pdo,
    string $userId,
    string $date,
    string $startTime,
    string $endTime,
    string $notes = '',
): array {
    $dailyLog = get_or_create_daily_log($pdo, $userId, $date);
    $wakeTime = (string) $dailyLog['wake_time'];
    $sleepTime = (string) $dailyLog['sleep_time'];

    if (
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

    $startTimestamp = combine_date_and_time($date, $startTime);
    $endTimestamp = combine_date_and_time($date, $endTime);

    $existingEntries = list_time_entries_for_daily_log(
        $pdo,
        (int) $dailyLog['id'],
    );

    $endSeconds = time_to_seconds($endTime);

    foreach ($existingEntries as $existing) {
        if ($existing['end'] === null) {
            // Running entry: it extends from its start to infinity.
            // Overlap if new entry's end is strictly after the running entry's start.
            $runningStartTime = extract_time_component(
                (string) $existing['start'],
            );
            $runningStartSeconds =
                $runningStartTime !== null
                    ? time_to_seconds($runningStartTime)
                    : null;

            if (
                $endSeconds !== null &&
                $runningStartSeconds !== null &&
                $endSeconds > $runningStartSeconds
            ) {
                throw new \InvalidArgumentException(
                    'New entry overlaps a running entry.',
                );
            }
        } else {
            $existingStart = extract_time_component(
                (string) $existing['start'],
            );
            $existingEnd = extract_time_component((string) $existing['end']);

            if (
                $existingStart !== null &&
                $existingEnd !== null &&
                time_ranges_overlap(
                    $startTime,
                    $endTime,
                    $existingStart,
                    $existingEnd,
                )
            ) {
                throw new \InvalidArgumentException(
                    'New entry overlaps an existing entry.',
                );
            }
        }
    }

    $insert = $pdo->prepare(
        'INSERT INTO time_entries (daily_log_id, start, end, state, notes)
         VALUES (:daily_log_id, :start, :end, :state, :notes)',
    );
    $insert->execute([
        ':daily_log_id' => $dailyLog['id'],
        ':start' => $startTimestamp,
        ':end' => $endTimestamp,
        ':state' => 'completed',
        ':notes' => $notes,
    ]);

    $created = find_time_entry_by_id($pdo, (int) $pdo->lastInsertId());

    if ($created === null) {
        throw new \RuntimeException('Failed to create the past entry.');
    }

    return $created;
}

function build_add_past_time_entry_response(
    \PDO $pdo,
    array $currentUser,
    array $payload,
    ?string $date = null,
): array {
    $userId = (string) ($currentUser['id'] ?? '');

    if ($userId === '') {
        throw new \InvalidArgumentException('Current user id is required.');
    }

    $startTime = trim((string) ($payload['start'] ?? ''));
    $endTime = trim((string) ($payload['end'] ?? ''));
    $notes = (string) ($payload['notes'] ?? '');

    if ($startTime === '') {
        return [
            'status' => 422,
            'body' => [
                'ok' => false,
                'error' => 'start is required.',
            ],
        ];
    }

    if ($endTime === '') {
        return [
            'status' => 422,
            'body' => [
                'ok' => false,
                'error' => 'end is required.',
            ],
        ];
    }

    $resolvedDate = $date ?? date('Y-m-d');

    try {
        $entry = add_past_time_entry(
            $pdo,
            $userId,
            $resolvedDate,
            $startTime,
            $endTime,
            $notes,
        );
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
        'status' => 201,
        'body' => [
            'ok' => true,
            'entry' => $entry,
        ],
    ];
}
