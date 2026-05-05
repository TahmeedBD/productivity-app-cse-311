<?php
declare(strict_types=1);

require_once __DIR__ . '/service.php';

function build_list_daily_completion_summaries_response(
    \PDO $pdo,
    array $currentUser,
): array {
    $userId = (string) ($currentUser['id'] ?? '');

    return [
        'status' => 200,
        'body' => [
            'ok' => true,
            'summaries' => list_daily_completion_summaries($pdo, $userId),
        ],
    ];
}
