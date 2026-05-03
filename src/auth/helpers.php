<?php

/**
 * Generate a UUID v4 string using cryptographically secure random bytes.
 */
function generate_uuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40); // version 4
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80); // variant
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Start a PHP session with secure cookie parameters.
 * Also ensures a CSRF token exists in the session.
 */
function start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

/**
 * Send a JSON response and terminate execution.
 *
 * @param array $data   Response payload.
 * @param int   $status HTTP status code.
 */
function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

/**
 * Read and decode a JSON request body.
 * Aborts with 415 if Content-Type is not application/json.
 * Aborts with 400 if the body is not valid JSON.
 *
 * @return array Decoded body as an associative array.
 */
function get_json_body(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') === false) {
        json_response(
            [
                'ok' => false,
                'error' => 'Content-Type must be application/json.',
            ],
            415,
        );
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!is_array($data)) {
        json_response(['ok' => false, 'error' => 'Invalid JSON body.'], 400);
    }

    return $data;
}

/**
 * Abort with 405 if the current HTTP method does not match the expected one.
 *
 * @param string $method Expected HTTP method (e.g. 'POST', 'GET').
 */
function require_method(string $method): void
{
    if ($_SERVER['REQUEST_METHOD'] !== strtoupper($method)) {
        json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
    }
}

/**
 * Validate the CSRF token supplied in the X-CSRF-Token request header.
 * Aborts with 403 on mismatch. Call after start_session().
 */
function validate_csrf(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (
        empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        json_response(['ok' => false, 'error' => 'Invalid CSRF token.'], 403);
    }
}
