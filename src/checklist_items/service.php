<?php
declare(strict_types=1);

require_once __DIR__ . '/../activities/service.php';

const CHECKLIST_DEFAULT_MIN_DURATION_MINUTES = 20;

function validate_checklist_item_min_duration(int $minDurationMinutes): void
{
    if ($minDurationMinutes <= 0) {
        throw new \InvalidArgumentException(
            'min_duration_minutes must be greater than zero.',
        );
    }
}

function find_checklist_item_by_id(\PDO $pdo, int $itemId): ?array
{
    $statement = $pdo->prepare(
        'SELECT id, user_id, activity_id, min_duration_minutes
         FROM checklist_items
         WHERE id = :id
         LIMIT 1',
    );
    $statement->execute([':id' => $itemId]);

    $item = $statement->fetch(\PDO::FETCH_ASSOC);

    return $item === false ? null : $item;
}

function find_checklist_item_by_user_and_activity(
    \PDO $pdo,
    string $userId,
    int $activityId,
): ?array {
    $statement = $pdo->prepare(
        'SELECT id, user_id, activity_id, min_duration_minutes
         FROM checklist_items
         WHERE user_id = :user_id AND activity_id = :activity_id
         LIMIT 1',
    );
    $statement->execute([
        ':user_id' => $userId,
        ':activity_id' => $activityId,
    ]);

    $item = $statement->fetch(\PDO::FETCH_ASSOC);

    return $item === false ? null : $item;
}

function list_checklist_items(\PDO $pdo, string $userId): array
{
    $statement = $pdo->prepare(
        'SELECT ci.id,
                ci.user_id,
                ci.activity_id,
                ci.min_duration_minutes,
                a.name AS activity_name
         FROM checklist_items ci
         INNER JOIN activities a ON a.id = ci.activity_id
         WHERE ci.user_id = :user_id
         ORDER BY a.name ASC, ci.id ASC',
    );
    $statement->execute([':user_id' => $userId]);

    return $statement->fetchAll(\PDO::FETCH_ASSOC);
}

function create_checklist_item(
    \PDO $pdo,
    string $userId,
    int $activityId,
    ?int $minDurationMinutes = null,
): array {
    $activity = find_activity_by_id($pdo, $activityId);

    if ($activity === null || $activity['user_id'] !== $userId) {
        throw new \InvalidArgumentException('Activity not found.');
    }

    if (
        find_checklist_item_by_user_and_activity($pdo, $userId, $activityId) !==
        null
    ) {
        throw new \InvalidArgumentException(
            'A checklist item already exists for that activity.',
        );
    }

    $resolvedMinDuration =
        $minDurationMinutes ?? CHECKLIST_DEFAULT_MIN_DURATION_MINUTES;
    validate_checklist_item_min_duration($resolvedMinDuration);

    $statement = $pdo->prepare(
        'INSERT INTO checklist_items (user_id, activity_id, min_duration_minutes)
         VALUES (:user_id, :activity_id, :min_duration_minutes)',
    );
    $statement->execute([
        ':user_id' => $userId,
        ':activity_id' => $activityId,
        ':min_duration_minutes' => $resolvedMinDuration,
    ]);

    $created = find_checklist_item_by_id($pdo, (int) $pdo->lastInsertId());

    if ($created === null) {
        throw new \RuntimeException('Failed to create the checklist item.');
    }

    return $created;
}

function update_checklist_item(
    \PDO $pdo,
    int $itemId,
    string $userId,
    int $activityId,
    int $minDurationMinutes,
): array {
    $existing = find_checklist_item_by_id($pdo, $itemId);

    if ($existing === null || $existing['user_id'] !== $userId) {
        throw new \InvalidArgumentException('Checklist item not found.');
    }

    $activity = find_activity_by_id($pdo, $activityId);

    if ($activity === null || $activity['user_id'] !== $userId) {
        throw new \InvalidArgumentException('Activity not found.');
    }

    $conflict = find_checklist_item_by_user_and_activity(
        $pdo,
        $userId,
        $activityId,
    );

    if ($conflict !== null && (int) $conflict['id'] !== $itemId) {
        throw new \InvalidArgumentException(
            'A checklist item already exists for that activity.',
        );
    }

    validate_checklist_item_min_duration($minDurationMinutes);

    $statement = $pdo->prepare(
        'UPDATE checklist_items
         SET activity_id = :activity_id,
             min_duration_minutes = :min_duration_minutes
         WHERE id = :id',
    );
    $statement->execute([
        ':activity_id' => $activityId,
        ':min_duration_minutes' => $minDurationMinutes,
        ':id' => $itemId,
    ]);

    $updated = find_checklist_item_by_id($pdo, $itemId);

    if ($updated === null) {
        throw new \RuntimeException('Failed to reload the checklist item.');
    }

    return $updated;
}
