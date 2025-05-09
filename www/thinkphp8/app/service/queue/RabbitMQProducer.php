<?php

declare(strict_types=1);

namespace app\service\queue;

use think\facade\Log;
use think\facade\Queue;
use think\helper\Str;

/**
 * RabbitMQ生产者服务
 *
 * 该类封装了RabbitMQ消息队列的生产者功能，提供了发送消息的方法
 *
 * @package app\service\queue
 */
class RabbitMQProducer
{
    /**
     * 默认队列名称
     *
     * @var string
     */
    protected $defaultQueue = 'default';

    /**
     * 默认连接名称
     *
     * @var string
     */
    protected $connection = 'rabbitmq';

    /**
     * 是否启用发布者确认模式
     *
     * @var bool
     */
    protected $publisherConfirms = false;

    /**
     * 是否等待发布者确认
     *
     * @var bool
     */
    protected $waitForConfirm = false;

    /**
     * 发布者确认超时时间（秒）
     *
     * @var float
     */
    protected $confirmTimeout = 5.0;

    /**
     * 构造函数
     *
     * @param string $queue 队列名称
     * @param string $connection 连接名称
     * @param bool $publisherConfirms 是否启用发布者确认模式
     * @param bool $waitForConfirm 是否等待发布者确认
     * @param float $confirmTimeout 发布者确认超时时间（秒）
     */
    public function __construct(?string $queue = null, ?string $connection = null, bool $publisherConfirms = false, bool $waitForConfirm = false, float $confirmTimeout = 5.0)
    {
        if ($queue) {
            $this->defaultQueue = $queue;
        }

        if ($connection) {
            $this->connection = $connection;
        }

        $this->publisherConfirms = $publisherConfirms;
        $this->waitForConfirm    = $waitForConfirm;
        $this->confirmTimeout    = $confirmTimeout;
    }

    /**
     * 发送消息到队列
     *
     * @param string $job 任务处理类
     * @param array $data 任务数据
     * @param string $queue 队列名称
     * @return mixed
     */
    public function send(string $job, array $data = [], ?string $queue = null)
    {
        $queue = $queue ?: $this->defaultQueue;

        // 添加消息ID和时间戳
        $data['message_id'] = $data['message_id'] ?? $this->generateMessageId();
        $data['created_at'] = $data['created_at'] ?? date('Y-m-d H:i:s');

        try {
            // 准备发送选项
            $options = [];

            // 如果启用了发布者确认模式，添加相关选项
            if ($this->publisherConfirms) {
                $options['wait_for_confirm'] = $this->waitForConfirm;
                $options['confirm_timeout']  = $this->confirmTimeout;
            }

            // 使用队列门面发送消息
            $queueConnection = Queue::connection($this->connection);

            // 如果是我们修改过的RabbitMQ连接器，可以传递选项
            if (method_exists($queueConnection, 'pushWithOptions')) {
                $result = $queueConnection->pushWithOptions($job, $data, $queue, $options);
            } else {
                // 否则使用标准方法
                $result = $queueConnection->push($job, $data, $queue);
            }

            Log::info('消息已发送到队列 - 队列: {queue}, 任务: {job}, 消息ID: {message_id}, 结果: {result}, 发布者确认: {confirms}', [
                'queue'      => $queue,
                'job'        => $job,
                'message_id' => $data['message_id'],
                'result'     => $result,
                'confirms'   => $this->publisherConfirms ? 'enabled' : 'disabled'
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('发送消息到队列失败 - 队列: {queue}, 任务: {job}, 消息ID: {message_id}, 错误: {error}, 跟踪: {trace}', [
                'queue'      => $queue,
                'job'        => $job,
                'message_id' => $data['message_id'],
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * 发送延迟消息到队列
     *
     * @param int $delay 延迟时间（秒）
     * @param string $job 任务处理类
     * @param array $data 任务数据
     * @param string $queue 队列名称
     * @return mixed
     */
    public function sendLater(int $delay, string $job, array $data = [], ?string $queue = null)
    {
        $queue = $queue ?: $this->defaultQueue;

        // 添加消息ID和时间戳
        $data['message_id'] = $data['message_id'] ?? $this->generateMessageId();
        $data['created_at'] = $data['created_at'] ?? date('Y-m-d H:i:s');

        try {
            // 准备发送选项
            $options = [];

            // 如果启用了发布者确认模式，添加相关选项
            if ($this->publisherConfirms) {
                $options['wait_for_confirm'] = $this->waitForConfirm;
                $options['confirm_timeout']  = $this->confirmTimeout;
            }

            // 使用队列门面发送延迟消息
            $queueConnection = Queue::connection($this->connection);

            // 如果是我们修改过的RabbitMQ连接器，可以传递选项
            if (method_exists($queueConnection, 'laterWithOptions')) {
                $result = $queueConnection->laterWithOptions($delay, $job, $data, $queue, $options);
            } else {
                // 否则使用标准方法
                $result = $queueConnection->later($delay, $job, $data, $queue);
            }

            Log::info('延迟消息已发送到队列 - 队列: {queue}, 任务: {job}, 延迟: {delay}秒, 消息ID: {message_id}, 结果: {result}, 发布者确认: {confirms}', [
                'queue'      => $queue,
                'job'        => $job,
                'delay'      => $delay,
                'message_id' => $data['message_id'],
                'result'     => $result,
                'confirms'   => $this->publisherConfirms ? 'enabled' : 'disabled'
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('发送延迟消息到队列失败 - 队列: {queue}, 任务: {job}, 延迟: {delay}秒, 消息ID: {message_id}, 错误: {error}, 跟踪: {trace}', [
                'queue'      => $queue,
                'job'        => $job,
                'delay'      => $delay,
                'message_id' => $data['message_id'],
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * 批量发送消息到队列
     *
     * @param array $messages 消息数组，每个元素包含job和data
     * @param string $queue 队列名称
     * @return array
     */
    public function batchSend(array $messages, ?string $queue = null)
    {
        $queue   = $queue ?: $this->defaultQueue;
        $results = [];

        foreach ($messages as $index => $message) {
            if (!isset($message['job'])) {
                Log::warning('批量发送消息时缺少job字段 - 索引: {index}, 消息: {message}', [
                    'index'   => $index,
                    'message' => $message
                ]);
                continue;
            }

            $job  = $message['job'];
            $data = $message['data'] ?? [];

            try {
                $results[$index] = $this->send($job, $data, $queue);
            } catch (\Exception $e) {
                $results[$index] = false;
                Log::error('批量发送消息失败 - 索引: {index}, 任务: {job}, 错误: {error}', [
                    'index' => $index,
                    'job'   => $job,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $results;
    }

    /**
     * 生成唯一的消息ID
     *
     * @return string
     */
    protected function generateMessageId(): string
    {
        return 'msg_' . Str::random(16) . '_' . time();
    }

    /**
     * 获取队列长度
     *
     * @param string $queue 队列名称
     * @return int
     */
    public function getQueueSize(?string $queue = null): int
    {
        $queue = $queue ?: $this->defaultQueue;

        try {
            return Queue::connection($this->connection)->size($queue);
        } catch (\Exception $e) {
            Log::error('获取队列长度失败 - 队列: {queue}, 错误: {error}', [
                'queue' => $queue,
                'error' => $e->getMessage()
            ]);

            return 0;
        }
    }
}
