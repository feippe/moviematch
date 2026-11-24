CREATE TABLE IF NOT EXISTS communities (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    domain     VARCHAR(190) NOT NULL UNIQUE,   -- email domain, for example "byupathway.edu"
    created_at DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    email             VARCHAR(190) NOT NULL UNIQUE,
    password_hash     VARCHAR(255) NOT NULL,   -- password_hash(), never the plain password
    community_id      INT UNSIGNED NOT NULL,
    role              ENUM('user', 'admin') NOT NULL DEFAULT 'user',  -- everyone is a normal user unless we make them an admin
    email_verified_at DATETIME     NULL,       -- NULL until the user confirms the email
    blocked_at        DATETIME     NULL,       -- NULL = active. an admin sets it to block the user
    created_at        DATETIME     NOT NULL,
    FOREIGN KEY (community_id) REFERENCES communities (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS email_verifications (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    token_hash CHAR(64)     NOT NULL UNIQUE, -- Tokens are saved hashed (SHA-256), so a leaked database does not leak usable links.
    expires_at DATETIME     NOT NULL,
    created_at DATETIME     NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS password_resets (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    token_hash CHAR(64)     NOT NULL UNIQUE,
    expires_at DATETIME     NOT NULL,
    created_at DATETIME     NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- one profile per user
CREATE TABLE IF NOT EXISTS profiles (
    user_id    INT UNSIGNED NOT NULL PRIMARY KEY,
    name       VARCHAR(60)  NOT NULL,
    birth_date DATE         NOT NULL,              -- we calculate the age from this date
    sex        ENUM('female', 'male') NOT NULL,
    photo_path VARCHAR(255) NULL,                  -- empty until the user uploads a photo
    match_mode ENUM('one_to_one') NOT NULL DEFAULT 'one_to_one',  -- group mode comes later
    match_with ENUM('female', 'male', 'any') NOT NULL DEFAULT 'any',
    created_at DATETIME     NOT NULL,
    updated_at DATETIME     NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS matches (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_a_id    INT UNSIGNED NOT NULL,
    user_b_id    INT UNSIGNED NOT NULL,
    shared_count INT UNSIGNED NOT NULL,
    created_at   DATETIME NOT NULL,
    UNIQUE KEY unique_pair (user_a_id, user_b_id),
    FOREIGN KEY (user_a_id) REFERENCES users (id) ON DELETE CASCADE,
    FOREIGN KEY (user_b_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
    name  VARCHAR(50)  NOT NULL PRIMARY KEY,
    value VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO settings (name, value) VALUES ('min_shared_movies', '3');

CREATE TABLE IF NOT EXISTS movies (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tmdb_id      INT UNSIGNED NOT NULL,
    title        VARCHAR(255) NOT NULL,
    overview     TEXT NULL,
    poster_path  VARCHAR(255) NULL,
    release_date DATE NULL,
    popularity   DECIMAL(12, 4) NOT NULL DEFAULT 0,
    created_at   DATETIME NOT NULL,
    updated_at   DATETIME NOT NULL,
    UNIQUE KEY uq_movies_tmdb_id (tmdb_id),
    KEY idx_movies_popularity (popularity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS swipes (
    id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id   INT UNSIGNED NOT NULL,
    movie_id  BIGINT UNSIGNED NOT NULL,
    decision  ENUM('yes', 'no') NOT NULL,
    swiped_at DATETIME NOT NULL,
    UNIQUE KEY uq_swipes_user_movie (user_id, movie_id),
    KEY idx_swipes_movie_id (movie_id),
    CONSTRAINT fk_swipes_user
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_swipes_movie
        FOREIGN KEY (movie_id) REFERENCES movies (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS match_responses (
    match_id     INT UNSIGNED NOT NULL,
    user_id      INT UNSIGNED NOT NULL,
    response     ENUM('accepted', 'declined') NOT NULL,
    responded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (match_id, user_id),
    FOREIGN KEY (match_id) REFERENCES matches (id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)  REFERENCES users (id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;