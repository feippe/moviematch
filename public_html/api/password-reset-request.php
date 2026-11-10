<?php

require __DIR__ . '/../../src/init.php';

$data = api_start();
$result = request_password_reset(field($data, 'email'));
json_response($result['status'], $result['body']);
