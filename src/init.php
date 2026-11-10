<?php

$configFile = __DIR__ . '/../config/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('Missing config/config.php. Copy config/config.sample.php to config/config.php and fill in your data.');
}

require $configFile;
require __DIR__ . '/helpers.php';
require __DIR__ . '/mail.php';
require __DIR__ . '/auth.php';
