<?php
/**
 * Public route: POST /auth/register.php
 *
 * Request body (application/json):
 *   { "email": "...", "username": "...", "password": "..." }
 *
 * Success 201:
 *   { "ok": true, "user": { "id": "...", "email": "...", "username": "..." } }
 *
 * Error 400 / 409:
 *   { "ok": false, "error": "..." }
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/helpers.php';

start_session();
require_method('POST');

$body = get_json_body();
$email = trim($body['email'] ?? '');
$username = trim($body['username'] ?? '');
$password = $body['password'] ?? '';

// --- Input validation ---
if ($email === '' || $username === '' || $password === '') {
    json_response(
        [
            'ok' => false,
            'error' => 'Email, username, and password are required.',
        ],
        400,
    );
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['ok' => false, 'error' => 'Invalid email address.'], 400);
}

if (strlen($password) < 8) {
    json_response(
        ['ok' => false, 'error' => 'Password must be at least 8 characters.'],
        400,
    );
}

if (strlen($username) < 2 || strlen($username) > 100) {
    json_response(
        [
            'ok' => false,
            'error' => 'Username must be between 2 and 100 characters.',
        ],
        400,
    );
}

// --- Insert user ---
$id = generate_uuid();
$hash = password_hash($password, PASSWORD_BCRYPT);

try {
    $stmt = $pdo->prepare(
        'INSERT INTO users (id, email, username, password_hash)
         VALUES (:id, :email, :username, :hash)',
    );
    $stmt->execute([
        ':id' => $id,
        ':email' => $email,
        ':username' => $username,
        ':hash' => $hash,
    ]);
} catch (\PDOException $e) {
    // SQLSTATE 23000 = integrity constraint violation (UNIQUE)
    if ($e->getCode() === '23000') {
        // Inspect the driver message to distinguish the offending column.
        // Avoid revealing which field is taken to limit enumeration, but the
        // plan explicitly separates the two errors — follow the plan.
        $msg = $e->getMessage();
        if (strpos($msg, 'email') !== false) {
            json_response(
                ['ok' => false, 'error' => 'Email already in use.'],
                409,
            );
        }
        json_response(
            ['ok' => false, 'error' => 'Username already in use.'],
            409,
        );
    }
    throw $e;
}

json_response(
    [
        'ok' => true,
        'user' => ['id' => $id, 'email' => $email, 'username' => $username],
    ],
    201,
);
