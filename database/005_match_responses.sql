-- Migration 005: Match Responses
-- Stores each user's acceptance or rejection of a match.

CREATE TABLE IF NOT EXISTS match_responses (
    match_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    response ENUM('accepted', 'declined') NOT NULL,
    responded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (match_id, user_id),

    FOREIGN KEY (match_id)
        REFERENCES matches (id)
        ON DELETE CASCADE,

    FOREIGN KEY (user_id)
        REFERENCES users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;