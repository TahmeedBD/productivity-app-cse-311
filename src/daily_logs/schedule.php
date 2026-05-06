<?php
declare(strict_types=1);

require_once __DIR__ . '/service.php';

function normalize_daily_log_schedule_time(string $value): string
{
    $trimmed = trim($value);

    if ($trimmed === '') {
        throw new \InvalidArgumentException(
            'wake_time and sleep_time are required.',
        );
    }

    if (preg_match('/^\d{2}:\d{2}$/', $trimmed) === 1) {
        $trimmed .= ':00';
    }

    if (time_to_seconds($trimmed) === null) {
        throw new \InvalidArgumentException(
            'Wake and sleep times must use HH:MM format.',
        );
    }

    return $trimmed;
}

function build_daily_log_schedule_response(
    \PDO $pdo,
    array $currentUser,
    ?string $date = null,
): array {
    $userId = (string) ($currentUser['id'] ?? '');

    if ($userId === '') {
        throw new \InvalidArgumentException('Current user id is required.');
    }

    $resolvedDate = $date ?? date('Y-m-d');
    $todayDailyLog = get_or_create_daily_log($pdo, $userId, $resolvedDate);
    $tomorrowDate = (new \DateTimeImmutable($resolvedDate))
        ->modify('+1 day')
        ->format('Y-m-d');

    return [
        'status' => 200,
        'body' => [
            'ok' => true,
            'today_daily_log' => $todayDailyLog,
            'future_defaults' => get_effective_daily_log_times_for_date(
                $pdo,
                $userId,
                $tomorrowDate,
            ),
        ],
    ];
}

function build_update_daily_log_schedule_response(
    \PDO $pdo,
    array $currentUser,
    array $payload,
    ?string $date = null,
): array {
    $userId = (string) ($currentUser['id'] ?? '');

    if ($userId === '') {
        throw new \InvalidArgumentException('Current user id is required.');
    }

    try {
        $wakeTime = normalize_daily_log_schedule_time(
            (string) ($payload['wake_time'] ?? ''),
        );
        $sleepTime = normalize_daily_log_schedule_time(
            (string) ($payload['sleep_time'] ?? ''),
        );
    } catch (\InvalidArgumentException $exception) {
        return [
            'status' => 422,
            'body' => [
                'ok' => false,
                'error' => $exception->getMessage(),
            ],
        ];
    }

    $resolvedDate = $date ?? date('Y-m-d');

    try {
        $result = update_today_sleep_and_future_daily_log_times(
            $pdo,
            $userId,
            $resolvedDate,
            $wakeTime,
            $sleepTime,
        );
    } catch (\InvalidArgumentException $exception) {
        return [
            'status' => 422,
            'body' => [
                'ok' => false,
                'error' => $exception->getMessage(),
            ],
        ];
    }

    return [
        'status' => 200,
        'body' => [
            'ok' => true,
            'today_daily_log' => $result['today_daily_log'],
            'future_defaults' => $result['future_defaults'],
        ],
    ];
}
