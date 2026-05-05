<?php
/**
 * guard.php — Reusable session enforcer.
 *
 * Include at the top of any PHP file that requires a valid session:
 *
 *   require_once __DIR__ . '/../src/auth/guard.php';
 *   // $currentUser is now available: ['id' => ..., 'email' => ..., 'username' => ...]
 *
 * Behaviour when the session is missing or invalid:
 *   - Fetch / JSON requests → HTTP 401 JSON response, execution stops.
 *   - Regular browser page loads → redirect to /login.php, execution stops.
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/helpers.php';

start_session();

if (empty($_SESSION['user_id'])) {
    // Detect whether the caller expects a JSON response.
    $acceptsJson =
        strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false ||
        strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false ||
        ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';

    if ($acceptsJson) {
        json_response(['ok' => false, 'error' => 'Not authenticated.'], 401);
    }

    header('Location: /login.php');
    exit();
}

// Re-fetch user to ensure the account still exists.
$stmt = $pdo->prepare('SELECT id, email, username FROM users WHERE id = :id');
$stmt->execute([':id' => $_SESSION['user_id']]);
$currentUser = $stmt->fetch();

if (!$currentUser) {
    session_destroy();

    $acceptsJson =
        strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false ||
        strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false ||
        ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';

    if ($acceptsJson) {
        json_response(['ok' => false, 'error' => 'Not authenticated.'], 401);
    }

    header('Location: /login.php');
    exit();
}

// $currentUser is now available to the including file.
