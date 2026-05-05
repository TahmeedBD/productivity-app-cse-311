<?php
declare(strict_types=1);

require_once __DIR__ . '/service.php';

function build_list_checklist_items_response(
    \PDO $pdo,
    array $currentUser,
): array {
    $userId = (string) ($currentUser['id'] ?? '');

    return [
        'status' => 200,
        'body' => [
            'ok' => true,
            'items' => list_checklist_items($pdo, $userId),
        ],
    ];
}
