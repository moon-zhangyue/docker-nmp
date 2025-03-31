<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
return [
    'http'   => [
        // 全局中间件
    ],
    // 路由中间件（并没有起作用，还是需要写在routes中或者使用注解）
    'router' => [
        // 红包相关接口限流中间件
        'api/red-packet/create' => [
            App\Middleware\RateLimitMiddleware::class,
        ],
        'api/red-packet/grab'   => [
            App\Middleware\RateLimitMiddleware::class,
        ],
    ],
    //Telescope中间件
    'grpc'   => [
        FriendsOfHyperf\Telescope\Middleware\TelescopeMiddleware::class,
    ],
];
