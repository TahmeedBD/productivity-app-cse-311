<?php
declare(strict_types=1);

require_once __DIR__ . '/service.php';

function save_running_time_entry(
    \PDO $pdo,
    string $userId,
    string $date,
    array $payload,
): array {
    $dailyLog = get_or_create_daily_log($pdo, $userId, $date);
    $runningEntry = find_running_time_entry_for_daily_log(
        $pdo,
        (int) $dailyLog['id'],
    );

    if ($runningEntry === null) {
        throw new \InvalidArgumentException('No running entry to save.');
    }

    return apply_time_entry_details(
        $pdo,
        $userId,
        (int) $runningEntry['id'],
        $payload,
    );
}

function build_save_running_time_entry_response(
    \PDO $pdo,
    array $currentUser,
    array $payload,
    ?string $date = null,
): array {
    $userId = (string) ($currentUser['id'] ?? '');

    if ($userId === '') {
        throw new \InvalidArgumentException('Current user id is required.');
    }

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
    $notes = (string) ($payload['notes'] ?? '');

    try {
        $resolvedDate =
            $date ??
            normalize_time_entry_request_date($payload['date'] ?? null);
    } catch (\InvalidArgumentException $exception) {
        return [
            'status' => 422,
            'body' => [
                'ok' => false,
                'error' => $exception->getMessage(),
            ],
        ];
    }

    if ($resolvedDate === null) {
        $resolvedDate = date('Y-m-d');
    }

    try {
        $entry = save_running_time_entry($pdo, $userId, $resolvedDate, [
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
