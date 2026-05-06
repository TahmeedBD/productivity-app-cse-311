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

function find_latest_daily_log_before_date(
    \PDO $pdo,
    string $userId,
    string $date,
): ?array {
    $statement = $pdo->prepare(
        'SELECT id, user_id, date, wake_time, sleep_time
         FROM daily_logs
         WHERE user_id = :user_id AND date < :date
         ORDER BY date DESC
         LIMIT 1',
    );
    $statement->execute([
        ':user_id' => $userId,
        ':date' => $date,
    ]);

    $dailyLog = $statement->fetch(\PDO::FETCH_ASSOC);

    return $dailyLog === false ? null : $dailyLog;
}

function resolve_inherited_daily_log_times(
    \PDO $pdo,
    string $userId,
    string $date,
): array {
    $fallbackTimes = default_daily_log_times();
    $latestDailyLog = find_latest_daily_log_before_date($pdo, $userId, $date);

    if ($latestDailyLog === null) {
        return $fallbackTimes;
    }

    return [
        'wake_time' =>
            (string) ($latestDailyLog['wake_time'] ??
                $fallbackTimes['wake_time']),
        'sleep_time' =>
            (string) ($latestDailyLog['sleep_time'] ??
                $fallbackTimes['sleep_time']),
    ];
}

function get_effective_daily_log_times_for_date(
    \PDO $pdo,
    string $userId,
    string $date,
): array {
    $existingDailyLog = find_daily_log_by_user_and_date($pdo, $userId, $date);

    if ($existingDailyLog !== null) {
        return [
            'wake_time' => (string) ($existingDailyLog['wake_time'] ?? ''),
            'sleep_time' => (string) ($existingDailyLog['sleep_time'] ?? ''),
        ];
    }

    return resolve_inherited_daily_log_times($pdo, $userId, $date);
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
        resolve_inherited_daily_log_times($pdo, $userId, $date),
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

function update_daily_log_times(
    \PDO $pdo,
    int $dailyLogId,
    string $wakeTime,
    string $sleepTime,
): array {
    $statement = $pdo->prepare(
        'UPDATE daily_logs
         SET wake_time = :wake_time,
             sleep_time = :sleep_time
         WHERE id = :id',
    );
    $statement->execute([
        ':wake_time' => $wakeTime,
        ':sleep_time' => $sleepTime,
        ':id' => $dailyLogId,
    ]);

    $reloaded = $pdo
        ->query(
            'SELECT id, user_id, date, wake_time, sleep_time
             FROM daily_logs
             WHERE id = ' .
                $dailyLogId .
                '
             LIMIT 1',
        )
        ->fetch(\PDO::FETCH_ASSOC);

    if ($reloaded === false) {
        throw new \RuntimeException('Failed to reload the daily log.');
    }

    return $reloaded;
}

function upsert_daily_log_times(
    \PDO $pdo,
    string $userId,
    string $date,
    string $wakeTime,
    string $sleepTime,
): array {
    $existingDailyLog = find_daily_log_by_user_and_date($pdo, $userId, $date);

    if ($existingDailyLog !== null) {
        return update_daily_log_times(
            $pdo,
            (int) $existingDailyLog['id'],
            $wakeTime,
            $sleepTime,
        );
    }

    return get_or_create_daily_log($pdo, $userId, $date, [
        'wake_time' => $wakeTime,
        'sleep_time' => $sleepTime,
    ]);
}

function update_today_sleep_and_future_daily_log_times(
    \PDO $pdo,
    string $userId,
    string $todayDate,
    string $futureWakeTime,
    string $sleepTime,
): array {
    if (
        time_to_seconds($futureWakeTime) === null ||
        time_to_seconds($sleepTime) === null
    ) {
        throw new \InvalidArgumentException(
            'Wake and sleep times must be valid HH:MM:SS values.',
        );
    }

    $todayDailyLog = get_or_create_daily_log($pdo, $userId, $todayDate);
    $tomorrowDate = (new \DateTimeImmutable($todayDate))
        ->modify('+1 day')
        ->format('Y-m-d');

    $pdo->beginTransaction();

    try {
        $updateToday = $pdo->prepare(
            'UPDATE daily_logs
             SET sleep_time = :sleep_time
             WHERE id = :id',
        );
        $updateToday->execute([
            ':sleep_time' => $sleepTime,
            ':id' => $todayDailyLog['id'],
        ]);

        $updateFuture = $pdo->prepare(
            'UPDATE daily_logs
             SET wake_time = :wake_time,
                 sleep_time = :sleep_time
             WHERE user_id = :user_id AND date > :today_date',
        );
        $updateFuture->execute([
            ':wake_time' => $futureWakeTime,
            ':sleep_time' => $sleepTime,
            ':user_id' => $userId,
            ':today_date' => $todayDate,
        ]);

        upsert_daily_log_times(
            $pdo,
            $userId,
            $tomorrowDate,
            $futureWakeTime,
            $sleepTime,
        );

        $updatedTodayDailyLog = find_daily_log_by_user_and_date(
            $pdo,
            $userId,
            $todayDate,
        );

        if ($updatedTodayDailyLog === null) {
            throw new \RuntimeException('Failed to reload today\'s daily log.');
        }

        $futureDefaults = get_effective_daily_log_times_for_date(
            $pdo,
            $userId,
            $tomorrowDate,
        );

        $pdo->commit();

        return [
            'today_daily_log' => $updatedTodayDailyLog,
            'future_defaults' => $futureDefaults,
        ];
    } catch (\Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}
