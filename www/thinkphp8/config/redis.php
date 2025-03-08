<?php

return [
    'default' => [
        'host'       => env('redis.host', 'redis'),
        'port'       => env('redis.port', 6379),
        'password'   => env('redis.password', ''),
        'select'     => env('redis.select', 0),
        'timeout'    => env('redis.timeout', 0),
        'persistent' => false,
        'options'    => [
            \Redis::OPT_READ_TIMEOUT => -1,
        ],
    ],
]; 