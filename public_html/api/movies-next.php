<?php

declare(strict_types=1);

require __DIR__ . '/../../src/init.php';

api_start(false, 'GET');
$user = require_login();

$stmt = db()->prepare(
    'SELECT m.id, m.tmdb_id, m.title, m.overview, m.poster_path, m.release_date
     FROM movies AS m
     WHERE NOT EXISTS (
         SELECT 1
         FROM swipes AS s
         WHERE s.movie_id = m.id
           AND s.user_id = ?
     )
     ORDER BY m.popularity DESC, m.id DESC
     LIMIT 1'
);

$stmt->execute([(int) $user['id']]);
$movie = $stmt->fetch();

if (!$movie) {
    json_response(404, [
        'success' => false,
        'error' => 'There are no more movies available. Please check back later.',
    ]);
}

$movie['id'] = (int) $movie['id'];
$movie['tmdb_id'] = (int) $movie['tmdb_id'];
$movie['poster_url'] = $movie['poster_path'] !== null
    ? 'https://image.tmdb.org/t/p/w500' . $movie['poster_path']
    : null;

json_response(200, [
    'success' => true,
    'movie' => $movie,
]);
