<?php
require_once __DIR__ . '/../../src/auth/guard.php';
require_once __DIR__ . '/../../src/activities/list.php';

require_method('GET');

$response = build_list_activities_response($pdo, $currentUser);

json_response($response['body'], $response['status']);
