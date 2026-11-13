<?php

// Returns the shared database connection (PDO).
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

// Sends a JSON response and stops the script.
function json_response(int $status, array $body) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/*
Starts an API call: only POST is accepted, the body must be JSON,
and any unexpected error becomes a generic JSON error (details go to the error log).
Returns the decoded JSON body as an array.
*/
function api_start(bool $requireBody = true): array {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    set_exception_handler(function ($e) {
        error_log('MovieMatch error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        json_response(500, ['success' => false, 'error' => 'Server error. Please try again later.']);
    });

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        json_response(405, ['success' => false, 'error' => 'Method not allowed.']);
    }

    $raw = (string) file_get_contents('php://input');
    if ($raw === '' && !$requireBody) {
        return [];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        json_response(400, ['success' => false, 'error' => 'Invalid request.']);
    }
    return $data;
}

// Reads a text field from the JSON body. Passwords must use $trim = false.
function field(array $data, string $key, bool $trim = true): string {
    $value = $data[$key] ?? '';
    if (!is_string($value)) {
        return '';
    }
    return $trim ? trim($value) : $value;
}

// Creates a random token. The token goes to the user, only its hash goes to the database.
function make_token(): array {
    $token = bin2hex(random_bytes(32));
    return ['token' => $token, 'hash' => hash('sha256', $token)];
}

// Current time in UTC, in database format.
function utc_now(): string {
    return gmdate('Y-m-d H:i:s');
}

// Time in UTC after a number of seconds, in database format.
function utc_in(int $seconds): string {
    return gmdate('Y-m-d H:i:s', time() + $seconds);
}

function is_https(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

// Starts the PHP session with safe cookie settings.
function start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
