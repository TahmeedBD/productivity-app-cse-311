<?php
declare(strict_types=1);

const ACTIVITY_NAME_MAX_LENGTH = 100;

function validate_activity_name(string $name): void
{
    if (trim($name) === '') {
        throw new \InvalidArgumentException('Activity name is required.');
    }

    if (mb_strlen($name) > ACTIVITY_NAME_MAX_LENGTH) {
        throw new \InvalidArgumentException(
            'Activity name must not exceed ' .
                ACTIVITY_NAME_MAX_LENGTH .
                ' characters.',
        );
    }
}

function find_activity_by_id(\PDO $pdo, int $activityId): ?array
{
    $statement = $pdo->prepare(
        'SELECT id, user_id, name FROM activities WHERE id = :id LIMIT 1',
    );
    $statement->execute([':id' => $activityId]);
    $row = $statement->fetch(\PDO::FETCH_ASSOC);

    return $row === false ? null : $row;
}

function find_activity_by_user_and_name(
    \PDO $pdo,
    string $userId,
    string $name,
): ?array {
    $statement = $pdo->prepare(
        'SELECT id, user_id, name FROM activities
         WHERE user_id = :user_id AND name = :name LIMIT 1',
    );
    $statement->execute([':user_id' => $userId, ':name' => $name]);
    $row = $statement->fetch(\PDO::FETCH_ASSOC);

    return $row === false ? null : $row;
}

function create_activity(\PDO $pdo, string $userId, string $name): array
{
    validate_activity_name($name);

    if (find_activity_by_user_and_name($pdo, $userId, $name) !== null) {
        throw new \InvalidArgumentException(
            "An activity named \"{$name}\" already exists.",
        );
    }

    $statement = $pdo->prepare(
        'INSERT INTO activities (user_id, name) VALUES (:user_id, :name)',
    );
    $statement->execute([':user_id' => $userId, ':name' => $name]);

    $created = find_activity_by_id($pdo, (int) $pdo->lastInsertId());

    if ($created === null) {
        throw new \RuntimeException('Failed to create the activity.');
    }

    return $created;
}

function list_activities(\PDO $pdo, string $userId): array
{
    $statement = $pdo->prepare(
        'SELECT id, user_id, name FROM activities
         WHERE user_id = :user_id ORDER BY name ASC',
    );
    $statement->execute([':user_id' => $userId]);

    return $statement->fetchAll(\PDO::FETCH_ASSOC);
}

function update_activity(
    \PDO $pdo,
    int $activityId,
    string $userId,
    string $name,
): array {
    validate_activity_name($name);

    $existing = find_activity_by_id($pdo, $activityId);

    if ($existing === null || $existing['user_id'] !== $userId) {
        throw new \InvalidArgumentException('Activity not found.');
    }

    $conflict = find_activity_by_user_and_name($pdo, $userId, $name);

    if ($conflict !== null && (int) $conflict['id'] !== $activityId) {
        throw new \InvalidArgumentException(
            "An activity named \"{$name}\" already exists.",
        );
    }

    $statement = $pdo->prepare(
        'UPDATE activities SET name = :name WHERE id = :id',
    );
    $statement->execute([':name' => $name, ':id' => $activityId]);

    return find_activity_by_id($pdo, $activityId);
}

function delete_activity(\PDO $pdo, int $activityId, string $userId): void
{
    $existing = find_activity_by_id($pdo, $activityId);

    if ($existing === null || $existing['user_id'] !== $userId) {
        throw new \InvalidArgumentException('Activity not found.');
    }

    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM time_entries WHERE activity_id = :activity_id',
    );
    $statement->execute([':activity_id' => $activityId]);

    if ((int) $statement->fetchColumn() > 0) {
        throw new \InvalidArgumentException(
            'Cannot delete an activity that has associated time entries.',
        );
    }

    $pdo->prepare('DELETE FROM activities WHERE id = :id')->execute([
        ':id' => $activityId,
    ]);
}
