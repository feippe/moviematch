<?php

require __DIR__ . '/../../src/init.php';

// the photo comes as a file (not json), so there is no json body here
api_start(false);

// stops here with an error if the user is not logged in
$user = require_login();

// the browser puts the file in $_FILES, under the name "photo"
if (!isset($_FILES['photo'])) {
    json_response(400, ['success' => false, 'error' => 'Please choose a photo.']);
}

$result = save_photo((int) $user['id'], $_FILES['photo']);

// users need a photo to match, so after the photo is saved we look for new matches
// if matching has a problem, the photo is still saved
if ($result['status'] === 200) {
    try {
        update_matches((int) $user['id']);
    } catch (Throwable $e) {
        error_log('MovieMatch matching error: ' . $e->getMessage());
    }
}

json_response($result['status'], $result['body']);