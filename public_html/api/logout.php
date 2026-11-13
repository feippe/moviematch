<?php

require __DIR__ . '/../../src/init.php';

api_start(false);

start_session();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $cookie = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600, $cookie['path'], $cookie['domain'], $cookie['secure'], $cookie['httponly']);
}
session_destroy();

json_response(200, ['success' => true]);
