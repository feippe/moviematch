<?php

require __DIR__ . '/../../src/init.php';

$data = api_start();
$result = login_user(field($data, 'email'), field($data, 'password', false));

if ($result['status'] === 200) {
    start_session();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $result['body']['user']['id'];
}

json_response($result['status'], $result['body']);
