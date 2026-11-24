<?php

require __DIR__ . '/../../src/init.php';

$data = api_start();

// stops here with an error if the user is not logged in
$user = require_login();

// get the match id from the body
$matchId = 0;
if (isset($data['match_id']) && is_numeric($data['match_id'])) {
    $matchId = (int) $data['match_id'];
}

if ($matchId < 1) {
    json_response(400, ['success' => false, 'error' => 'A valid match ID is required.']);
}

$result = answer_match($matchId, (int) $user['id'], 'declined');
json_response($result['status'], $result['body']);