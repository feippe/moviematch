CREATE TABLE IF NOT EXISTS matches (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_a_id    INT UNSIGNED NOT NULL,   -- always the smaller user id
    user_b_id    INT UNSIGNED NOT NULL,   -- always the bigger user id
    shared_count INT UNSIGNED NOT NULL,   -- how many movies both said YES to
    created_at   DATETIME     NOT NULL,
    UNIQUE KEY unique_pair (user_a_id, user_b_id),
    FOREIGN KEY (user_a_id) REFERENCES users (id) ON DELETE CASCADE,
    FOREIGN KEY (user_b_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
    name  VARCHAR(50)  NOT NULL PRIMARY KEY,
    value VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO settings (name, value) VALUES ('min_shared_movies', '3');