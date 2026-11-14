-- migration 002: profiles and matching preferences
-- run this once on your database. also copy it into schema.sql for new installs.

-- every user is a normal user, unless we make them an admin
-- blocked_at stays empty until an admin blocks the user
ALTER TABLE users
    ADD COLUMN role ENUM('user', 'admin') NOT NULL DEFAULT 'user' AFTER community_id,
    ADD COLUMN blocked_at DATETIME NULL AFTER email_verified_at;

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
