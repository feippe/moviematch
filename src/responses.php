<?php

// gets a match, but only if this user is one of the two people in it
function get_my_match(int $matchId, int $userId) {
    $stmt = db()->prepare(
        'SELECT id, user_a_id, user_b_id FROM matches WHERE id = ? AND (user_a_id = ? OR user_b_id = ?)'
    );
    $stmt->execute([$matchId, $userId, $userId]);
    $match = $stmt->fetch();

    // fetch gives false when nothing was found
    if (!$match) {
        return null;
    }

    // find out who the other person is
    if ((int) $match['user_a_id'] === $userId) {
        $match['other_id'] = (int) $match['user_b_id'];
    } else {
        $match['other_id'] = (int) $match['user_a_id'];
    }

    return $match;
}

// a match is closed if someone declined it, or if the other person is blocked
function match_is_closed(array $match): bool {
    $pdo = db();

    // did someone decline?
    $stmt = $pdo->prepare("SELECT user_id FROM match_responses WHERE match_id = ? AND response = 'declined'");
    $stmt->execute([$match['id']]);
    if ($stmt->fetch()) {
        return true;
    }

    // is the other person blocked?
    $stmt = $pdo->prepare('SELECT blocked_at FROM users WHERE id = ?');
    $stmt->execute([$match['other_id']]);
    $other = $stmt->fetch();
    if (!$other || $other['blocked_at'] !== null) {
        return true;
    }

    return false;
}

// saves the answer of the user. $response is 'accepted' or 'declined'.
function answer_match(int $matchId, int $userId, string $response): array {
    $match = get_my_match($matchId, $userId);
    if ($match === null) {
        return auth_fail(404, 'Match not found.');
    }

    if (match_is_closed($match)) {
        return auth_fail(409, 'This match is no longer available.');
    }

    $pdo = db();

    // each user can answer only one time
    $stmt = $pdo->prepare('SELECT response FROM match_responses WHERE match_id = ? AND user_id = ?');
    $stmt->execute([$matchId, $userId]);
    if ($stmt->fetch()) {
        return auth_fail(409, 'You already answered this match.');
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO match_responses (match_id, user_id, response, responded_at) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$matchId, $userId, $response, utc_now()]);
    } catch (PDOException $e) {
        // 23000 means the same answer was saved at the same time (double click)
        if ($e->getCode() === '23000') {
            return auth_fail(409, 'You already answered this match.');
        }
        throw $e;
    }

    if ($response === 'declined') {
        return auth_ok(['message' => 'Match declined.']);
    }

    // the user accepted. did the other person accept before?
    $stmt = $pdo->prepare(
        "SELECT user_id FROM match_responses WHERE match_id = ? AND user_id = ? AND response = 'accepted'"
    );
    $stmt->execute([$matchId, $match['other_id']]);

    if ($stmt->fetch()) {
        return auth_ok([
            'status'  => 'accepted',
            'message' => "It's a match! You can now see their contact.",
        ]);
    }

    return auth_ok([
        'status'  => 'waiting',
        'message' => 'Waiting for the other person to accept.',
    ]);
}

// gives the name and email of the other person, only when both accepted
function get_match_contact(int $matchId, int $userId): array {
    $match = get_my_match($matchId, $userId);
    if ($match === null) {
        return auth_fail(404, 'Match not found.');
    }

    if (match_is_closed($match)) {
        return auth_fail(409, 'This match is no longer available.');
    }

    $pdo = db();

    // both people must have accepted (so we need 2 accepted answers)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM match_responses WHERE match_id = ? AND response = 'accepted'");
    $stmt->execute([$matchId]);
    $acceptedCount = (int) $stmt->fetchColumn();

    if ($acceptedCount < 2) {
        return auth_fail(403, 'Both of you must accept first.');
    }

    // get the name and email of the other person
    $stmt = $pdo->prepare(
        'SELECT p.name, u.email FROM users u JOIN profiles p ON p.user_id = u.id WHERE u.id = ?'
    );
    $stmt->execute([$match['other_id']]);
    $other = $stmt->fetch();

    if (!$other) {
        return auth_fail(409, 'This match is no longer available.');
    }

    return auth_ok(['contact' => ['name' => $other['name'], 'email' => $other['email']]]);
}