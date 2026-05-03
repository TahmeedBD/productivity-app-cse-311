<?php
require_once __DIR__ . '/../../src/auth/guard.php';
require_once __DIR__ . '/../../src/time_entries/start.php';

require_method('POST');

$body = get_json_body();
$response = build_start_time_entry_response($pdo, $currentUser, $body);

json_response($response['body'], $response['status']);
