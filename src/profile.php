<?php

// the rules for the profile
const MIN_AGE = 18;
const MAX_AGE = 100;
const MAX_NAME_LENGTH = 60;
const MAX_PHOTO_SIZE = 5242880; // 5 mb

// gets the age in years from a birth date like 2000-05-20
function get_age(string $birthDate): int {
    $birth = new DateTime($birthDate);
    $today = new DateTime('today');
    $difference = $birth->diff($today);

    // invert is 1 when the birth date is after today (in the future)
    if ($difference->invert === 1) {
        return 0;
    }

    return $difference->y;
}

// checks that the date looks like 2000-05-20 and is a real day on the calendar
function is_valid_date(string $date): bool {
    if (strlen($date) !== 10) {
        return false;
    }

    $parts = explode('-', $date);
    if (count($parts) !== 3) {
        return false;
    }

    $year = (int) $parts[0];
    $month = (int) $parts[1];
    $day = (int) $parts[2];
    return checkdate($month, $day, $year);
}

// gets the logged in user from the database. returns null if nobody is logged in.
function current_user() {
    start_session();

    // the session only has a user_id if the user logged in
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT u.id, u.email, u.role, u.blocked_at, c.domain AS community,
                p.name, p.birth_date, p.sex, p.photo_path, p.match_mode, p.match_with
         FROM users u
         JOIN communities c ON c.id = u.community_id
         LEFT JOIN profiles p ON p.user_id = u.id
         WHERE u.id = ?'
    );
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    // fetch gives false when the user was not found
    if (!$user) {
        return null;
    }
    return $user;
}

// put this at the top of every api file that needs a logged in user
function require_login(): array {
    $user = current_user();

    if ($user === null) {
        json_response(401, ['success' => false, 'error' => 'Please log in.']);
    }

    // blocked users get kicked out
    if ($user['blocked_at'] !== null) {
        $_SESSION = [];
        session_destroy();
        json_response(403, ['success' => false, 'error' => 'This account is blocked.']);
    }

    return $user;
}

// same as require_login, but only admins can pass (we will use it for the admin panel)
function require_admin(): array {
    $user = require_login();

    if ($user['role'] !== 'admin') {
        json_response(403, ['success' => false, 'error' => 'Not allowed.']);
    }

    return $user;
}

// builds the data we send to the browser for the logged in user
function user_data(array $row): array {
    $profile = null;
    $profileComplete = false;

    // if there is a name, the user already filled the profile
    if ($row['name'] !== null) {
        $profile = [
            'name'       => $row['name'],
            'birth_date' => $row['birth_date'],
            'age'        => get_age($row['birth_date']),
            'sex'        => $row['sex'],
            'photo_path' => $row['photo_path'],
            'match_mode' => $row['match_mode'],
            'match_with' => $row['match_with'],
        ];

        // the profile is only complete when the user also has a photo
        if ($row['photo_path'] !== null) {
            $profileComplete = true;
        }
    }

    return [
        'id'               => (int) $row['id'],
        'email'            => $row['email'],
        'role'             => $row['role'],
        'community'        => $row['community'],
        'profile_complete' => $profileComplete,
        'profile'          => $profile,
    ];
}

// saves the profile. creates it the first time, updates it after that.
function save_profile(int $userId, string $name, string $birthDate, string $sex, string $matchWith): array {
    // check the name
    if ($name === '') {
        return auth_fail(400, 'Please enter your name.');
    }
    if (mb_strlen($name) > MAX_NAME_LENGTH) {
        return auth_fail(400, 'Your name must have ' . MAX_NAME_LENGTH . ' characters or less.');
    }

    // check the birth date
    if ($birthDate === '') {
        return auth_fail(400, 'Please enter your date of birth.');
    }
    if (!is_valid_date($birthDate)) {
        return auth_fail(400, 'Please enter a valid date of birth.');
    }
    if ($birthDate > gmdate('Y-m-d')) {
        return auth_fail(400, 'Your date of birth cannot be in the future.');
    }
    $age = get_age($birthDate);
    if ($age < MIN_AGE) {
        return auth_fail(400, 'You must be at least ' . MIN_AGE . ' years old to use MovieMatch.');
    }
    if ($age > MAX_AGE) {
        return auth_fail(400, 'Please check your date of birth.');
    }

    // check sex and who the user wants to match with
    if ($sex !== 'female' && $sex !== 'male') {
        return auth_fail(400, 'Please choose your sex.');
    }
    if ($matchWith !== 'female' && $matchWith !== 'male' && $matchWith !== 'any') {
        return auth_fail(400, 'Please choose who you want to match with.');
    }

    $pdo = db();
    $now = utc_now();

    // look if this user already has a profile
    $stmt = $pdo->prepare('SELECT user_id FROM profiles WHERE user_id = ?');
    $stmt->execute([$userId]);

    if ($stmt->fetch()) {
        // profile exists, so we update it
        $stmt = $pdo->prepare(
            'UPDATE profiles SET name = ?, birth_date = ?, sex = ?, match_with = ?, updated_at = ? WHERE user_id = ?'
        );
        $stmt->execute([$name, $birthDate, $sex, $matchWith, $now, $userId]);
    } else {
        // no profile yet, so we create it
        $stmt = $pdo->prepare(
            'INSERT INTO profiles (user_id, name, birth_date, sex, match_with, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $name, $birthDate, $sex, $matchWith, $now, $now]);
    }

    return auth_ok(['message' => 'Profile saved.']);
}

// saves the photo of the user. the profile must exist before.
// $file is the item from $_FILES, for example $_FILES['photo']
function save_photo(int $userId, array $file): array {
    // php gives an error code with every file. UPLOAD_ERR_OK means all is fine.
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return auth_fail(400, 'Please choose a photo.');
    }
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        return auth_fail(400, 'The photo is too big. The maximum is 5 MB.');
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return auth_fail(400, 'The upload failed. Please try again.');
    }

    // check the size and that the file really came from an upload
    if ($file['size'] > MAX_PHOTO_SIZE) {
        return auth_fail(400, 'The photo is too big. The maximum is 5 MB.');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return auth_fail(400, 'The upload failed. Please try again.');
    }

    // look inside the file to see what it really is (we do not trust the file name)
    $info = getimagesize($file['tmp_name']);
    if ($info === false) {
        return auth_fail(400, 'The file is not an image.');
    }

    // we choose the file extension ourselves, from the real type
    $extension = '';
    if ($info['mime'] === 'image/jpeg') {
        $extension = 'jpg';
    } elseif ($info['mime'] === 'image/png') {
        $extension = 'png';
    } elseif ($info['mime'] === 'image/webp') {
        $extension = 'webp';
    }
    if ($extension === '') {
        return auth_fail(400, 'Please use a JPG, PNG or WEBP photo.');
    }

    // the user needs a profile first. we also get the old photo here.
    $pdo = db();
    $stmt = $pdo->prepare('SELECT photo_path FROM profiles WHERE user_id = ?');
    $stmt->execute([$userId]);
    $profile = $stmt->fetch();
    if (!$profile) {
        return auth_fail(400, 'Please save your profile first.');
    }

    // random file name, so nobody can guess it
    $folder = dirname(__DIR__) . '/public_html/uploads/photos/';
    $fileName = bin2hex(random_bytes(16)) . '.' . $extension;

    if (!move_uploaded_file($file['tmp_name'], $folder . $fileName)) {
        error_log('MovieMatch: could not save the photo in ' . $folder);
        return auth_fail(500, 'We could not save the photo. Please try again later.');
    }

    // save the photo path in the database
    $photoPath = '/uploads/photos/' . $fileName;
    $stmt = $pdo->prepare('UPDATE profiles SET photo_path = ?, updated_at = ? WHERE user_id = ?');
    $stmt->execute([$photoPath, utc_now(), $userId]);

    // delete the old photo, if there was one
    if ($profile['photo_path'] !== null) {
        $oldFile = $folder . basename($profile['photo_path']);
        if (is_file($oldFile)) {
            unlink($oldFile);
        }
    }

    return auth_ok(['photo_path' => $photoPath]);
}