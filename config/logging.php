<?php

use Monolog\Logger;

return [
    'channels' => [
        'stderr' => [
            'driver' => 'monolog',
            'handler' => \Monolog\Handler\StreamHandler::class,
            'with' => [
                'stream' => 'php://stderr',
            ],
            'level' => 'error',
        ],
    ],

    'default' => env('LOG_CHANNEL', 'stderr'),
];