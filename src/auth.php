<?php

const MIN_PASSWORD_LENGTH = 8;
const MAX_PASSWORD_LENGTH = 72;      // bcrypt only uses the first 72 bytes
const VERIFICATION_LIFETIME = 86400; // 24 hours
const RESET_LIFETIME = 3600;         // 1 hour

// Personal email providers. They are not universities, so they cannot create a community.
// This check is skipped when APP_ENV = 'local' so we can test with any email.
const FREE_EMAIL_DOMAINS = [
    'gmail.com', 'googlemail.com', 'hotmail.com', 'outlook.com', 'live.com', 'msn.com',
    'yahoo.com', 'icloud.com', 'me.com', 'aol.com', 'proton.me', 'protonmail.com',
];

function auth_result(int $status, array $body): array {
    return ['status' => $status, 'body' => $body];
}

function auth_fail(int $status, string $error): array {
    return auth_result($status, ['success' => false, 'error' => $error]);
}

function auth_ok(array $body = []): array{
    return auth_result(200, array_merge(['success' => true], $body));
}

// Returns an error message if the password is not valid, or null if it is OK.
function password_error(string $password): ?string {
    if (strlen($password) < MIN_PASSWORD_LENGTH) {
        return 'The password must have at least ' . MIN_PASSWORD_LENGTH . ' characters.';
    }
    if (strlen($password) > MAX_PASSWORD_LENGTH) {
        return 'The password must have at most ' . MAX_PASSWORD_LENGTH . ' characters.';
    }
    return null;
}

function normalize_email(string $email): string {
    return strtolower(trim($email));
}

function email_domain(string $email): string{
    return substr($email, strrpos($email, '@') + 1);
}

function is_allowed_domain(string $domain): bool {
    if (APP_ENV === 'local') {
        return true;
    }
    return !in_array($domain, FREE_EMAIL_DOMAINS, true);
}

// Returns the id of the community for a domain. It creates the community if it does not exist.
function find_or_create_community(PDO $pdo, string $domain): int {
    $stmt = $pdo->prepare('SELECT id FROM communities WHERE domain = ?');
    $stmt->execute([$domain]);
    $row = $stmt->fetch();
    if ($row) {
        return (int) $row['id'];
    }

    $stmt = $pdo->prepare('INSERT INTO communities (domain, created_at) VALUES (?, ?)');
    $stmt->execute([$domain, utc_now()]);
    return (int) $pdo->lastInsertId();
}

function register_user(string $email, string $password): array {
    $email = normalize_email($email);
    if ($email === '' || strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return auth_fail(400, 'Please enter a valid email.');
    }

    $passwordError = password_error($password);
    if ($passwordError !== null) {
        return auth_fail(400, $passwordError);
    }

    $domain = email_domain($email);
    if (!is_allowed_domain($domain)) {
        return auth_fail(400, 'Please use your university email.');
    }

    $pdo = db();
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return auth_fail(409, 'This email is already registered.');
    }

    $pdo->beginTransaction();
    try {
        $communityId = find_or_create_community($pdo, $domain);
        $now = utc_now();

        $stmt = $pdo->prepare(
            'INSERT INTO users (email, password_hash, community_id, created_at) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$email, password_hash($password, PASSWORD_DEFAULT), $communityId, $now]);
        $userId = (int) $pdo->lastInsertId();

        $token = make_token();
        $stmt = $pdo->prepare(
            'INSERT INTO email_verifications (user_id, token_hash, expires_at, created_at) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $token['hash'], utc_in(VERIFICATION_LIFETIME), $now]);

        // If the email cannot be sent, the account is not created (the user can try again).
        if (!send_verification_email($email, $token['token'])) {
            $pdo->rollBack();
            return auth_fail(500, 'We could not send the verification email. Please try again later.');
        }

        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($e->getCode() === '23000') { // duplicate email (two sign ups at the same time)
            return auth_fail(409, 'This email is already registered.');
        }
        throw $e;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return auth_result(201, ['success' => true, 'message' => 'Check your email to confirm your account.']);
}

// Confirms the email with the token from the link. Returns true if the token is valid.
function verify_email(string $token): bool {
    if ($token === '') {
        return false;
    }

    $pdo = db();
    $stmt = $pdo->prepare('SELECT user_id FROM email_verifications WHERE token_hash = ? AND expires_at > ?');
    $stmt->execute([hash('sha256', $token), utc_now()]);
    $row = $stmt->fetch();
    if (!$row) {
        return false;
    }

    $userId = (int) $row['user_id'];
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('UPDATE users SET email_verified_at = ? WHERE id = ? AND email_verified_at IS NULL');
    $stmt->execute([utc_now(), $userId]);
    $stmt = $pdo->prepare('DELETE FROM email_verifications WHERE user_id = ?');
    $stmt->execute([$userId]);
    $pdo->commit();

    return true;
}

// On success the body has the "user" data, and the API file starts the session.
function login_user(string $email, string $password): array {
    static $dummyHash = null; // used when the email does not exist, so the answer takes a similar time
    if ($dummyHash === null) {
        $dummyHash = password_hash('not-a-real-password', PASSWORD_DEFAULT);
    }

    $email = normalize_email($email);
    $stmt = db()->prepare('SELECT id, email, password_hash, email_verified_at FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    $passwordOk = password_verify($password, $user ? $user['password_hash'] : $dummyHash);
    if (!$user || !$passwordOk) {
        return auth_fail(401, 'Wrong email or password.');
    }
    if ($user['email_verified_at'] === null) {
        return auth_fail(403, 'Please confirm your email first.');
    }

    return auth_ok(['user' => ['id' => (int) $user['id'], 'email' => $user['email']]]);
}

// Always answers the same, so nobody can find out which emails are registered.
function request_password_reset(string $email): array {
    $email = normalize_email($email);

    if ($email !== '' && strlen($email) <= 190) {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $userId = (int) $user['id'];
            $stmt = $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?'); // only the last link works
            $stmt->execute([$userId]);

            $token = make_token();
            $stmt = $pdo->prepare(
                'INSERT INTO password_resets (user_id, token_hash, expires_at, created_at) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$userId, $token['hash'], utc_in(RESET_LIFETIME), utc_now()]);

            if (!send_password_reset_email($email, $token['token'])) {
                error_log('MovieMatch: could not send the password reset email to user ' . $userId);
            }
        }
    }

    return auth_ok(['message' => 'If the email exists, we sent you a link.']);
}

function reset_password(string $token, string $password): array {
    $invalidLink = auth_fail(400, 'The link is not valid or it expired.');

    $passwordError = password_error($password);
    if ($passwordError !== null) {
        return auth_fail(400, $passwordError);
    }
    if ($token === '') {
        return $invalidLink;
    }

    $pdo = db();
    $stmt = $pdo->prepare('SELECT user_id FROM password_resets WHERE token_hash = ? AND expires_at > ?');
    $stmt->execute([hash('sha256', $token), utc_now()]);
    $row = $stmt->fetch();
    if (!$row) {
        return $invalidLink;
    }

    $userId = (int) $row['user_id'];
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
    $stmt = $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?');
    $stmt->execute([$userId]);
    $pdo->commit();

    return auth_ok(['message' => 'Your password was changed.']);
}
