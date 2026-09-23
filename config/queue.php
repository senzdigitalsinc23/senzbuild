<?php

return [
    'default' => $_ENV['QUEUE_CONNECTION'] ?? 'database',

    'connections' => [
        'database' => [
            'driver'  => 'database',
            'table'   => 'queue_jobs',
            'queue'   => $_ENV['QUEUE_NAME'] ?? 'default',
            'retry_after' => (int)($_ENV['QUEUE_RETRY_AFTER'] ?? 90),
            'min_attempts' => (int)($_ENV['QUEUE_MIN_ATTEMPTS'] ?? 3),
            'max_attempts' => (int)($_ENV['QUEUE_MAX_ATTEMPTS'] ?? 10),
            'backoff'  => $_ENV['QUEUE_BACKOFF'] ?? 'exponential',
        ],
        'sync' => [
            'driver' => 'sync',
        ],
    ],

    'workers' => [
        'timeout' => (int)($_ENV['QUEUE_WORKER_TIMEOUT'] ?? 60),
        'sleep'   => (int)($_ENV['QUEUE_WORKER_SLEEP'] ?? 3),
        'max_jobs' => (int)($_ENV['QUEUE_WORKER_MAX_JOBS'] ?? 0),
        'memory'  => (int)($_ENV['QUEUE_WORKER_MEMORY'] ?? 128),
    ],

    'failed' => [
        'driver'   => $_ENV['QUEUE_FAILED_DRIVER'] ?? 'database-table',
        'table'    => 'queue_failed_jobs',
        'expire'   => (int)($_ENV['QUEUE_FAILED_EXPIRE'] ?? 86400 * 7),
    ],
];
