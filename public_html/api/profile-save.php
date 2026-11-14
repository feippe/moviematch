<?php

require __DIR__ . '/../../src/init.php';

$data = api_start();

// stops here with an error if the user is not logged in
$user = require_login();

$result = save_profile(
    (int) $user['id'],
    field($data, 'name'),
    field($data, 'birth_date'),
    field($data, 'sex'),
    field($data, 'match_with')
);
json_response($result['status'], $result['body']);
