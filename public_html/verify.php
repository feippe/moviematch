<?php

/*
Link from the verification email: /verify.php?token=...
It confirms the account and sends the user to the login page: 
   /login.php?verified=1  (confirmed)
   /login.php?verified=0  (invalid or expired link)
*/

require __DIR__ . '/../src/init.php';

$token = (isset($_GET['token']) && is_string($_GET['token'])) ? $_GET['token'] : '';

try {
    $verified = verify_email($token);
} catch (Throwable $e) {
    error_log('MovieMatch error: ' . $e->getMessage());
    $verified = false;
}

header('Location: /login.php?verified=' . ($verified ? '1' : '0'));
exit;
