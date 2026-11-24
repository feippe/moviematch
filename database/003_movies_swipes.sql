CREATE TABLE IF NOT EXISTS movies (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tmdb_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    overview TEXT NULL,
    poster_path VARCHAR(255) NULL,
    release_date DATE NULL,
    popularity DECIMAL(12, 4) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_movies_tmdb_id (tmdb_id),
    KEY idx_movies_popularity (popularity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS swipes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    movie_id BIGINT UNSIGNED NOT NULL,
    decision ENUM('yes', 'no') NOT NULL,
    swiped_at DATETIME NOT NULL,
    UNIQUE KEY uq_swipes_user_movie (user_id, movie_id),
    KEY idx_swipes_movie_id (movie_id),
    CONSTRAINT fk_swipes_user
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_swipes_movie
        FOREIGN KEY (movie_id) REFERENCES movies (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
