<?php

require __DIR__ . '/../../src/init.php';

$data = api_start();
$user = require_login();

$movieId = filter_var($data['movie_id'] ?? null, FILTER_VALIDATE_INT);
$decision = strtolower(trim((string) ($data['decision'] ?? '')));

if ($movieId === false || $movieId === null || $movieId < 1) {
    json_response(400, [
        'success' => false,
        'error' => 'A valid movie ID is required.',
    ]);
}

if (!in_array($decision, ['yes', 'no'], true)) {
    json_response(400, [
        'success' => false,
        'error' => 'Decision must be yes or no.',
    ]);
}

$pdo = db();

$movieCheck = $pdo->prepare('SELECT id FROM movies WHERE id = ?');
$movieCheck->execute([$movieId]);

if (!$movieCheck->fetch()) {
    json_response(404, [
        'success' => false,
        'error' => 'The selected movie does not exist.',
    ]);
}

$saveSwipe = $pdo->prepare(
    'INSERT INTO swipes (user_id, movie_id, decision, swiped_at)
     VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
        decision = VALUES(decision),
        swiped_at = VALUES(swiped_at)'
);

$saveSwipe->execute([
    (int) $user['id'],
    (int) $movieId,
    $decision,
    utc_now(),
]);

// look for new matches, but never fail the swipe if matching has a problem
if ($decision === 'yes') {
    try {
        update_matches((int) $user['id']);
    } catch (Throwable $e) {
        error_log('MovieMatch matching error: ' . $e->getMessage());
    }
}

json_response(200, [
    'success' => true,
    'message' => 'Your swipe has been saved.',
    'decision' => $decision,
]);
