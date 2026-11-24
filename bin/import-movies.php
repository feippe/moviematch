<?php

$configFile = __DIR__ . '/../config/config.php';

if (!is_file($configFile)) {
    fwrite(STDERR, "Missing config/config.php. Create it from config/config.sample.php and configure TMDB_API_KEY.\n");
    exit(1);
}

require $configFile;

if (!defined('TMDB_API_KEY') || trim((string) TMDB_API_KEY) === '') {
    fwrite(STDERR, "TMDB_API_KEY is not configured.\n");
    exit(1);
}

$dsn = sprintf(
    'mysql:host=%s;dbname=%s;charset=%s',
    DB_HOST,
    DB_NAME,
    defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4'
);

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $exception) {
    fwrite(STDERR, "Database connection failed: {$exception->getMessage()}\n");
    exit(1);
}

function fetchPopularMovies(string $apiKey, int $page): array
{
    $url = 'https://api.themoviedb.org/3/movie/popular?' . http_build_query([
        'api_key' => $apiKey,
        'language' => 'en-US',
        'page' => $page,
    ]);

    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);

    $body = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($body === false) {
        throw new RuntimeException('TMDB request failed: ' . $error);
    }

    if ($status !== 200) {
        throw new RuntimeException('TMDB returned HTTP ' . $status . ': ' . substr($body, 0, 400));
    }

    $payload = json_decode($body, true);
    if (!is_array($payload) || !isset($payload['results']) || !is_array($payload['results'])) {
        throw new RuntimeException('TMDB returned an invalid response.');
    }

    return $payload['results'];
}

$requestedPages = isset($argv[1]) ? (int) $argv[1] : 5;
$pageCount = max(1, min($requestedPages, 20));

$upsert = $pdo->prepare(
    'INSERT INTO movies
        (tmdb_id, title, overview, poster_path, release_date, popularity, created_at, updated_at)
     VALUES
        (:tmdb_id, :title, :overview, :poster_path, :release_date, :popularity, :created_at, :updated_at)
     ON DUPLICATE KEY UPDATE
        title = VALUES(title),
        overview = VALUES(overview),
        poster_path = VALUES(poster_path),
        release_date = VALUES(release_date),
        popularity = VALUES(popularity),
        updated_at = VALUES(updated_at)'
);

$processed = 0;
$skipped = 0;
$now = gmdate('Y-m-d H:i:s');

try {
    for ($page = 1; $page <= $pageCount; $page++) {
        foreach (fetchPopularMovies((string) TMDB_API_KEY, $page) as $movie) {
            $tmdbId = filter_var($movie['id'] ?? null, FILTER_VALIDATE_INT);
            $movieTitle = trim((string) ($movie['title'] ?? ''));

            if (!$tmdbId || $movieTitle === '') {
                $skipped++;
                continue;
            }

            $releaseDate = $movie['release_date'] ?? null;
            if (!is_string($releaseDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $releaseDate)) {
                $releaseDate = null;
            }

            $posterPath = $movie['poster_path'] ?? null;
            if (!is_string($posterPath) || $posterPath === '') {
                $posterPath = null;
            }

            $upsert->execute([
                ':tmdb_id' => $tmdbId,
                ':title' => mb_substr($movieTitle, 0, 255),
                ':overview' => (string) ($movie['overview'] ?? ''),
                ':poster_path' => $posterPath,
                ':release_date' => $releaseDate,
                ':popularity' => (float) ($movie['popularity'] ?? 0),
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);

            $processed++;
        }

        fwrite(STDOUT, "Processed TMDB page {$page}/{$pageCount}.\n");
    }

    fwrite(STDOUT, "Import finished. Processed: {$processed}; skipped: {$skipped}.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, "Movie import failed: {$exception->getMessage()}\n");
    exit(1);
}
