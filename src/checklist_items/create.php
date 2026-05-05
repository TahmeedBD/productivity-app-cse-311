<?php
declare(strict_types=1);

require_once __DIR__ . '/service.php';

function build_create_checklist_item_response(
    \PDO $pdo,
    array $currentUser,
    array $payload,
): array {
    $userId = (string) ($currentUser['id'] ?? '');
    $activityId = filter_var(
        $payload['activity_id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]],
    );
    $minDurationMinutes = filter_var(
        $payload['min_duration_minutes'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]],
    );

    if ($activityId === false) {
        return [
            'status' => 422,
            'body' => ['ok' => false, 'error' => 'activity_id is required.'],
        ];
    }

    try {
        $item = create_checklist_item(
            $pdo,
            $userId,
            $activityId,
            $minDurationMinutes === false ? null : $minDurationMinutes,
        );
    } catch (\InvalidArgumentException $exception) {
        $status = str_contains($exception->getMessage(), 'already exists')
            ? 409
            : 422;

        return [
            'status' => $status,
            'body' => ['ok' => false, 'error' => $exception->getMessage()],
        ];
    }

    return ['status' => 201, 'body' => ['ok' => true, 'item' => $item]];
}
