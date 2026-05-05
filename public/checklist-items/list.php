<?php
require_once __DIR__ . '/../../src/auth/guard.php';
require_once __DIR__ . '/../../src/checklist_items/list.php';

require_method('GET');

$response = build_list_checklist_items_response($pdo, $currentUser);

json_response($response['body'], $response['status']);
