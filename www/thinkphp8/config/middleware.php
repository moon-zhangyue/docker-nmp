<?php
// 中间件配置
return [
    // 全局中间件定义
    'alias' => [
        'cors' => \app\middleware\Cors::class,
        'jwt_auth' => \app\middleware\JwtAuth::class
    ],
    
    // 全局中间件
    'global' => [
        'cors'
    ],
    
    // 分组中间件定义
    'groups' => [
        // 后台中间件
        'admin' => [
            'auth'
        ],
    ],
    // 优先级设置，此数组中的中间件会按照数组中的顺序优先执行
    'priority' => [],
];
