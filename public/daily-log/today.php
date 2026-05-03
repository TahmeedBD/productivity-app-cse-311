<?php
require_once __DIR__ . '/../../src/auth/guard.php';
require_once __DIR__ . '/../../src/daily_logs/today.php';

require_method('GET');

$response = build_today_daily_log_response($pdo, $currentUser);

json_response($response['body'], $response['status']);
