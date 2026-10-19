<?php
declare(strict_types=1);

// Composer entry point. --static-only verifies preservation without MySQL.
require __DIR__ . '/preservation.php';
if (!in_array('--static-only', $argv ?? [], true)) {
    require __DIR__ . '/http_regression.php';
}
