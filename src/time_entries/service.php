<?php
declare(strict_types=1);

require_once __DIR__ . '/../daily_logs/helpers.php';
require_once __DIR__ . '/../daily_logs/service.php';
require_once __DIR__ . '/../activities/service.php';
require_once __DIR__ . '/../activity_subtypes/service.php';

function is_entry_start_within_awake_window(
    string $startTime,
    string $wakeTime,
    string $sleepTime,
): bool {
    $startSeconds = time_to_seconds($startTime);
    $wakeSeconds = time_to_seconds($wakeTime);
    $sleepSeconds = time_to_seconds($sleepTime);

    if (
        $startSeconds === null ||
        $wakeSeconds === null ||
        $sleepSeconds === null
    ) {
        return false;
    }

    if ($sleepSeconds <= $wakeSeconds) {
        return false;
    }

    return $startSeconds >= $wakeSeconds && $startSeconds < $sleepSeconds;
}

function combine_date_and_time(string $date, string $time): string
{
    return $date . ' ' . $time;
}

function extract_time_component(string $timestamp): ?string
{
    $parts = explode(' ', $timestamp);

    return count($parts) === 2 ? $parts[1] : null;
}

function find_time_entry_by_id(\PDO $pdo, int $entryId): ?array
{
    $statement = $pdo->prepare(
        'SELECT id, daily_log_id, activity_id, activity_subtype_id, start, end, state, notes
         FROM time_entries
         WHERE id = :id
         LIMIT 1',
    );
    $statement->execute([':id' => $entryId]);

    $entry = $statement->fetch(\PDO::FETCH_ASSOC);

    return $entry === false ? null : $entry;
}

function find_latest_time_entry_for_daily_log(
    \PDO $pdo,
    int $dailyLogId,
): ?array {
    $statement = $pdo->prepare(
        'SELECT id, daily_log_id, activity_id, activity_subtype_id, start, end, state, notes
         FROM time_entries
         WHERE daily_log_id = :daily_log_id
         ORDER BY start DESC, id DESC
         LIMIT 1',
    );
    $statement->execute([':daily_log_id' => $dailyLogId]);

    $entry = $statement->fetch(\PDO::FETCH_ASSOC);

    return $entry === false ? null : $entry;
}

function list_time_entries_for_daily_log(\PDO $pdo, int $dailyLogId): array
{
    $statement = $pdo->prepare(
        'SELECT te.id,
                te.daily_log_id,
                te.activity_id,
                te.activity_subtype_id,
                te.start,
                te.end,
                te.state,
                te.notes,
                a.name AS activity_name,
                ast.name AS activity_subtype_name
         FROM time_entries te
         LEFT JOIN activities a ON a.id = te.activity_id
         LEFT JOIN activity_subtypes ast ON ast.id = te.activity_subtype_id
         WHERE te.daily_log_id = :daily_log_id
         ORDER BY te.start ASC, te.id ASC',
    );
    $statement->execute([':daily_log_id' => $dailyLogId]);

    return $statement->fetchAll(\PDO::FETCH_ASSOC);
}

function list_today_time_entries(\PDO $pdo, string $userId, string $date): array
{
    $dailyLog = get_or_create_daily_log($pdo, $userId, $date);

    return list_time_entries_for_daily_log($pdo, (int) $dailyLog['id']);
}

function validate_time_entry_classification(
    \PDO $pdo,
    string $userId,
    ?int $activityId,
    ?int $activitySubtypeId,
): array {
    if ($activityId === null && $activitySubtypeId === null) {
        return [
            'activity_id' => null,
            'activity_subtype_id' => null,
        ];
    }

    if ($activityId === null) {
        throw new \InvalidArgumentException(
            'Activity is required when subtype is selected.',
        );
    }

    require_activity_ownership($pdo, $activityId, $userId);

    if ($activitySubtypeId === null) {
        return [
            'activity_id' => $activityId,
            'activity_subtype_id' => null,
        ];
    }

    $subtype = find_activity_subtype_by_id($pdo, $activitySubtypeId);

    if ($subtype === null || (int) $subtype['activity_id'] !== $activityId) {
        throw new \InvalidArgumentException(
            'Subtype not found for the selected activity.',
        );
    }

    return [
        'activity_id' => $activityId,
        'activity_subtype_id' => $activitySubtypeId,
    ];
}

function start_time_entry(
    \PDO $pdo,
    string $userId,
    string $date,
    string $startTime,
    string $notes = '',
    ?int $activityId = null,
    ?int $activitySubtypeId = null,
): array {
    $dailyLog = get_or_create_daily_log($pdo, $userId, $date);
    $wakeTime = (string) $dailyLog['wake_time'];
    $sleepTime = (string) $dailyLog['sleep_time'];
    $classification = validate_time_entry_classification(
        $pdo,
        $userId,
        $activityId,
        $activitySubtypeId,
    );

    if (
        !is_entry_start_within_awake_window($startTime, $wakeTime, $sleepTime)
    ) {
        throw new \InvalidArgumentException(
            'Entry start must be inside the awake window.',
        );
    }

    $startTimestamp = combine_date_and_time($date, $startTime);

    $pdo->beginTransaction();

    try {
        $latestEntry = find_latest_time_entry_for_daily_log(
            $pdo,
            (int) $dailyLog['id'],
        );

        if ($latestEntry !== null) {
            $latestStartTime = extract_time_component(
                (string) $latestEntry['start'],
            );
            $newStartSeconds = time_to_seconds($startTime);
            $latestStartSeconds =
                $latestStartTime === null
                    ? null
                    : time_to_seconds($latestStartTime);

            if ($latestEntry['end'] === null) {
                if (
                    $newStartSeconds === null ||
                    $latestStartSeconds === null ||
                    $newStartSeconds <= $latestStartSeconds
                ) {
                    throw new \InvalidArgumentException(
                        'New entry must start after the current running entry.',
                    );
                }

                $update = $pdo->prepare(
                    'UPDATE time_entries
                     SET end = :end, state = :state
                     WHERE id = :id',
                );
                $update->execute([
                    ':end' => $startTimestamp,
                    ':state' => 'completed',
                    ':id' => $latestEntry['id'],
                ]);
            } else {
                $latestEndTime = extract_time_component(
                    (string) $latestEntry['end'],
                );
                $latestEndSeconds =
                    $latestEndTime === null
                        ? null
                        : time_to_seconds($latestEndTime);

                if (
                    $newStartSeconds === null ||
                    $latestEndSeconds === null ||
                    $newStartSeconds < $latestEndSeconds
                ) {
                    throw new \InvalidArgumentException(
                        'New entry must not overlap a previous entry.',
                    );
                }
            }
        }

        $insert = $pdo->prepare(
            'INSERT INTO time_entries (
                daily_log_id,
                activity_id,
                activity_subtype_id,
                start,
                end,
                state,
                notes
             )
             VALUES (
                :daily_log_id,
                :activity_id,
                :activity_subtype_id,
                :start,
                NULL,
                :state,
                :notes
             )',
        );
        $insert->execute([
            ':daily_log_id' => $dailyLog['id'],
            ':activity_id' => $classification['activity_id'],
            ':activity_subtype_id' => $classification['activity_subtype_id'],
            ':start' => $startTimestamp,
            ':state' => 'running',
            ':notes' => $notes,
        ]);

        $createdEntry = find_time_entry_by_id($pdo, (int) $pdo->lastInsertId());

        if ($createdEntry === null) {
            throw new \RuntimeException(
                'Failed to create the new running entry.',
            );
        }

        $pdo->commit();

        return $createdEntry;
    } catch (\Throwable $throwable) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $throwable;
    }
}
