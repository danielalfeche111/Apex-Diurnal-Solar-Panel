<?php

// Development server: php -S 127.0.0.1:8000 -t public public/router.php
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
$file = realpath(__DIR__.$path);
if ($file !== false && str_starts_with($file, __DIR__.DIRECTORY_SEPARATOR)
    && is_file($file) && !str_ends_with(strtolower($file), '.php')) {
    return false;
}

require __DIR__.'/index.php';
