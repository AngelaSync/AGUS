<?php
/**
 * Router script for PHP's built-in server (used by `php agus serve`).
 * Real files in public/ are served as-is; everything else goes to index.php.
 */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = __DIR__ . $path;

if ($path !== '/' && is_file($file) && realpath($file) !== __FILE__) {
    return false;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/index.php';
