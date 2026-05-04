<?php
declare(strict_types=1);

require_once __DIR__ . '/service.php';

function build_update_activity_response(
    \PDO $pdo,
    array $currentUser,
    array $payload,
): array {
    $userId = (string) ($currentUser['id'] ?? '');
    $activityId = isset($payload['id']) ? (int) $payload['id'] : null;
    $name = trim((string) ($payload['name'] ?? ''));

    if ($activityId === null || $activityId === 0) {
        return [
            'status' => 422,
            'body' => ['ok' => false, 'error' => 'id is required.'],
        ];
    }

    if ($name === '') {
        return [
            'status' => 422,
            'body' => ['ok' => false, 'error' => 'name is required.'],
        ];
    }

    try {
        $activity = update_activity($pdo, $activityId, $userId, $name);
    } catch (\InvalidArgumentException $exception) {
        $status = str_contains($exception->getMessage(), 'already exists')
            ? 409
            : 422;

        return [
            'status' => $status,
            'body' => ['ok' => false, 'error' => $exception->getMessage()],
        ];
    }

    return ['status' => 200, 'body' => ['ok' => true, 'activity' => $activity]];
}
