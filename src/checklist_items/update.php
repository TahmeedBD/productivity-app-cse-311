<?php
declare(strict_types=1);

require_once __DIR__ . '/service.php';

function build_update_checklist_item_response(
    \PDO $pdo,
    array $currentUser,
    array $payload,
): array {
    $userId = (string) ($currentUser['id'] ?? '');
    $itemId = filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);
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

    if ($itemId === false) {
        return [
            'status' => 422,
            'body' => ['ok' => false, 'error' => 'id is required.'],
        ];
    }

    if ($activityId === false) {
        return [
            'status' => 422,
            'body' => ['ok' => false, 'error' => 'activity_id is required.'],
        ];
    }

    if ($minDurationMinutes === false) {
        return [
            'status' => 422,
            'body' => [
                'ok' => false,
                'error' => 'min_duration_minutes is required.',
            ],
        ];
    }

    try {
        $item = update_checklist_item(
            $pdo,
            $itemId,
            $userId,
            $activityId,
            $minDurationMinutes,
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

    return ['status' => 200, 'body' => ['ok' => true, 'item' => $item]];
}
