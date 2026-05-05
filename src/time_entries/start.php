<?php
declare(strict_types=1);

require_once __DIR__ . '/service.php';

function build_start_time_entry_response(
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

    $startTime = trim((string) ($payload['start'] ?? ''));
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

    if ($startTime === '') {
        $startTime = $currentTime ?? date('H:i:s');
    }

    if ($resolvedDate === null) {
        $resolvedDate = date('Y-m-d');
    }

    try {
        $entry = start_time_entry($pdo, $userId, $resolvedDate, $startTime, [
            'activity_id' => $activityId === false ? null : $activityId,
            'activity_subtype_id' =>
                $activitySubtypeId === false ? null : $activitySubtypeId,
            'notes' => $notes,
        ]);

        return [
            'status' => 201,
            'body' => [
                'ok' => true,
                'entry' => $entry,
            ],
        ];
    } catch (\InvalidArgumentException $exception) {
        return [
            'status' => 422,
            'body' => [
                'ok' => false,
                'error' => $exception->getMessage(),
            ],
        ];
    }
}
