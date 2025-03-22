<?php

declare(strict_types=1);

namespace think\queue\autoscale;

use think\facade\Log;
use think\facade\Cache;
use think\queue\health\HealthCheck;
use think\queue\metrics\PrometheusCollector;
use think\queue\balance\LoadBalancer;
use think\queue\config\HotReloadManager;

/**
 * 队列消费者自动扩展器
 * 根据队列负载自动调整消费者实例数
 */
class AutoScaler
{
    /**
     * Redis缓存键前缀
     */
    protected $keyPrefix = 'queue:autoscale:';

    /**
     * 健康检查器
     */
    protected $healthCheck;

    /**
     * 指标收集器
     */
    protected $metricsCollector;

    /**
     * 负载均衡器
     */
    protected $loadBalancer;

    /**
     * 配置热加载管理器
     */
    protected $configManager;

    /**
     * 租户ID
     */
    protected $tenantId = 'default';

    /**
     * 单例实例映射
     * 按租户ID存储不同的实例
     */
    private static $instances = [];

    /**
     * 私有构造函数，防止外部实例化
     * 
     * @param string $tenantId 租户ID
     */
    private function __construct(string $tenantId = 'default')
    {
        $this->tenantId = $tenantId;
        $this->healthCheck = HealthCheck::getInstance();
        $this->metricsCollector = PrometheusCollector::getInstance();
        $this->loadBalancer = LoadBalancer::getInstance();
        $this->configManager = HotReloadManager::getInstance($tenantId);
    }

    /**
     * 获取实例
     * 
     * @param string $tenantId 租户ID
     * @return AutoScaler
     */
    public static function getInstance(string $tenantId = 'default'): AutoScaler
    {
        if (!isset(self::$instances[$tenantId])) {
            self::$instances[$tenantId] = new self($tenantId);
        }

        return self::$instances[$tenantId];
    }

    /**
     * 检查并自动扩展消费者
     * 
     * @param string $topic 主题名称
     * @return array 扩展结果
     */
    public function checkAndScale(string $topic): array
    {
        // 获取主题负载信息
        $topicLoad = $this->loadBalancer->getTopicLoad($topic);

        // 获取自动扩展配置
        $config = $this->getAutoScaleConfig($topic);

        // 计算期望的消费者数量
        $desiredConsumerCount = $this->calculateDesiredConsumerCount($topicLoad, $config);

        // 获取当前活跃消费者数量
        $activeConsumers = $this->healthCheck->getActiveConsumers();
        $currentConsumerCount = count(array_filter($activeConsumers, function ($consumerId) use ($topic) {
            return strpos($consumerId, $topic) !== false;
        }));

        $result = [
            'topic' => $topic,
            'current_consumer_count' => $currentConsumerCount,
            'desired_consumer_count' => $desiredConsumerCount,
            'message_rate' => $topicLoad['message_rate'],
            'action' => 'none',
            'timestamp' => time()
        ];

        // 如果期望的消费者数量与当前数量不同，则需要扩展或收缩
        if ($desiredConsumerCount > $currentConsumerCount) {
            // 需要扩展消费者
            $result['action'] = 'scale_up';
            $this->scaleUp($topic, $desiredConsumerCount - $currentConsumerCount);
        } elseif ($desiredConsumerCount < $currentConsumerCount) {
            // 需要收缩消费者
            $result['action'] = 'scale_down';
            $this->scaleDown($topic, $currentConsumerCount - $desiredConsumerCount);
        }

        // 记录自动扩展结果
        $this->recordScaleAction($topic, $result);

        return $result;
    }

    /**
     * 获取自动扩展配置
     * 
     * @param string $topic 主题名称
     * @return array 自动扩展配置
     */
    protected function getAutoScaleConfig(string $topic): array
    {
        $defaultConfig = [
            'min_consumers' => 1,
            'max_consumers' => 10,
            'messages_per_consumer' => 100,
            'scale_up_threshold' => 0.8,
            'scale_down_threshold' => 0.3,
            'scale_up_step' => 1,
            'scale_down_step' => 1,
            'cooldown_period' => 300,
        ];

        // 从配置中获取自动扩展配置
        $configKey = 'kafka.connections.kafka.autoscale.' . $topic;
        $config = $this->configManager->get($configKey, []);

        // 合并默认配置和自定义配置
        return array_merge($defaultConfig, $config);
    }

    /**
     * 计算期望的消费者数量
     * 
     * @param array $topicLoad 主题负载信息
     * @param array $config 自动扩展配置
     * @return int 期望的消费者数量
     */
    protected function calculateDesiredConsumerCount(array $topicLoad, array $config): int
    {
        $messageRate = $topicLoad['message_rate'] ?? 0;
        $messagesPerConsumer = $config['messages_per_consumer'];

        // 根据消息速率和每个消费者处理的消息数计算期望的消费者数量
        $desiredCount = ceil($messageRate / $messagesPerConsumer);

        // 确保消费者数量在配置的最小值和最大值之间
        $desiredCount = max($config['min_consumers'], min($config['max_consumers'], $desiredCount));

        return (int)$desiredCount;
    }

    /**
     * 扩展消费者
     * 
     * @param string $topic 主题名称
     * @param int $count 扩展数量
     * @return bool 操作是否成功
     */
    protected function scaleUp(string $topic, int $count): bool
    {
        // 检查冷却期
        if ($this->isInCooldown($topic, 'scale_up')) {
            Log::info('Auto scale up is in cooldown period, topic: {topic}, tenant_id: {tenant_id}', [
                'topic' => $topic,
                'tenant_id' => $this->tenantId
            ]);
            return false;
        }

        Log::info('Scaling up consumers, topic: {topic}, count: {count}, tenant_id: {tenant_id}', [
            'topic' => $topic,
            'count' => $count,
            'tenant_id' => $this->tenantId
        ]);

        // 更新配置中的消费者数量
        $configKey = 'kafka.connections.kafka.consumer_count.' . $topic;
        $currentCount = $this->configManager->get($configKey, 1);
        $newCount = $currentCount + $count;
        $this->configManager->set($configKey, $newCount);

        // 设置冷却期
        $this->setCooldown($topic, 'scale_up');

        return true;
    }

    /**
     * 收缩消费者
     * 
     * @param string $topic 主题名称
     * @param int $count 收缩数量
     * @return bool 操作是否成功
     */
    protected function scaleDown(string $topic, int $count): bool
    {
        // 检查冷却期
        if ($this->isInCooldown($topic, 'scale_down')) {
            Log::info('Auto scale down is in cooldown period, topic: {topic}, tenant_id: {tenant_id}', [
                'topic' => $topic,
                'tenant_id' => $this->tenantId
            ]);
            return false;
        }

        Log::info('Scaling down consumers, topic: {topic}, count: {count}, tenant_id: {tenant_id}', [
            'topic' => $topic,
            'count' => $count,
            'tenant_id' => $this->tenantId
        ]);

        // 更新配置中的消费者数量
        $configKey = 'kafka.connections.kafka.consumer_count.' . $topic;
        $currentCount = $this->configManager->get($configKey, 1);
        $newCount = max(1, $currentCount - $count);
        $this->configManager->set($configKey, $newCount);

        // 设置冷却期
        $this->setCooldown($topic, 'scale_down');

        return true;
    }

    /**
     * 检查是否在冷却期内
     * 
     * @param string $topic 主题名称
     * @param string $action 操作类型（scale_up, scale_down）
     * @return bool 是否在冷却期内
     */
    protected function isInCooldown(string $topic, string $action): bool
    {
        $key = $this->keyPrefix . $this->tenantId . ':' . $topic . ':cooldown:' . $action;
        $cooldownUntil = Cache::get($key, 0);

        return time() < $cooldownUntil;
    }

    /**
     * 设置冷却期
     * 
     * @param string $topic 主题名称
     * @param string $action 操作类型（scale_up, scale_down）
     * @return void
     */
    protected function setCooldown(string $topic, string $action): void
    {
        // 获取自动扩展配置
        $config = $this->getAutoScaleConfig($topic);

        // 获取冷却期时长（秒）
        $cooldownPeriod = $config['cooldown_period'];

        // 计算冷却期结束时间
        $cooldownUntil = time() + $cooldownPeriod;

        // 设置冷却期缓存
        $key = $this->keyPrefix . $this->tenantId . ':' . $topic . ':cooldown:' . $action;
        Cache::set($key, $cooldownUntil, $cooldownPeriod);

        Log::debug('Set cooldown period', [
            'topic' => $topic,
            'action' => $action,
            'cooldown_period' => $cooldownPeriod,
            'cooldown_until' => date('Y-m-d H:i:s', $cooldownUntil),
            'tenant_id' => $this->tenantId
        ]);
    }

    /**
     * 记录自动扩展操作
     * 
     * @param string $topic 主题名称
     * @param array $result 扩展结果
     * @return void
     */
    protected function recordScaleAction(string $topic, array $result): void
    {
        // 记录日志
        Log::info('Auto scale action, topic: {topic}, action: {action}, current_count: {current_consumer_count}, desired_count: {desired_consumer_count}', $result);

        // 记录到缓存中，保存最近的扩展历史
        $historyKey = $this->keyPrefix . $this->tenantId . ':' . $topic . ':history';
        $history = Cache::get($historyKey, []);

        // 限制历史记录数量，最多保存50条
        if (count($history) >= 50) {
            array_shift($history);
        }

        // 添加新的记录
        $history[] = $result;
        Cache::set($historyKey, $history, 86400); // 保存一天

        // 记录指标
        if ($result['action'] !== 'none') {
            $labels = [
                'topic' => $topic,
                'tenant_id' => $this->tenantId,
                'action' => $result['action']
            ];

            // 记录扩展操作次数
            $this->metricsCollector->incrementCounter('queue_autoscale_actions_total', 1.0, $labels);

            // 记录当前消费者数量
            $this->metricsCollector->incrementGauge('queue_consumers', $result['current_consumer_count'], [
                'topic' => $topic,
                'tenant_id' => $this->tenantId
            ]);

            // 记录消息处理速率
            $this->metricsCollector->incrementGauge('queue_message_rate', $result['message_rate'], [
                'topic' => $topic,
                'tenant_id' => $this->tenantId
            ]);
        }
    }
}
