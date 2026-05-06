<?php
require_once __DIR__ . '/../../src/auth/guard.php';
require_once __DIR__ . '/../../src/daily_logs/schedule.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $response = build_daily_log_schedule_response($pdo, $currentUser);
    json_response($response['body'], $response['status']);
}

if ($method === 'POST') {
    $body = get_json_body();
    $response = build_update_daily_log_schedule_response(
        $pdo,
        $currentUser,
        $body,
    );
    json_response($response['body'], $response['status']);
}

json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
