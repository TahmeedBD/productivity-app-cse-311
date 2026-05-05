<?php
/**
 * Public route: POST /auth/login.php
 *
 * Request body (application/json):
 *   { "identifier": "...", "password": "..." }
 *
 * Success 200 (sets PHPSESSID cookie, session contains user_id):
 *   { "ok": true, "user": { "id": "...", "email": "...", "username": "..." } }
 *
 * Error 401:
 *   { "ok": false, "error": "Invalid credentials." }
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/helpers.php';

start_session();
require_method('POST');

$body = get_json_body();
$identifier = trim((string) ($body['identifier'] ?? ($body['email'] ?? '')));
$password = $body['password'] ?? '';

if ($identifier === '' || $password === '') {
    json_response(
        [
            'ok' => false,
            'error' => 'Username or email and password are required.',
        ],
        400,
    );
}

// Fetch the user by email or username.
$stmt = $pdo->prepare(
    'SELECT id, email, username, password_hash
     FROM users
     WHERE email = :email_identifier OR username = :username_identifier
     LIMIT 1',
);
$stmt->execute([
    ':email_identifier' => $identifier,
    ':username_identifier' => $identifier,
]);
$user = $stmt->fetch();

// Always run password_verify to prevent timing-based user enumeration.
// If no user was found, verify against a dummy hash so the call takes the
// same amount of time as a real comparison.
$dummyHash = '$2y$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ012345';
$hash = $user ? $user['password_hash'] : $dummyHash;

if (!$user || !password_verify($password, $hash)) {
    // Same error for bad identifier AND bad password — prevents enumeration.
    json_response(['ok' => false, 'error' => 'Invalid credentials.'], 401);
}

// Prevent session fixation: regenerate the session ID before writing auth data.
session_regenerate_id(true);

$_SESSION['user_id'] = $user['id'];
$_SESSION['email'] = $user['email'];
$_SESSION['username'] = $user['username'];

json_response([
    'ok' => true,
    'user' => [
        'id' => $user['id'],
        'email' => $user['email'],
        'username' => $user['username'],
    ],
]);
