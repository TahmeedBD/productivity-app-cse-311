<?php
require_once __DIR__ . '/../../src/auth/guard.php';
require_once __DIR__ . '/../../src/daily_completions/list.php';

require_method('GET');

$response = build_list_daily_completion_summaries_response($pdo, $currentUser);

json_response($response['body'], $response['status']);
