<?php
declare(strict_types=1);

require_once __DIR__ . '/../daily_logs/helpers.php';
require_once __DIR__ . '/../checklist_items/service.php';

function can_track_daily_completions(\PDO $pdo): bool
{
    static $cache = [];

    $cacheKey = spl_object_id($pdo);

    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    try {
        $pdo->query('SELECT 1 FROM checklist_items LIMIT 1');
        $pdo->query('SELECT 1 FROM daily_completions LIMIT 1');
        $cache[$cacheKey] = true;
    } catch (\PDOException $exception) {
        $cache[$cacheKey] = false;
    }

    return $cache[$cacheKey];
}

function find_daily_completion_by_log_and_item(
    \PDO $pdo,
    int $dailyLogId,
    int $checklistItemId,
): ?array {
    $statement = $pdo->prepare(
        'SELECT id, user_id, daily_log_id, checklist_item_id, is_completed
         FROM daily_completions
         WHERE daily_log_id = :daily_log_id
           AND checklist_item_id = :checklist_item_id
         LIMIT 1',
    );
    $statement->execute([
        ':daily_log_id' => $dailyLogId,
        ':checklist_item_id' => $checklistItemId,
    ]);

    $row = $statement->fetch(\PDO::FETCH_ASSOC);

    return $row === false ? null : $row;
}

function mark_checklist_item_complete_for_day(
    \PDO $pdo,
    string $userId,
    int $dailyLogId,
    int $checklistItemId,
): array {
    $existing = find_daily_completion_by_log_and_item(
        $pdo,
        $dailyLogId,
        $checklistItemId,
    );

    if ($existing === null) {
        $statement = $pdo->prepare(
            'INSERT INTO daily_completions (
                user_id,
                daily_log_id,
                checklist_item_id,
                is_completed
             )
             VALUES (
                :user_id,
                :daily_log_id,
                :checklist_item_id,
                :is_completed
             )',
        );
        $statement->execute([
            ':user_id' => $userId,
            ':daily_log_id' => $dailyLogId,
            ':checklist_item_id' => $checklistItemId,
            ':is_completed' => 1,
        ]);

        $existing = find_daily_completion_by_log_and_item(
            $pdo,
            $dailyLogId,
            $checklistItemId,
        );
    }

    if ($existing === null) {
        throw new \RuntimeException('Failed to persist the daily completion.');
    }

    return $existing;
}

function mark_matching_checklist_item_complete_from_entry(
    \PDO $pdo,
    string $userId,
    array $entry,
): void {
    if (!can_track_daily_completions($pdo)) {
        return;
    }

    $activityId = isset($entry['activity_id'])
        ? (int) $entry['activity_id']
        : 0;
    $start = (string) ($entry['start'] ?? '');
    $end = (string) ($entry['end'] ?? '');
    $dailyLogId = isset($entry['daily_log_id'])
        ? (int) $entry['daily_log_id']
        : 0;

    if ($activityId <= 0 || $dailyLogId <= 0 || $end === '') {
        return;
    }

    $checklistItem = find_checklist_item_by_user_and_activity(
        $pdo,
        $userId,
        $activityId,
    );

    if ($checklistItem === null) {
        return;
    }

    $startTime = extract_time_component($start);
    $endTime = extract_time_component($end);
    $startSeconds = $startTime === null ? null : time_to_seconds($startTime);
    $endSeconds = $endTime === null ? null : time_to_seconds($endTime);

    if (
        $startSeconds === null ||
        $endSeconds === null ||
        $endSeconds < $startSeconds
    ) {
        return;
    }

    $requiredSeconds = (int) $checklistItem['min_duration_minutes'] * 60;

    if ($endSeconds - $startSeconds < $requiredSeconds) {
        return;
    }

    mark_checklist_item_complete_for_day(
        $pdo,
        $userId,
        $dailyLogId,
        (int) $checklistItem['id'],
    );
}

function list_daily_completion_summaries(\PDO $pdo, string $userId): array
{
    $statement = $pdo->prepare(
        'SELECT ci.id AS checklist_item_id,
                ci.activity_id,
                ci.min_duration_minutes,
                a.name AS activity_name,
                dl.date AS completion_date
         FROM checklist_items ci
         INNER JOIN activities a ON a.id = ci.activity_id
         LEFT JOIN daily_completions dc
            ON dc.checklist_item_id = ci.id
           AND dc.user_id = ci.user_id
           AND dc.is_completed = 1
         LEFT JOIN daily_logs dl ON dl.id = dc.daily_log_id
         WHERE ci.user_id = :user_id
         ORDER BY a.name ASC, dl.date ASC, ci.id ASC',
    );
    $statement->execute([':user_id' => $userId]);

    $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
    $summaries = [];

    foreach ($rows as $row) {
        $itemId = (int) $row['checklist_item_id'];

        if (!isset($summaries[$itemId])) {
            $summaries[$itemId] = [
                'checklist_item_id' => $itemId,
                'activity_id' => (int) $row['activity_id'],
                'activity_name' => (string) $row['activity_name'],
                'min_duration_minutes' => (int) $row['min_duration_minutes'],
                'completed_days_count' => 0,
                'completion_dates' => [],
            ];
        }

        if ($row['completion_date'] !== null) {
            $summaries[$itemId]['completion_dates'][] =
                (string) $row['completion_date'];
            $summaries[$itemId]['completed_days_count']++;
        }
    }

    return array_values($summaries);
}
