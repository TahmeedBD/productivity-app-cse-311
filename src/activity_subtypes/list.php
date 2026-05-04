<?php
declare(strict_types=1);

require_once __DIR__ . '/service.php';

function build_list_activity_subtypes_response(
    \PDO $pdo,
    array $currentUser,
    array $payload,
): array {
    $userId = (string) ($currentUser['id'] ?? '');
    $activityId = isset($payload['activity_id'])
        ? (int) $payload['activity_id']
        : null;

    if ($activityId === null || $activityId === 0) {
        return [
            'status' => 422,
            'body' => ['ok' => false, 'error' => 'activity_id is required.'],
        ];
    }

    try {
        $subtypes = list_activity_subtypes($pdo, $activityId, $userId);
    } catch (\InvalidArgumentException $exception) {
        return [
            'status' => 422,
            'body' => ['ok' => false, 'error' => $exception->getMessage()],
        ];
    }

    return ['status' => 200, 'body' => ['ok' => true, 'subtypes' => $subtypes]];
}
