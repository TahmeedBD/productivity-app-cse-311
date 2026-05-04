<?php
require_once __DIR__ . '/../../src/auth/guard.php';
require_once __DIR__ . '/../../src/activity_subtypes/list.php';

require_method('GET');

$response = build_list_activity_subtypes_response($pdo, $currentUser, $_GET);

json_response($response['body'], $response['status']);
