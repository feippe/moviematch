<?php

require __DIR__ . '/../../src/init.php';

// this one uses GET, and it has no body
api_start(false, 'GET');

// stops here with an error if the user is not logged in
$user = require_login();

json_response(200, ['success' => true, 'user' => user_data($user)]);
