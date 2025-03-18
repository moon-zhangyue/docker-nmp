<?php

declare(strict_types=1);

namespace think\queue\health;

use think\facade\Log;
use think\facade\Cache;
use think\queue\metrics\PrometheusCollector;

/**
 * 队列健康检查
 * 用于监控消费者健康状态并暴露给外部监控系统
 */
class HealthCheck
{
    /**
     * Redis缓存键前缀
     */
    protected $keyPrefix = 'queue:health:';

    /**
     * 指标收集器
     */
    protected $metricsCollector;

    /**
     * 单例实例
     */
    private static $instance = null;

    /**
     * 私有构造函数，防止外部实例化
     */
    private function __construct()
    {
        $this->metricsCollector = PrometheusCollector::getInstance();
    }

    /**
     * 获取单例实例
     * 
     * @return HealthCheck
     */
    public static function getInstance(): HealthCheck
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * 获取缓存键
     * 
     * @param string $consumerId 消费者ID
     * @param string $suffix 后缀
     * @return string 缓存键
     */
    protected function getKey(string $consumerId, string $suffix = ''): string
    {
        return $this->keyPrefix . $consumerId . ($suffix ? ':' . $suffix : '');
    }

    /**
     * 更新消费者心跳
     * 
     * @param string $consumerId 消费者ID
     * @param array $status 状态信息
     * @param int $expireTime 过期时间（秒）
     * @return bool 是否成功
     */
    public function updateHeartbeat(string $consumerId, array $status = [], int $expireTime = 60): bool
    {
        $now = time();
        $heartbeatKey = $this->getKey($consumerId, 'heartbeat');

        // 合并状态信息
        $heartbeat = [
            'consumer_id' => $consumerId,
            'last_heartbeat' => $now,
            'status' => 'active',
            'host' => gethostname(),
            'pid' => getmypid(),
            'memory_usage' => memory_get_usage(true),
        ];

        if (!empty($status)) {
            $heartbeat = array_merge($heartbeat, $status);
        }

        // 保存心跳信息
        $result = Cache::set($heartbeatKey, $heartbeat, $expireTime);

        if ($result) {
            // 更新活跃消费者列表
            $this->updateActiveConsumers($consumerId);

            Log::debug('Consumer heartbeat updated', [
                'consumer_id' => $consumerId,
                'expire_time' => $expireTime
            ]);
        }

        return $result;
    }

    /**
     * 更新活跃消费者列表
     * 
     * @param string $consumerId 消费者ID
     * @return bool 是否成功
     */
    protected function updateActiveConsumers(string $consumerId): bool
    {
        $key = $this->keyPrefix . 'active_consumers';
        $consumers = Cache::get($key, []);

        if (!in_array($consumerId, $consumers)) {
            $consumers[] = $consumerId;
            return Cache::set($key, $consumers);
        }

        return true;
    }

    /**
     * 获取所有活跃消费者
     * 
     * @return array 活跃消费者列表
     */
    public function getActiveConsumers(): array
    {
        $key = $this->keyPrefix . 'active_consumers';
        $consumers = Cache::get($key, []);
        $active = [];

        foreach ($consumers as $consumerId) {
            $heartbeat = $this->getConsumerHeartbeat($consumerId);
            if ($heartbeat && $heartbeat['status'] === 'active') {
                $active[] = $consumerId;
            }
        }

        return $active;
    }

    /**
     * 获取消费者心跳信息
     * 
     * @param string $consumerId 消费者ID
     * @return array|null 心跳信息，如果不存在则返回null
     */
    public function getConsumerHeartbeat(string $consumerId): ?array
    {
        $heartbeatKey = $this->getKey($consumerId, 'heartbeat');
        return Cache::get($heartbeatKey);
    }

    /**
     * 检查消费者是否健康
     * 
     * @param string $consumerId 消费者ID
     * @param int $timeout 超时时间（秒）
     * @return bool 是否健康
     */
    public function isConsumerHealthy(string $consumerId, int $timeout = 60): bool
    {
        $heartbeat = $this->getConsumerHeartbeat($consumerId);

        if (!$heartbeat) {
            return false;
        }

        $now = time();
        $lastHeartbeat = $heartbeat['last_heartbeat'] ?? 0;

        // 检查心跳是否超时
        if ($now - $lastHeartbeat > $timeout) {
            return false;
        }

        return $heartbeat['status'] === 'active';
    }

    /**
     * 设置消费者状态
     * 
     * @param string $consumerId 消费者ID
     * @param string $status 状态（active, paused, stopping, error）
     * @param array $metadata 元数据
     * @return bool 是否成功
     */
    public function setConsumerStatus(string $consumerId, string $status, array $metadata = []): bool
    {
        $heartbeat = $this->getConsumerHeartbeat($consumerId);

        if (!$heartbeat) {
            return false;
        }

        $heartbeat['status'] = $status;
        $heartbeat['last_update'] = time();

        if (!empty($metadata)) {
            $heartbeat['metadata'] = $metadata;
        }

        $heartbeatKey = $this->getKey($consumerId, 'heartbeat');
        $result = Cache::set($heartbeatKey, $heartbeat);

        if ($result) {
            Log::info('Consumer status updated', [
                'consumer_id' => $consumerId,
                'status' => $status
            ]);
        }

        return $result;
    }

    /**
     * 获取健康检查状态
     * 
     * @return array 健康检查状态
     */
    public function getHealthStatus(): array
    {
        $activeConsumers = $this->getActiveConsumers();
        $unhealthyConsumers = [];

        foreach ($activeConsumers as $consumerId) {
            if (!$this->isConsumerHealthy($consumerId)) {
                $unhealthyConsumers[] = $consumerId;
            }
        }

        // 获取队列指标
        $metrics = $this->metricsCollector->getQueueMetrics();

        return [
            'status' => empty($unhealthyConsumers) ? 'healthy' : 'unhealthy',
            'active_consumers' => count($activeConsumers),
            'unhealthy_consumers' => $unhealthyConsumers,
            'metrics' => $metrics,
            'timestamp' => time()
        ];
    }

    /**
     * 导出健康检查状态为JSON
     * 
     * @return string JSON格式的健康检查状态
     */
    public function exportHealthStatusJson(): string
    {
        return json_encode($this->getHealthStatus(), JSON_UNESCAPED_UNICODE);
    }

    /**
     * 清除消费者心跳
     * 
     * @param string $consumerId 消费者ID
     * @return bool 是否成功
     */
    public function clearConsumerHeartbeat(string $consumerId): bool
    {
        $heartbeatKey = $this->getKey($consumerId, 'heartbeat');
        $result = Cache::delete($heartbeatKey);

        if ($result) {
            // 从活跃消费者列表中移除
            $key = $this->keyPrefix . 'active_consumers';
            $consumers = Cache::get($key, []);

            if (in_array($consumerId, $consumers)) {
                $consumers = array_filter($consumers, function ($id) use ($consumerId) {
                    return $id !== $consumerId;
                });
                Cache::set($key, array_values($consumers));
            }

            Log::info('Consumer heartbeat cleared', ['consumer_id' => $consumerId]);
        }

        return $result;
    }
}
