<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

function find_daily_log_by_user_and_date(
    \PDO $pdo,
    string $userId,
    string $date,
): ?array {
    $statement = $pdo->prepare(
        'SELECT id, user_id, date, wake_time, sleep_time
         FROM daily_logs
         WHERE user_id = :user_id AND date = :date
         LIMIT 1',
    );
    $statement->execute([
        ':user_id' => $userId,
        ':date' => $date,
    ]);

    $dailyLog = $statement->fetch(\PDO::FETCH_ASSOC);

    return $dailyLog === false ? null : $dailyLog;
}

function get_or_create_daily_log(
    \PDO $pdo,
    string $userId,
    string $date,
    ?array $dailyTimes = null,
): array {
    $existingDailyLog = find_daily_log_by_user_and_date($pdo, $userId, $date);

    if ($existingDailyLog !== null) {
        return $existingDailyLog;
    }

    $resolvedDailyTimes = array_merge(
        default_daily_log_times(),
        $dailyTimes ?? [],
    );

    try {
        $statement = $pdo->prepare(
            'INSERT INTO daily_logs (user_id, date, wake_time, sleep_time)
             VALUES (:user_id, :date, :wake_time, :sleep_time)',
        );
        $statement->execute([
            ':user_id' => $userId,
            ':date' => $date,
            ':wake_time' => $resolvedDailyTimes['wake_time'],
            ':sleep_time' => $resolvedDailyTimes['sleep_time'],
        ]);
    } catch (\PDOException $exception) {
        $existingDailyLog = find_daily_log_by_user_and_date(
            $pdo,
            $userId,
            $date,
        );

        if ($existingDailyLog !== null) {
            return $existingDailyLog;
        }

        throw $exception;
    }

    $createdDailyLog = find_daily_log_by_user_and_date($pdo, $userId, $date);

    if ($createdDailyLog === null) {
        throw new \RuntimeException('Failed to create a daily log record.');
    }

    return $createdDailyLog;
}
