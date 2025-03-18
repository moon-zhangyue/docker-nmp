<?php

namespace app\queue\redis;

use support\Db;
use Webman\RedisQueue\Consumer;

class MySmsSend implements Consumer
{
    // 要消费的队列名
    public string $queue = 'send-sms';

    // 连接名，对应 plugin/webman/redis-queue/redis.php 里的连接`
    public string $connection = 'default';

    // 消费
    public function consume($data): void
    {
        $affected = Db::table('message')
            ->where('data', json_encode($data))
            ->update(['consume_time' => date('Y-m-d H:i:s', time())]);

        // 无需反序列化
        var_export($data);
    }

    // 消费失败回调
    public function onConsumeFailure(\Throwable $e, $package): void
    {
        echo "consume failure\n";
        echo $e->getMessage() . "\n";
        // 无需反序列化
        var_export($package);
    }
}