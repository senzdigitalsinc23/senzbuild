<?php




$credentials = [
    'driver' => $_ENV['DB_DRIVER'] ?? getenv('DB_DRIVER') ?: 'mysql',
    'host' => $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost',
    'dbname' => $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'api_project',
    'username' => $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'root',
    'password' => $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: '',
    'charset' => 'utf8mb4',
];

return $credentials;