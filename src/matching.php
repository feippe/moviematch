<?php
/*
Finds every pair of users that match and saves the new ones in the matches table.
Two users match when they are in the same community, accept each other's sex
preference, and said YES to at least min_shared_movies of the same movies.
If $userId is given, only the pairs that include that user are checked (faster).
Returns how many new matches were created.
*/
function update_matches(?int $userId = null): int {
    $sql = "INSERT IGNORE INTO matches (user_a_id, user_b_id, shared_count, created_at)
            SELECT a.user_id, b.user_id, COUNT(*), ?
            FROM swipes a
            JOIN swipes b    ON b.movie_id = a.movie_id AND b.user_id > a.user_id
            JOIN users ua    ON ua.id = a.user_id
            JOIN users ub    ON ub.id = b.user_id
            JOIN profiles pa ON pa.user_id = a.user_id
            JOIN profiles pb ON pb.user_id = b.user_id
            WHERE a.decision = 'yes'
              AND b.decision = 'yes'
              AND ua.community_id = ub.community_id
              AND ua.email_verified_at IS NOT NULL AND ub.email_verified_at IS NOT NULL
              AND ua.blocked_at IS NULL AND ub.blocked_at IS NULL
              AND pa.photo_path IS NOT NULL AND pb.photo_path IS NOT NULL
              AND (pa.match_with = 'any' OR pa.match_with = pb.sex)
              AND (pb.match_with = 'any' OR pb.match_with = pa.sex)";

    $params = [utc_now()];

    if ($userId !== null) {
        $sql .= ' AND (a.user_id = ? OR b.user_id = ?)';
        $params[] = $userId;
        $params[] = $userId;
    }

    $sql .= " GROUP BY a.user_id, b.user_id
              HAVING COUNT(*) >= (SELECT CAST(value AS UNSIGNED) FROM settings WHERE name = 'min_shared_movies')";

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->rowCount();
}