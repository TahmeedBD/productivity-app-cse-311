<?php
require_once __DIR__ . '/../../src/auth/guard.php';
require_once __DIR__ . '/../../src/checklist_items/update.php';

require_method('POST');

$body = get_json_body();
$response = build_update_checklist_item_response($pdo, $currentUser, $body);

json_response($response['body'], $response['status']);
