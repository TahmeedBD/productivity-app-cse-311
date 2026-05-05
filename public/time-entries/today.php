<?php
require_once __DIR__ . '/../../src/auth/guard.php';
require_once __DIR__ . '/../../src/time_entries/today.php';

require_method('GET');

$requestedDate = isset($_GET['date']) ? trim((string) $_GET['date']) : null;

if ($requestedDate === '') {
    $requestedDate = null;
}

if (
    $requestedDate !== null &&
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestedDate)
) {
    json_response(
        [
            'ok' => false,
            'error' => 'Invalid date format. Expected YYYY-MM-DD.',
        ],
        422,
    );
}

$response = build_today_time_entries_response(
    $pdo,
    $currentUser,
    $requestedDate,
);

json_response($response['body'], $response['status']);
