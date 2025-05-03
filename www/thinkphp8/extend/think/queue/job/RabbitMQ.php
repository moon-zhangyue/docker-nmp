<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2023 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------

namespace think\queue\job;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use think\App;
use think\facade\Log;
use think\queue\connector\RabbitMQ as RabbitMQQueue;
use think\queue\Job;

/**
 * RabbitMQ任务类
 * 
 * 该类实现了ThinkPHP队列任务接口，用于处理RabbitMQ队列中的任务
 * 
 * @package think\queue\job
 */
class RabbitMQ extends Job
{
    /**
     * RabbitMQ队列连接器实例
     *
     * @var RabbitMQQueue
     */
    protected $rabbitmq;

    /**
     * RabbitMQ通道
     *
     * @var AMQPChannel
     */
    protected $channel;

    /**
     * RabbitMQ消息
     *
     * @var AMQPMessage
     */
    protected $message;

    /**
     * 构造函数
     *
     * @param App $app 应用实例
     * @param RabbitMQQueue $rabbitmq RabbitMQ连接器
     * @param AMQPChannel $channel RabbitMQ通道
     * @param AMQPMessage $message RabbitMQ消息
     * @param string $connection 连接名称
     * @param string $queue 队列名称
     */
    public function __construct(App $app, RabbitMQQueue $rabbitmq, AMQPChannel $channel, AMQPMessage $message, $connection, $queue)
    {
        $this->app = $app;
        $this->rabbitmq = $rabbitmq;
        $this->channel = $channel;
        $this->message = $message;
        $this->connection = $connection;
        $this->queue = $queue;
    }

    /**
     * 获取任务ID
     *
     * @return string
     */
    public function getJobId()
    {
        return $this->payload('id') ?? '';
    }

    /**
     * 获取任务尝试次数
     *
     * @return int
     */
    public function attempts()
    {
        return ($this->payload('attempts') ?? 0) + 1;
    }

    /**
     * 获取原始消息内容
     *
     * @return string
     */
    public function getRawBody()
    {
        return $this->message->getBody();
    }

    /**
     * 删除任务（确认消息已处理）
     *
     * @return void
     */
    public function delete()
    {
        parent::delete();

        // 确认消息已处理
        $this->rabbitmq->ack($this->queue, $this);

        Log::info('任务已删除: ' . $this->getJobId());
    }

    /**
     * 重新发布任务
     *
     * @param int $delay 延迟时间（秒）
     * @return void
     */
    public function release($delay = 0)
    {
        parent::release($delay);

        // 重新发布消息
        $this->rabbitmq->release($this->queue, $this, $delay);

        Log::info('任务已重新发布: ' . $this->getJobId() . ', 延迟: ' . $delay . '秒');
    }

    /**
     * 获取RabbitMQ消息实例
     *
     * @return AMQPMessage
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * 获取RabbitMQ通道实例
     *
     * @return AMQPChannel
     */
    public function getChannel()
    {
        return $this->channel;
    }
}
