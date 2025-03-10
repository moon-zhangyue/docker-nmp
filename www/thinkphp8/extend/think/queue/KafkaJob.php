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
                \think\facade\Log::warning('Job exceeded maximum retry attempts and moved to dead letter queue', [
                    'job' => $payload,
                    'queue' => $this->queue,
                    'dead_letter_queue' => $deadLetterQueue,
                    'attempts' => $payload['attempts'],
                    'max_tries' => $maxTries
                ]);
            }
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
                    \think\facade\Log::info('Job released with delay', [
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
        // 使用消息的 offset 作为任务 ID
        return $this->message->offset;
    }
}
