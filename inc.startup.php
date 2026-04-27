<?php

# composer autoloader
require 'vendor/autoload.php';

use Dotenv\Dotenv;

$root = __DIR__;

// Load main .env if present
if (file_exists($root . '/.env')) {
    $dotenv = Dotenv::createImmutable($root);
    $dotenv->load();
}

// If hostname indicates localhost, also load .env_localhost
$serverName = $_SERVER['SERVER_NAME'] ?? '';
if (strpos($serverName, 'localhost') !== false && file_exists($root . '/.env.localhost')) {
    $dotenvLocal = Dotenv::createMutable($root, '.env.localhost');
    $dotenvLocal->load();
}

