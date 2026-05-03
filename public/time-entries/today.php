<?php
require_once __DIR__ . '/../../src/auth/guard.php';
require_once __DIR__ . '/../../src/time_entries/today.php';

require_method('GET');

$response = build_today_time_entries_response($pdo, $currentUser);

json_response($response['body'], $response['status']);
