<?php
declare(strict_types=1);

require_once __DIR__ . '/service.php';

function build_today_time_entries_response(
    \PDO $pdo,
    array $currentUser,
    ?string $date = null,
): array {
    $userId = (string) ($currentUser['id'] ?? '');

    if ($userId === '') {
        throw new \InvalidArgumentException('Current user id is required.');
    }

    $entries = list_today_time_entries($pdo, $userId, $date ?? date('Y-m-d'));

    return [
        'status' => 200,
        'body' => [
            'ok' => true,
            'entries' => $entries,
        ],
    ];
}
