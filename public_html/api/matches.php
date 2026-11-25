<?php
require __DIR__ . '/../../src/init.php';

api_start(false, 'GET');
$user = require_login();
$userId = (int) $user['id'];

// matches of this user, with the profile of the other person (matches where someone already declined are hidden)
$stmt = db()->prepare(
    "SELECT m.id, IF(m.user_a_id = ?, m.user_b_id, m.user_a_id) AS other_id,
            p.name, p.birth_date, p.sex, p.photo_path, p.match_with AS other_match_with,
            r.response AS my_response,
            (SELECT COUNT(*) FROM match_responses a WHERE a.match_id = m.id AND a.response = 'accepted') AS accepted_count
     FROM matches m
     JOIN profiles p ON p.user_id = IF(m.user_a_id = ?, m.user_b_id, m.user_a_id)
     JOIN users u    ON u.id = p.user_id AND u.blocked_at IS NULL
     LEFT JOIN match_responses r ON r.match_id = m.id AND r.user_id = ?
     WHERE (m.user_a_id = ? OR m.user_b_id = ?)
       AND NOT EXISTS (
           SELECT 1 FROM match_responses d
           WHERE d.match_id = m.id AND d.response = 'declined'
       )
     ORDER BY m.shared_count DESC, m.created_at DESC"
);
$stmt->execute([$userId, $userId, $userId, $userId, $userId]);
$rows = $stmt->fetchAll();

// titles of the movies both people said YES to
$moviesStmt = db()->prepare(
    "SELECT mo.title
     FROM swipes s1
     JOIN swipes s2 ON s2.movie_id = s1.movie_id AND s2.decision = 'yes'
     JOIN movies mo ON mo.id = s1.movie_id
     WHERE s1.user_id = ? AND s1.decision = 'yes' AND s2.user_id = ?
     ORDER BY mo.popularity DESC"
);

$matches = [];
foreach ($rows as $row) {
    $bothAccepted = (int) $row['accepted_count'] === 2;

    // do the preferences of both people still fit? they can change them after the match was made.
    $iAcceptThem = ($user['match_with'] === 'any' || $user['match_with'] === $row['sex']);
    $theyAcceptMe = ($row['other_match_with'] === 'any' || $row['other_match_with'] === $user['sex']);

    // hide the match if the preferences do not fit anymore, but keep it if both already accepted
    if (!$bothAccepted && (!$iAcceptThem || !$theyAcceptMe)) {
        continue;
    }

    $moviesStmt->execute([$userId, (int) $row['other_id']]);
    $matches[] = [
        'id'            => (int) $row['id'],
        'name'          => $row['name'],
        'age'           => get_age($row['birth_date']),
        'sex'           => $row['sex'],
        'photo_path'    => $row['photo_path'],
        'shared_movies' => $moviesStmt->fetchAll(PDO::FETCH_COLUMN),
        'my_response'   => $row['my_response'],
        'both_accepted' => $bothAccepted,
    ];
}

json_response(200, ['success' => true, 'matches' => $matches]);