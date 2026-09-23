<?php




$smsConfig = [
    'sid' => $_ENV['SMS_ID'] ?? getenv('SMS_ID'),
    'token' => $_ENV['TOKEN'] ?? getenv('TOKEN'),
    'from' => $_ENV['SMS_FROM'] ?? getenv('SMS_FROM'),
];

return $smsConfig;