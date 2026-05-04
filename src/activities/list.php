<?php
declare(strict_types=1);

require_once __DIR__ . '/service.php';

function build_list_activities_response(\PDO $pdo, array $currentUser): array
{
    $userId = (string) ($currentUser['id'] ?? '');
    $activities = list_activities($pdo, $userId);

    return [
        'status' => 200,
        'body' => ['ok' => true, 'activities' => $activities],
    ];
}
