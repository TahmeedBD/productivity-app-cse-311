<?php
/**
 * Public route: GET /auth/me.php
 *
 * Used by the frontend on page load to check if the user is already logged in
 * and to retrieve the CSRF token needed for subsequent state-change requests.
 *
 * Logged-in 200:
 *   { "ok": true, "user": { "id": "...", "email": "...", "username": "..." }, "csrf_token": "..." }
 *
 * Not logged in 401:
 *   { "ok": false, "error": "Not authenticated." }
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/dev_login.php';

start_session();
apply_dev_auto_login($pdo);

if (empty($_SESSION['user_id'])) {
    json_response(['ok' => false, 'error' => 'Not authenticated.'], 401);
}

// Re-fetch the user from the DB to ensure the account still exists.
$stmt = $pdo->prepare('SELECT id, email, username FROM users WHERE id = :id');
$stmt->execute([':id' => $_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    // Account was deleted — invalidate the stale session.
    session_destroy();
    json_response(['ok' => false, 'error' => 'Not authenticated.'], 401);
}

json_response([
    'ok' => true,
    'user' => [
        'id' => $user['id'],
        'email' => $user['email'],
        'username' => $user['username'],
    ],
    'csrf_token' => $_SESSION['csrf_token'],
]);
