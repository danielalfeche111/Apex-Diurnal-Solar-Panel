<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

// PHP's built-in server may omit process variables from $_SERVER. Preserve
// explicit deployment/test configuration before reading local defaults.
$processEnvironment = [];
foreach (['APP_ENV', 'APP_DEBUG', 'APP_SECRET', 'DATABASE_URL'] as $key) {
    $value = getenv($key);
    if ($value !== false) {
        $processEnvironment[$key] = $value;
        $_SERVER[$key] = $value;
        $_ENV[$key] = $value;
    }
}

// The tracked example provides local defaults; secrets belong in .env.local.
$dotenv = new Dotenv();
$dotenv->loadEnv(dirname(__DIR__).'/.env.example', 'APP_ENV', 'dev', ['test']);
if (is_file(dirname(__DIR__).'/.env.local')) {
    $dotenv->overload(dirname(__DIR__).'/.env.local');
}

// Process environment takes precedence in containers and test runs.
foreach ($processEnvironment as $key => $value) {
    $_SERVER[$key] = $value;
    $_ENV[$key] = $value;
}
