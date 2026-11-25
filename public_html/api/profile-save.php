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

// the profile was saved, so the preferences may have changed. we look for new matches.
// if matching has a problem, the profile is still saved
if ($result['status'] === 200) {
    try {
        update_matches((int) $user['id']);
    } catch (Throwable $e) {
        error_log('MovieMatch matching error: ' . $e->getMessage());
    }
}

json_response($result['status'], $result['body']);