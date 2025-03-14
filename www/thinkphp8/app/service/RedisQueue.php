<?php

declare(strict_types=1);

namespace app\service;

use think\facade\Log;

class RedisQueue
{
    /**
     * 默认队列名称
     */
    const DEFAULT_QUEUE = 'default_queue';

    /**
     * 失败队列后缀
     */
    const FAILED_SUFFIX = ':failed';

    /**
     * 重试队列后缀
     */
    const RETRY_SUFFIX = ':retry';

    /**
     * 最大重试次数
     */
    const MAX_RETRY_ATTEMPTS = 3;

    /**
     * Redis实例
     */
    protected $redis;

    /**
     * 重试次数
     */
    const MAX_RETRY = 3;

    /**
     * 重试间隔（秒）
     */
    const RETRY_INTERVAL = 1;

    public function __construct()
    {
        $this->initRedis();
    }

    /**
     * 初始化Redis连接
     */
    protected function initRedis()
    {
        $retryCount = 0;
        $lastError = null;

        while ($retryCount < self::MAX_RETRY) {
            try {
                $config = config('redis.default');
                // 确保已安装PHP Redis扩展
                if (!extension_loaded('redis')) {
                    throw new \RuntimeException('Redis扩展未安装');
                }
                $this->redis = new \Redis(); // 使用完整的命名空间引用Redis类

                // 设置连接超时时间
                $timeout = $config['timeout'] ?? 5;

                Log::info('Connecting to Redis: host: {host}, port: {port}, timeout: {timeout}, attempt: {attempt}', [
                    'host' => $config['host'],
                    'port' => $config['port'],
                    'timeout' => $timeout,
                    'attempt' => $retryCount + 1
                ]);

                // 连接Redis
                if ($config['persistent']) {
                    $this->redis->pconnect($config['host'], (int)$config['port'], (float)$timeout);
                } else {
                    $this->redis->connect($config['host'], (int)$config['port'], (float)$timeout);
                }

                // 设置密码
                if (!empty($config['password'])) {
                    $this->redis->auth($config['password']);
                }

                // 选择数据库
                if (isset($config['select']) && $config['select'] !== 0) {
                    $this->redis->select((int)$config['select']);
                }

                // 设置选项
                if (!empty($config['options'])) {
                    foreach ($config['options'] as $key => $value) {
                        $this->redis->setOption($key, $value);
                    }
                }

                // 测试连接
                $this->redis->ping();
                Log::info('Redis connection established successfully');
                return;
            } catch (\Exception $e) {
                $lastError = $e;
                $retryCount++;

                Log::warning('Redis connection attempt failed: {error}, attempt: {attempt}/{max_attempts}', [
                    'error' => $e->getMessage(),
                    'attempt' => $retryCount,
                    'max_attempts' => self::MAX_RETRY
                ]);

                if ($retryCount < self::MAX_RETRY) {
                    sleep(self::RETRY_INTERVAL);
                }
            }
        }

        Log::error('Redis connection failed after {max_retry} attempts: {last_error}', [
            'last_error' => $lastError ? $lastError->getMessage() : null,
            'max_retry' => self::MAX_RETRY
        ]);
        throw new \Exception('Redis connection failed: ' . ($lastError ? $lastError->getMessage() : 'Unknown error'));
    }

    /**
     * 检查并重新连接Redis
     */
    protected function reconnectIfNeeded(): void
    {
        try {
            $this->redis->ping();
        } catch (\Exception $e) {
            Log::warning('Redis connection lost, attempting to reconnect');
            $this->initRedis();
        }
    }

    /**
     * 获取失败队列名称
     */
    protected function getFailedQueueName(string $queue): string
    {
        return $queue . self::FAILED_SUFFIX;
    }

    /**
     * 获取重试队列名称
     */
    protected function getRetryQueueName(string $queue): string
    {
        return $queue . self::RETRY_SUFFIX;
    }

    /**
     * 添加任务到队列
     * @param array $data 任务数据
     * @param string $queue 队列名称
     * @return bool
     * @throws \Exception
     */
    public function push(array $data, string $queue = self::DEFAULT_QUEUE): bool
    {
        try {
            $message = [
                'id' => uniqid('task_'),
                'data' => $data,
                'created_at' => date('Y-m-d H:i:s'),
                'attempts' => 0,
                'last_attempt' => null,
                'error' => null
            ];

            $result = $this->redis->lPush($queue, json_encode($message));

            Log::info('Task pushed to queue: {queue}, task_id: {task_id}', [
                'queue' => $queue,
                'task_id' => $message['id'],
                'data' => $data
            ]);

            return $result > 0;
        } catch (\Exception $e) {
            Log::error('Failed to push task to queue: {queue}, error: {error}', [
                'queue' => $queue,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 将失败的任务移到失败队列
     */
    public function markAsFailed(string $queue, array $message, string $error): bool
    {
        try {
            $message['error'] = $error;
            $message['failed_at'] = date('Y-m-d H:i:s');

            $failedQueue = $this->getFailedQueueName($queue);
            $result = $this->redis->lPush($failedQueue, json_encode($message));

            Log::info('Task marked as failed: {queue}, task_id: {task_id}, error: {error}', [
                'queue' => $queue,
                'task_id' => $message['id'],
                'error' => $error
            ]);

            return $result > 0;
        } catch (\Exception $e) {
            Log::error('Failed to mark task as failed: {queue}, task_id: {task_id}, error: {error}', [
                'queue' => $queue,
                'task_id' => $message['id'],
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 重试失败的任务
     */
    public function retry(string $queue, array $message): bool
    {
        try {
            $message['attempts']++;
            $message['last_attempt'] = date('Y-m-d H:i:s');
            $message['error'] = null;

            // 如果超过最大重试次数，移到失败队列
            if ($message['attempts'] > self::MAX_RETRY_ATTEMPTS) {
                return $this->markAsFailed($queue, $message, 'Exceeded maximum retry attempts');
            }

            // 添加到重试队列
            $retryQueue = $this->getRetryQueueName($queue);
            $result = $this->redis->lPush($retryQueue, json_encode($message));

            Log::info('Task scheduled for retry: {queue}, task_id: {task_id}, attempts: {attempts}', [
                'queue' => $queue,
                'task_id' => $message['id'],
                'attempts' => $message['attempts']
            ]);

            return $result > 0;
        } catch (\Exception $e) {
            Log::error('Failed to retry task: {queue}, task_id: {task_id}, error: {error}', [
                'queue' => $queue,
                'task_id' => $message['id'],
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 获取失败的任务列表
     */
    public function getFailedTasks(string $queue, int $start = 0, int $end = -1): array
    {
        try {
            $failedQueue = $this->getFailedQueueName($queue);
            $items = $this->redis->lRange($failedQueue, $start, $end);

            return array_map(function ($item) {
                return json_decode($item, true);
            }, $items);
        } catch (\Exception $e) {
            Log::error('Failed to get failed tasks: {queue}, error: {error}', [
                'queue' => $queue,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * 清除失败任务
     */
    public function clearFailedTasks(string $queue): bool
    {
        try {
            $failedQueue = $this->getFailedQueueName($queue);
            return $this->redis->del($failedQueue) !== false;
        } catch (\Exception $e) {
            Log::error('Failed to clear failed tasks: {queue}, error: {error}', [
                'queue' => $queue,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 重试所有失败的任务
     */
    public function retryAllFailed(string $queue): int
    {
        try {
            $failedQueue = $this->getFailedQueueName($queue);
            $retryCount = 0;

            while ($message = $this->redis->rPop($failedQueue)) {
                $messageData = json_decode($message, true);
                if ($this->retry($queue, $messageData)) {
                    $retryCount++;
                }
            }

            return $retryCount;
        } catch (\Exception $e) {
            Log::error('Failed to retry all failed tasks: {queue}, error: {error}', [
                'queue' => $queue,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * 从队列中获取任务（包括重试队列）
     */
    public function pop(string $queue = self::DEFAULT_QUEUE, int $timeout = 0): ?array
    {
        try {
            // 先检查重试队列
            $retryQueue = $this->getRetryQueueName($queue);
            $result = $this->redis->rPop($retryQueue);

            // 如果重试队列为空，则检查主队列
            if (!$result) {
                $queues = [$queue];
                $result = $this->redis->brPop($queues, $timeout ?: 1);
                if ($result) {
                    $result = $result[1];
                }
            }

            if (!$result) {
                return null;
            }

            $message = json_decode($result, true);
            if (!$message) {
                Log::warning('Invalid message format in queue: {queue}, raw_data: {raw_data}', [
                    'queue' => $queue,
                    'raw_data' => $result
                ]);
                return null;
            }

            Log::info('Task popped from queue: {queue}, task_id: {task_id}, attempts: {attempts}', [
                'queue' => $queue,
                'task_id' => $message['id'],
                'attempts' => $message['attempts'] ?? 0
            ]);

            return $message;
        } catch (\Exception $e) {
            Log::error('Failed to pop task from queue: {queue}, error: {error}', [
                'queue' => $queue,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * 检查是否为超时错误
     * @param \Exception $e
     * @return bool
     */
    protected function isTimeoutError(\Exception $e): bool
    {
        $message = strtolower($e->getMessage());
        return strpos($message, 'timeout') !== false ||
            strpos($message, 'read error') !== false ||
            strpos($message, 'went away') !== false;
    }

    /**
     * 检查是否为连接错误
     * @param \Exception $e
     * @return bool
     */
    protected function isConnectionError(\Exception $e): bool
    {
        $message = strtolower($e->getMessage());
        return strpos($message, 'connection') !== false ||
            strpos($message, 'connect') !== false ||
            strpos($message, 'refused') !== false;
    }

    /**
     * 获取队列长度
     * @param string $queue 队列名称
     * @return int
     */
    public function length(string $queue = self::DEFAULT_QUEUE): int
    {
        try {
            return $this->redis->lLen($queue);
        } catch (\Exception $e) {
            Log::error('Failed to get queue length: ' . $e->getMessage(), [
                'queue' => $queue
            ]);
            return 0;
        }
    }

    /**
     * 清空队列
     * @param string $queue 队列名称
     * @return bool
     */
    public function clear(string $queue = self::DEFAULT_QUEUE): bool
    {
        try {
            return $this->redis->del($queue) > 0;
        } catch (\Exception $e) {
            Log::error('Failed to clear queue: ' . $e->getMessage(), [
                'queue' => $queue
            ]);
            return false;
        }
    }

    /**
     * 查看队列中的所有任务（不会移除任务）
     * @param string $queue 队列名称
     * @param int $start 开始位置
     * @param int $end 结束位置
     * @return array
     */
    public function peek(string $queue = self::DEFAULT_QUEUE, int $start = 0, int $end = -1): array
    {
        try {
            $items = $this->redis->lRange($queue, $start, $end);
            return array_map(function ($item) {
                return json_decode($item, true);
            }, $items);
        } catch (\Exception $e) {
            Log::error('Failed to peek queue: ' . $e->getMessage(), [
                'queue' => $queue
            ]);
            return [];
        }
    }
}
