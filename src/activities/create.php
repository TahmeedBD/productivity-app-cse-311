<?php
declare(strict_types=1);

require_once __DIR__ . '/service.php';

function build_create_activity_response(
    \PDO $pdo,
    array $currentUser,
    array $payload,
): array {
    $userId = (string) ($currentUser['id'] ?? '');
    $name = trim((string) ($payload['name'] ?? ''));

    if ($name === '') {
        return [
            'status' => 422,
            'body' => ['ok' => false, 'error' => 'name is required.'],
        ];
    }

    try {
        $activity = create_activity($pdo, $userId, $name);
    } catch (\InvalidArgumentException $exception) {
        // Distinguish uniqueness conflict (409) from other validation (422)
        $status = str_contains($exception->getMessage(), 'already exists')
            ? 409
            : 422;

        return [
            'status' => $status,
            'body' => ['ok' => false, 'error' => $exception->getMessage()],
        ];
    }

    return ['status' => 201, 'body' => ['ok' => true, 'activity' => $activity]];
}
