<?php

declare(strict_types=1);

namespace app\provider;

use think\facade\Queue;
use think\queue\connector\RabbitMQ;
use think\Service;

/**
 * RabbitMQ服务提供者
 *
 * 该类用于注册RabbitMQ队列连接器
 *
 * @package app\provider
 */
class RabbitMQServiceProvider extends Service
{
    /**
     * 注册服务
     *
     * @return void
     */
    public function register()
    {
        // 注册RabbitMQ队列连接器
        Queue::extend('RabbitMQ', function ($config) {
            return new RabbitMQ($config);
        });
    }

    /**
     * 启动服务
     *
     * @return void
     */
    public function boot()
    {
        // 启动时的一些初始化操作
    }
}
