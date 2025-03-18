<?php

declare(strict_types=1);

namespace think\queue;

use think\queue\Job;

/**
 * KafkaJob 类，用于处理 Kafka 队列中的任务
 */
class KafkaJob extends Job
{
    // 定义类属性，用于存储消息、队列、容器、连接和连接名称
    protected $message;
    protected $queue;
    protected $container;
    protected $connection;
    protected $connectionName;

    // 构造函数，初始化 KafkaJob 对象
    public function __construct($container, $connection, $message, $connectionName, $queue)
    {
        // 初始化类属性
        $this->container = $container;
        $this->connection = $connection;
        $this->message = $message;
        $this->connectionName = $connectionName;
        $this->queue = $queue;
        $this->app = $container; // 兼容旧版本，将容器赋值给 $app 属性
    }


    // 获取消息的原始内容
    public function getRawBody(): string
    {
        // 返回 $this->message 对象的 payload 属性，该属性存储了消息的原始内容
        return $this->message->payload;
    }

    // 删除任务，并提交消息偏移量
    public function delete()
    {
        // 调用父类的 delete 方法
        parent::delete();
        // 显式提交消息偏移量 - 使用同步提交确保偏移量被正确提交
        if ($this->message instanceof \RdKafka\Message) {
            try {
                // 记录处理开始时间，用于计算处理时间
                $startTime = microtime(true);

                // 改用同步提交替代异步提交，确保消息被确认
                $this->connection->consumer->commit($this->message);

                // 计算处理时间（秒）
                $processingTime = microtime(true) - $startTime;

                // 记录提交成功日志
                if (class_exists('\think\facade\Log')) {
                    \think\facade\Log::debug('Kafka message committed successfully-{topic},{partition},{offset},{time}', [
                        'topic' => $this->message->topic_name,
                        'partition' => $this->message->partition,
                        'offset' => $this->message->offset,
                        'time' => round($processingTime, 4)
                    ]);
                }

                // 解析消息内容
                $payload = json_decode($this->message->payload, true);
                $messageId = $payload['message_id'] ?? uniqid('msg_', true);

                // 使用新的幂等性检查和指标收集功能
                if (method_exists($this->connection, 'markMessageAsProcessed')) {
                    $this->connection->markMessageAsProcessed(
                        $messageId,
                        $this->message->topic_name,
                        $processingTime
                    );
                }
            } catch (\Exception $e) {
                // 记录提交失败日志
                if (class_exists('\think\facade\Log')) {
                    \think\facade\Log::error('Failed to commit Kafka message-{topic},{partition},{offset},{error}', [
                        'error' => $e->getMessage(),
                        'topic' => $this->message->topic_name ?? 'unknown',
                        'partition' => $this->message->partition ?? -1,
                        'offset' => $this->message->offset ?? -1
                    ]);
                }

                // 解析消息内容
                $payload = json_decode($this->message->payload, true);
                $messageId = $payload['message_id'] ?? uniqid('msg_', true);

                // 使用新的死信队列和指标收集功能
                if (method_exists($this->connection, 'markMessageAsFailed')) {
                    $this->connection->markMessageAsFailed(
                        $messageId,
                        $this->message->topic_name,
                        $payload,
                        $e->getMessage(),
                        0.0
                    );
                }
            }
        }
    }
    /**
     * 释放当前任务到队列中，如果任务重试次数超过最大值，则放入死信队列
     * 
     * @param int $delay 延迟时间（秒），默认为0，如果为0则根据重试次数计算延迟时间
     */
    public function release($delay = 0)
    {
        parent::release($delay);

        // 解析当前payload
        $payload = json_decode($this->message->payload, true);

        // 增加尝试次数
        $payload['attempts'] = $this->attempts() + 1;

        // 获取最大重试次数，默认为3次
        $maxTries = $payload['maxTries'] ?? 3;

        // 如果超过最大重试次数，则放入死信队列
        if ($payload['attempts'] > $maxTries) {
            // 将任务放入死信队列（使用原队列名称加上_dead_letter后缀）
            $deadLetterQueue = $this->queue . '_dead_letter';
            $this->connection->pushRaw(
                json_encode($payload),
                $deadLetterQueue
            );

            // 记录日志
            if (class_exists('\think\facade\Log')) {
                \think\facade\Log::warning('Job exceeded maximum retry attempts and moved to dead letter queue: {job_info}', [
                    'job_info' => [
                        'job' => $payload,
                        'queue' => $this->queue,
                        'dead_letter_queue' => $deadLetterQueue,
                        'attempts' => $payload['attempts'],
                        'max_tries' => $maxTries
                    ]
                ]);
            }

            // 更新失败指标
            $this->updateMetrics('failed');
        } else {
            // 未超过最大重试次数，继续放回原队列重试
            // 计算延迟时间，使用指数退避策略
            if ($delay == 0) {
                // 如果没有指定延迟，则根据尝试次数计算延迟时间
                // 指数退避策略：尝试次数越多，等待时间越长
                $delay = min(900, pow(2, $payload['attempts']) * 10); // 最大延迟15分钟
            }

            // 如果需要延迟执行，使用延迟队列
            if ($delay > 0) {
                $delayQueue = $this->queue . '_delayed';
                $payload['available_at'] = time() + $delay; // 记录任务可执行的时间
                $payload['original_queue'] = $this->queue; // 记录原始队列名称

                $this->connection->pushRaw(
                    json_encode($payload),
                    $delayQueue
                );

                // 记录日志
                if (class_exists('\think\facade\Log')) {
                    \think\facade\Log::info('Job released with delay: {queue}, delay: {delay}, delay_queue: {delay_queue}, available_at: {available_at}', [
                        'job' => $payload,
                        'queue' => $this->queue,
                        'delay_queue' => $delayQueue,
                        'delay' => $delay,
                        'available_at' => $payload['available_at']
                    ]);
                }
            } else {
                // 无延迟，直接放回原队列
                $this->connection->pushRaw(
                    json_encode($payload),
                    $this->queue
                );
            }
        }
    }

    public function attempts(): int
    {
        // 从消息payload中获取尝试次数，如果不存在则返回1（首次尝试）
        $payload = json_decode($this->getRawBody(), true);
        return $payload['attempts'] ?? 1;
    }

    public function getJobId()
    {
        // 优先使用消息ID作为任务ID，如果不存在则使用offset
        $payload = json_decode($this->getRawBody(), true);
        return $payload['message_id'] ?? $this->message->offset;
    }

    /**
     * 更新队列处理指标
     * @param string $status 处理状态：success或failed
     */
    protected function updateMetrics(string $status)
    {
        try {
            // 从缓存中获取当前指标数据
            $metrics = \think\facade\Cache::get('queue_metrics', []);

            // 初始化当前队列的指标数据
            if (!isset($metrics[$this->queue])) {
                $metrics[$this->queue] = [
                    'success' => 0,
                    'failed' => 0,
                    'last_processed_at' => 0
                ];
            }

            // 更新指标
            $metrics[$this->queue][$status]++;
            $metrics[$this->queue]['last_processed_at'] = time();

            // 保存更新后的指标数据到缓存
            \think\facade\Cache::set('queue_metrics', $metrics);
        } catch (\Exception $e) {
            // 记录错误日志但不中断处理流程
            if (class_exists('\think\facade\Log')) {
                \think\facade\Log::error('Failed to update queue metrics: {message}', ['message' => $e->getMessage()]);
            }
        }
    }
    /**
     * 标记任务为失败
     * 
     * @return void
     */
    public function fail()
    {
        parent::markAsFailed();

        // 记录日志
        if (class_exists('\think\facade\Log')) {
            \think\facade\Log::error('Job marked as failed: topic: {topic}, partition: {partition}, offset: {offset}', [
                'topic' => $this->message->topic_name ?? 'unknown',
                'partition' => $this->message->partition ?? -1,
                'offset' => $this->message->offset ?? -1,
                'payload' => json_decode($this->getRawBody(), true)
            ]);
        }

        // 更新失败指标
        if (method_exists($this->connection, 'updateMetrics')) {
            $this->connection->updateMetrics('failed');
        }

        // 同时更新本地缓存中的失败指标
        $this->updateMetrics('failed');

        // 提交消息偏移量，确认已处理（即使失败也需要确认，避免消息被重复消费）
        if ($this->message instanceof \RdKafka\Message) {
            try {
                $this->connection->consumer->commit($this->message);
            } catch (\Exception $e) {
                // 记录提交失败日志
                if (class_exists('\think\facade\Log')) {
                    \think\facade\Log::error('Failed to commit Kafka message after marking as failed: {error}', [
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
    }
}
