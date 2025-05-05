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
     * 构造函数
     *
     * @param string $queue 队列名称
     * @param string $connection 连接名称
     */
    public function __construct(?string $queue = null, ?string $connection = null)
    {
        if ($queue) {
            $this->defaultQueue = $queue;
        }

        if ($connection) {
            $this->connection = $connection;
        }
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
            // 使用队列门面发送消息
            $result = Queue::connection($this->connection)->push($job, $data, $queue);

            Log::info('消息已发送到队列 - 队列: {queue}, 任务: {job}, 消息ID: {message_id} , 结果: {result}', [
                'queue'      => $queue,
                'job'        => $job,
                'message_id' => $data['message_id'],
                'result'     => $result
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
            // 使用队列门面发送延迟消息
            $result = Queue::connection($this->connection)
                ->later($delay, $job, $data, $queue);

            Log::info('延迟消息已发送到队列 - 队列: {queue}, 任务: {job}, 延迟: {delay}秒, 消息ID: {message_id}, 结果: {result}', [
                'queue'      => $queue,
                'job'        => $job,
                'delay'      => $delay,
                'message_id' => $data['message_id'],
                'result'     => $result
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
