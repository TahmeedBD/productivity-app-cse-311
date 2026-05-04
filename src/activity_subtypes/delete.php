<?php
declare(strict_types=1);

require_once __DIR__ . '/service.php';

function build_delete_activity_subtype_response(
    \PDO $pdo,
    array $currentUser,
    array $payload,
): array {
    $userId = (string) ($currentUser['id'] ?? '');
    $subtypeId = isset($payload['id']) ? (int) $payload['id'] : null;
    $activityId = isset($payload['activity_id'])
        ? (int) $payload['activity_id']
        : null;

    if ($subtypeId === null || $subtypeId === 0) {
        return [
            'status' => 422,
            'body' => ['ok' => false, 'error' => 'id is required.'],
        ];
    }

    if ($activityId === null || $activityId === 0) {
        return [
            'status' => 422,
            'body' => ['ok' => false, 'error' => 'activity_id is required.'],
        ];
    }

    try {
        delete_activity_subtype($pdo, $subtypeId, $activityId, $userId);
    } catch (\InvalidArgumentException $exception) {
        $status = str_contains($exception->getMessage(), 'Cannot delete')
            ? 409
            : 422;

        return [
            'status' => $status,
            'body' => ['ok' => false, 'error' => $exception->getMessage()],
        ];
    }

    return ['status' => 200, 'body' => ['ok' => true]];
}
