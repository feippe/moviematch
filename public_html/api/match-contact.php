<?php

require __DIR__ . '/../../src/init.php';

// this one uses GET, and it has no body
api_start(false, 'GET');

// stops here with an error if the user is not logged in
$user = require_login();

// the match id comes in the url: /api/match-contact.php?match_id=12
$matchId = 0;
if (isset($_GET['match_id']) && is_numeric($_GET['match_id'])) {
    $matchId = (int) $_GET['match_id'];
}

if ($matchId < 1) {
    json_response(400, ['success' => false, 'error' => 'A valid match ID is required.']);
}

$result = get_match_contact($matchId, (int) $user['id']);
json_response($result['status'], $result['body']);