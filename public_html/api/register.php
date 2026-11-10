<?php

require __DIR__ . '/../../src/init.php';

$data = api_start();
$result = register_user(field($data, 'email'), field($data, 'password', false));
json_response($result['status'], $result['body']);
