<?php

// .env is loaded in public/index.php before Config::load()

return [
    'app'     => $_ENV['APP_NAME'] ?? '',
    'env'     => $_ENV['APP_ENV'] ?? 'local',
    'debug'   => $_ENV['APP_DEBUG'] ?? 'true',
    'url'     => $_ENV['APP_URL'] ?? 'http://localhost:8000',
    'api_key' => $_ENV['API_KEY'] ?? '',
];
