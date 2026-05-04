<?php
declare(strict_types=1);

require_once __DIR__ . '/../activities/service.php';

const SUBTYPE_NAME_MAX_LENGTH = 100;

function validate_subtype_name(string $name): void
{
    if (trim($name) === '') {
        throw new \InvalidArgumentException('Subtype name is required.');
    }

    if (mb_strlen($name) > SUBTYPE_NAME_MAX_LENGTH) {
        throw new \InvalidArgumentException(
            'Subtype name must not exceed ' .
                SUBTYPE_NAME_MAX_LENGTH .
                ' characters.',
        );
    }
}

function require_activity_ownership(
    \PDO $pdo,
    int $activityId,
    string $userId,
): array {
    $activity = find_activity_by_id($pdo, $activityId);

    if ($activity === null || $activity['user_id'] !== $userId) {
        throw new \InvalidArgumentException('Activity not found.');
    }

    return $activity;
}

function find_activity_subtype_by_id(\PDO $pdo, int $subtypeId): ?array
{
    $statement = $pdo->prepare(
        'SELECT id, activity_id, name FROM activity_subtypes WHERE id = :id LIMIT 1',
    );
    $statement->execute([':id' => $subtypeId]);
    $row = $statement->fetch(\PDO::FETCH_ASSOC);

    return $row === false ? null : $row;
}

function find_activity_subtype_by_name(
    \PDO $pdo,
    int $activityId,
    string $name,
): ?array {
    $statement = $pdo->prepare(
        'SELECT id, activity_id, name FROM activity_subtypes
         WHERE activity_id = :activity_id AND name = :name LIMIT 1',
    );
    $statement->execute([':activity_id' => $activityId, ':name' => $name]);
    $row = $statement->fetch(\PDO::FETCH_ASSOC);

    return $row === false ? null : $row;
}

function create_activity_subtype(
    \PDO $pdo,
    int $activityId,
    string $userId,
    string $name,
): array {
    require_activity_ownership($pdo, $activityId, $userId);
    validate_subtype_name($name);

    if (find_activity_subtype_by_name($pdo, $activityId, $name) !== null) {
        throw new \InvalidArgumentException(
            "A subtype named \"{$name}\" already exists in this activity.",
        );
    }

    $statement = $pdo->prepare(
        'INSERT INTO activity_subtypes (activity_id, name) VALUES (:activity_id, :name)',
    );
    $statement->execute([':activity_id' => $activityId, ':name' => $name]);

    $created = find_activity_subtype_by_id($pdo, (int) $pdo->lastInsertId());

    if ($created === null) {
        throw new \RuntimeException('Failed to create the subtype.');
    }

    return $created;
}

function list_activity_subtypes(
    \PDO $pdo,
    int $activityId,
    string $userId,
): array {
    require_activity_ownership($pdo, $activityId, $userId);

    $statement = $pdo->prepare(
        'SELECT id, activity_id, name FROM activity_subtypes
         WHERE activity_id = :activity_id ORDER BY name ASC',
    );
    $statement->execute([':activity_id' => $activityId]);

    return $statement->fetchAll(\PDO::FETCH_ASSOC);
}

function update_activity_subtype(
    \PDO $pdo,
    int $subtypeId,
    int $activityId,
    string $userId,
    string $name,
): array {
    require_activity_ownership($pdo, $activityId, $userId);
    validate_subtype_name($name);

    $existing = find_activity_subtype_by_id($pdo, $subtypeId);

    if ($existing === null || (int) $existing['activity_id'] !== $activityId) {
        throw new \InvalidArgumentException('Subtype not found.');
    }

    $conflict = find_activity_subtype_by_name($pdo, $activityId, $name);

    if ($conflict !== null && (int) $conflict['id'] !== $subtypeId) {
        throw new \InvalidArgumentException(
            "A subtype named \"{$name}\" already exists in this activity.",
        );
    }

    $pdo->prepare(
        'UPDATE activity_subtypes SET name = :name WHERE id = :id',
    )->execute([':name' => $name, ':id' => $subtypeId]);

    return find_activity_subtype_by_id($pdo, $subtypeId);
}

function delete_activity_subtype(
    \PDO $pdo,
    int $subtypeId,
    int $activityId,
    string $userId,
): void {
    require_activity_ownership($pdo, $activityId, $userId);

    $existing = find_activity_subtype_by_id($pdo, $subtypeId);

    if ($existing === null || (int) $existing['activity_id'] !== $activityId) {
        throw new \InvalidArgumentException('Subtype not found.');
    }

    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM time_entries WHERE activity_subtype_id = :subtype_id',
    );
    $statement->execute([':subtype_id' => $subtypeId]);

    if ((int) $statement->fetchColumn() > 0) {
        throw new \InvalidArgumentException(
            'Cannot delete a subtype that has associated time entries.',
        );
    }

    $pdo->prepare('DELETE FROM activity_subtypes WHERE id = :id')->execute([
        ':id' => $subtypeId,
    ]);
}
