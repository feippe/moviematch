<?php

require __DIR__ . '/../../src/init.php';

$data = api_start();
$result = reset_password(field($data, 'token'), field($data, 'password', false));
json_response($result['status'], $result['body']);
