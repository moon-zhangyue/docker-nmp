<?php

return [
    'default' => [
        'host' => 'redis',
        'port' => 6379,
        'password' => null,
        'database' => 1,
        'timeout' => 2.0,
        'prefix' => 'workerman_',
    ],
    
    // 可以配置多个Redis连接
    'cache' => [
        'host' => 'redis',
        'port' => 6379,
        'password' => null,
        'database' => 2,
        'timeout' => 2.0,
        'prefix' => 'cache:',
    ],
]; 