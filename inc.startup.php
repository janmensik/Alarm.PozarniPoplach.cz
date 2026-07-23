<?php

# composer autoloader
require 'vendor/autoload.php';

use Dotenv\Dotenv;

$root = __DIR__;

// Load main .env if present
if (file_exists($root . '/.env')) {
    $dotenv = Dotenv::createUnsafeImmutable($root);
    $dotenv->load();
}

// If hostname indicates localhost, also load .env_localhost
$serverName = $_SERVER['SERVER_NAME'] ?? '';
$isLocalhost = str_contains($serverName, 'localhost') || (PHP_SAPI === 'cli' && file_exists($root . '/.env.localhost'));

if ($isLocalhost && file_exists($root . '/.env.localhost')) {
    $dotenvLocal = Dotenv::createUnsafeMutable($root, '.env.localhost');
    $dotenvLocal->load();
}

// Security: Disable display_errors in production to prevent leaking sensitive information
if (getenv('DEBUGGING') == 1) {
    ini_set('display_errors', '1');
} else {
    ini_set('display_errors', '0');
}
