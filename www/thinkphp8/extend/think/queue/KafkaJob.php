<?php

declare(strict_types=1);

namespace think\queue;

use think\queue\Job;

class KafkaJob extends Job
{
    protected $message;
    protected $queue;
    protected $container;
    protected $connection;
    protected $connectionName;

    public function __construct($container, $connection, $message, $connectionName, $queue)
    {
        $this->container = $container;
        $this->connection = $connection;
        $this->message = $message;
        $this->connectionName = $connectionName;
        $this->queue = $queue;
        $this->app = $container;
    }


    public function getRawBody(): string
    {
        // 返回 $this->message 对象的 payload 属性，该属性存储了消息的原始内容
        return $this->message->payload;
    }

    public function delete()
    {
        parent::delete();
        // 显式提交消息偏移量 - 使用同步提交确保偏移量被正确提交
        if ($this->message instanceof \RdKafka\Message) {
            // 改用同步提交替代异步提交，确保消息被确认
            $this->connection->consumer->commit($this->message);
            // $this->connection->consumer->commitAsync($this->message); // 异步提交
        }
    }

    public function release($delay = 0)
    {
        parent::release($delay);

        $this->connection->pushRaw(
            $this->message->payload,
            $this->queue,
            ['delay' => $delay]
        );
    }

    public function attempts(): int
    {
        return 0;
    }

    public function getJobId()
    {
        // 使用消息的 offset 作为任务 ID
        return $this->message->offset;
    }
}
