<?php

return [
    'default' => [
        'host'       => env('REDIS_HOST', 'redis'),
        'port'       => env('REDIS_PORT', 6379),
        'password'   => env('REDIS_PASSWORD', ''),
        'select'     => env('REDIS_SELECT', 0),
        'timeout'    => env('REDIS_TIMEOUT', 0),
        'persistent' => env('REDIS_PERSISTENT', false),
        'options'    => [
            // 使用常量值替代Redis类常量
            3 => -1, // Redis::OPT_READ_TIMEOUT => -1
        ],
    ],
];
