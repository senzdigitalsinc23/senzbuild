<?php
return [
    'name' => $_ENV['APP_NAME'] ?? getenv('APP_NAME') ?: 'API Project',
    'env'  => $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'local',
    'debug' => $_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?: 'true',
    'url'  => $_ENV['APP_URL'] ?? getenv('APP_URL') ?: 'http://localhost/api-project',
    'display_errors' => $_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?: 'true',
    'log_path' => __DIR__ . '/../storage/logs'
];

