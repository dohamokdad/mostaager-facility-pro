<?php
// PHPUnit bootstrap for Mostaager Facility Pro.

if (defined('ABSPATH')) {
    return;
}

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// If the WordPress test environment is available, load it.
if (file_exists(__DIR__ . '/wp-tests-config.php')) {
    require_once __DIR__ . '/wp-tests-config.php';
}

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

if (file_exists(ABSPATH . 'mostaager-facility-pro.php')) {
    require_once ABSPATH . 'mostaager-facility-pro.php';
}
