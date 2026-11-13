<?php

/*
Sends a plain text email.
- APP_ENV = 'local': the email is NOT sent. It is saved in mail_outbox.log (project root) for testing.
*/
function send_mail(string $to, string $subject, string $body): bool {
    if (APP_ENV === 'local') {
        $entry = '=== ' . utc_now() . " UTC ===\nTo: $to\nSubject: $subject\n\n$body\n\n";
        return file_put_contents(dirname(__DIR__) . '/mail_outbox.log', $entry, FILE_APPEND | LOCK_EX) !== false;
    }

    // Temporary SMTP errors (4xx, for example iCloud "454 auth service unavailable") are retried.
    $maxAttempts = 3;
    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        try {
            smtp_send($to, $subject, $body);
            return true;
        } catch (SmtpTemporaryError $e) {
            error_log("MovieMatch mail temporary error (attempt $attempt of $maxAttempts): " . $e->getMessage());
            if ($attempt < $maxAttempts) {
                sleep(3);
            }
        } catch (Throwable $e) {
            // The log has the SMTP answer, never the password.
            error_log('MovieMatch mail error: ' . $e->getMessage());
            return false;
        }
    }
    return false;
}

// The SMTP server said "try again later" (a 4xx answer).
class SmtpTemporaryError extends RuntimeException {
}

// Reads one SMTP answer (it can have many lines) and checks the code. Returns the answer text.
function smtp_expect($socket, array $okCodes): string {
    $text = '';
    do {
        $line = fgets($socket, 1024);
        if ($line === false) {
            throw new RuntimeException('SMTP connection closed or timed out.');
        }
        $text .= $line;
    } while (isset($line[3]) && $line[3] === '-'); // "250-..." means more lines are coming

    $code = (int) substr($text, 0, 3);
    if ($code >= 400 && $code < 500) {
        throw new SmtpTemporaryError('Temporary SMTP answer: ' . trim($text));
    }
    if (!in_array($code, $okCodes, true)) {
        throw new RuntimeException('Unexpected SMTP answer: ' . trim($text));
    }
    return $text;
}

// Sends one command and checks the answer.
function smtp_command($socket, string $command, array $okCodes): string {
    fwrite($socket, $command . "\r\n");
    return smtp_expect($socket, $okCodes);
}

// Talks to the SMTP server: connect, STARTTLS, login, send. Throws an exception on any error.
function smtp_send(string $to, string $subject, string $body): void {
    $context = stream_context_create(['ssl' => [
        'verify_peer'      => true,
        'verify_peer_name' => true,
        'peer_name'        => MAIL_HOST,
    ]]);

    $socket = @stream_socket_client(
        'tcp://' . MAIL_HOST . ':' . MAIL_PORT, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context
    );
    if (!$socket) {
        throw new RuntimeException("Could not connect to the SMTP server: $errstr ($errno)");
    }
    stream_set_timeout($socket, 15);

    try {
        $clientName = parse_url(APP_URL, PHP_URL_HOST) ?: 'localhost';

        smtp_expect($socket, [220]);
        smtp_command($socket, 'EHLO ' . $clientName, [250]);

        smtp_command($socket, 'STARTTLS', [220]);
        // "@" hides the PHP warning, so it cannot get mixed with the JSON answer. The error is logged below.
        if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('Could not start the secure connection (STARTTLS).');
        }
        smtp_command($socket, 'EHLO ' . $clientName, [250]); // ask again after TLS

        // AUTH PLAIN: user and password in one step.
        smtp_command($socket, 'AUTH PLAIN ' . base64_encode("\0" . MAIL_USER . "\0" . MAIL_PASS), [235]);

        smtp_command($socket, 'MAIL FROM:<' . MAIL_FROM . '>', [250]);
        smtp_command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
        smtp_command($socket, 'DATA', [354]);

        // Never allow line breaks inside header values
        $to = str_replace(["\r", "\n"], '', $to);
        $subject = str_replace(["\r", "\n"], '', $subject);
        $fromDomain = substr(MAIL_FROM, strrpos(MAIL_FROM, '@') + 1);

        $message = 'From: MovieMatch <' . MAIL_FROM . ">\r\n"
                 . 'To: <' . $to . ">\r\n"
                 . 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n"
                 . 'Date: ' . date('r') . "\r\n"
                 . 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $fromDomain . ">\r\n"
                 . "MIME-Version: 1.0\r\n"
                 . "Content-Type: text/plain; charset=UTF-8\r\n"
                 . "Content-Transfer-Encoding: base64\r\n"
                 . "\r\n"
                 . chunk_split(base64_encode($body), 76, "\r\n");

        fwrite($socket, $message . ".\r\n"); // a line with only a dot ends the message
        smtp_expect($socket, [250]);

        smtp_command($socket, 'QUIT', [221]);
    } finally {
        fclose($socket);
    }
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
    $link = app_link('reset-password.php?token=' . urlencode($token));
    $body = "We received a request to change your MovieMatch password.\n\n"
          . "Open this link to choose a new password (it is valid for 1 hour):\n\n"
          . "$link\n\n"
          . "If you did not ask for this, you can ignore this email. Your password will not change.\n";
    return send_mail($email, 'Reset your MovieMatch password', $body);
}