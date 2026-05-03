<?php
declare(strict_types=1);

require_once __DIR__ . '/service.php';

function build_today_daily_log_response(
    \PDO $pdo,
    array $currentUser,
    ?string $date = null,
): array {
    $userId = (string) ($currentUser['id'] ?? '');

    if ($userId === '') {
        throw new \InvalidArgumentException('Current user id is required.');
    }

    $resolvedDate = $date ?? date('Y-m-d');
    $dailyLog = get_or_create_daily_log($pdo, $userId, $resolvedDate);

    return [
        'status' => 200,
        'body' => [
            'ok' => true,
            'daily_log' => $dailyLog,
        ],
    ];
}
