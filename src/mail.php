<?php

/*
Sends a plain text email.
- APP_ENV = 'local': the email is NOT sent. It is saved in mail_outbox.log (project root),
- Any other value: it is sent with the Resend email service (HTTPS API) using RESEND_API_KEY and MAIL_FROM from config.php.
*/
function send_mail(string $to, string $subject, string $body): bool {
    if (APP_ENV === 'local') {
        $entry = '=== ' . utc_now() . " UTC ===\nTo: $to\nSubject: $subject\n\n$body\n\n";
        return file_put_contents(dirname(__DIR__) . '/mail_outbox.log', $entry, FILE_APPEND | LOCK_EX) !== false;
    }

    // Temporary problems (network error, 429 or 5xx) are retried. Other errors are not.
    $maxAttempts = 3;
    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        $result = resend_send($to, $subject, $body);
        if ($result['ok']) {
            return true;
        }
        // The log has the answer from Resend, never the API key.
        error_log("MovieMatch mail error (attempt $attempt of $maxAttempts): " . $result['error']);
        if (!$result['retry']) {
            return false;
        }
        if ($attempt < $maxAttempts) {
            sleep(2);
        }
    }
    return false;
}

/*
Sends one email with the Resend API: POST https://api.resend.com/emails
Returns ['ok' => bool, 'retry' => bool, 'error' => string].
*/
function resend_send(string $to, string $subject, string $body): array {
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'retry' => false, 'error' => 'The PHP curl extension is not enabled.'];
    }

    // Never allow line breaks inside header values
    $to = str_replace(["\r", "\n"], '', $to);
    $subject = str_replace(["\r", "\n"], '', $subject);

    $payload = json_encode([
        'from'    => 'MovieMatch <' . MAIL_FROM . '>',
        'to'      => [$to],
        'subject' => $subject,
        'text'    => $body,
    ]);

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . RESEND_API_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 20,
    ]);
    $answer = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($answer === false) {
        return ['ok' => false, 'retry' => true, 'error' => 'Could not reach Resend: ' . $curlError];
    }
    if ($status >= 200 && $status < 300) {
        return ['ok' => true, 'retry' => false, 'error' => ''];
    }
    return [
        'ok'    => false,
        'retry' => ($status === 429 || $status >= 500),
        'error' => "Resend answered HTTP $status: " . trim((string) $answer),
    ];
}

function app_link(string $path): string {
    return rtrim(APP_URL, '/') . '/' . ltrim($path, '/');
}

function send_verification_email(string $email, string $token): bool {
    $link = app_link('verify.php?token=' . urlencode($token));
    $body = "Welcome to MovieMatch!\n\n"
          . "Please confirm your email by opening this link (it is valid for 24 hours):\n\n"
          . "$link\n\n"
          . "If you did not create an account, you can ignore this email.\n";
    return send_mail($email, 'Confirm your MovieMatch account', $body);
}

function send_password_reset_email(string $email, string $token): bool {
    $link = app_link('reset-password.html?token=' . urlencode($token));
    $body = "We received a request to change your MovieMatch password.\n\n"
          . "Open this link to choose a new password (it is valid for 1 hour):\n\n"
          . "$link\n\n"
          . "If you did not ask for this, you can ignore this email. Your password will not change.\n";
    return send_mail($email, 'Reset your MovieMatch password', $body);
}