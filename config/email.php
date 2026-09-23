<?php




$emailConfig = [
    'host' => $_ENV['MAIL_HOST'] ?? getenv('MAIL_HOST'),
    'username' => $_ENV['MAIL_USER'] ?? getenv('MAIL_USER'),
    'password' => $_ENV['MAIL_PASS'] ?? getenv('MAIL_PASS'),
    'from' => $_ENV['MAIL_FROM'] ?? getenv('MAIL_FROM'),
    'name' => $_ENV['MAIL_NAME'] ?? getenv('MAIL_NAME'),
    'port'  => $_ENV['MAIL_PORT'] ?? getenv('MAIL_PORT'),
    'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? getenv('MAIL_ENCRYPTION'),
];

return $emailConfig;