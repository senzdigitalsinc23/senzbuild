<?php
// Simple router for PHP built-in server
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$script = __DIR__ . '/index.php';

if (is_file(__DIR__ . $uri) && $uri !== '/') {
    return false; // Let PHP serve the file directly
}

require_once $script;
