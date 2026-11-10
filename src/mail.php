<?php
/*
Sends a plain text email.
APP_ENV = 'local': the email is NOT sent. It is saved in mail_outbox.log (project root)
*/
function send_mail(string $to, string $subject, string $body): bool {
    if (APP_ENV === 'local') {
        $entry = '=== ' . utc_now() . " UTC ===\nTo: $to\nSubject: $subject\n\n$body\n\n";
        return file_put_contents(dirname(__DIR__) . '/mail_outbox.log', $entry, FILE_APPEND | LOCK_EX) !== false;
    }

    $headers = [
        'From: MovieMatch <' . MAIL_FROM . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
    ];
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    return mail($to, $encodedSubject, $body, implode("\r\n", $headers));
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
