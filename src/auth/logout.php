<?php
/**
 * Public route: POST /auth/logout.php
 *
 * Requires X-CSRF-Token header matching the value returned by me.php.
 *
 * Success 200:
 *   { "ok": true }
 */

require_once __DIR__ . '/helpers.php';

start_session();
require_method('POST');
validate_csrf();

// Clear session data.
$_SESSION = [];

// Expire the session cookie immediately.
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly'],
    );
}

session_destroy();

json_response(['ok' => true]);
