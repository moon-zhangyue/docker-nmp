<?php

return [
    'default' => [
        'host' => 'redis',
        'port' => 6379,
        'password' => null,
        'database' => 0,
        'timeout' => 2.0,
        'prefix' => '',
    ],
    
    // 可以配置多个Redis连接
    'cache' => [
        'host' => 'redis',
        'port' => 6379,
        'password' => null,
        'database' => 1,
        'timeout' => 2.0,
        'prefix' => 'cache:',
    ],
]; 