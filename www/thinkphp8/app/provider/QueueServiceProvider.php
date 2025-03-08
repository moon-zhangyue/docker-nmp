<?php
declare (strict_types = 1);

namespace app\provider;

use think\App;
use think\queue\Queue;
use think\queue\connector\Kafka;
use think\Service;

class QueueServiceProvider extends Service
{
    public function register()
    {
        $this->app->bind('queue', Queue::class);

        Queue::extend('kafka', function ($app, $config) {
            return new Kafka($config);
        });
    }

    public function boot()
    {
        // 启动时的一些初始化操作
    }
} 