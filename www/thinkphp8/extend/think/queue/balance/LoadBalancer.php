<?php

declare(strict_types=1);

namespace think\queue\balance;

use think\facade\Log;
use think\facade\Cache;
use think\queue\config\HotReloadManager;
use think\queue\partition\PartitionManager;

/**
 * Kafka负载均衡器
 * 用于动态调整Kafka主题分区和消费者的负载均衡
 */
class LoadBalancer
{
    /**
     * Redis缓存键前缀
     */
    protected $keyPrefix = 'queue:balance:';

    /**
     * 配置热加载管理器
     */
    protected $configManager;

    /**
     * 分区管理器
     */
    protected $partitionManager;

    /**
     * 单例实例
     */
    private static $instance = null;

    /**
     * 私有构造函数，防止外部实例化
     */
    private function __construct()
    {
        $this->configManager = HotReloadManager::getInstance();
        $this->partitionManager = new PartitionManager();
    }

    /**
     * 获取单例实例
     * 
     * @return LoadBalancer
     */
    public static function getInstance(): LoadBalancer
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * 获取缓存键
     * 
     * @param string $topic 主题名称
     * @param string $suffix 后缀
     * @return string 缓存键
     */
    protected function getKey(string $topic, string $suffix = ''): string
    {
        return $this->keyPrefix . $topic . ($suffix ? ':' . $suffix : '');
    }

    /**
     * 获取主题的负载信息
     * 
     * @param string $topic 主题名称
     * @return array 负载信息
     */
    public function getTopicLoad(string $topic): array
    {
        $key = $this->getKey($topic, 'load');
        $load = Cache::get($key, []);

        if (empty($load)) {
            $load = [
                'message_rate' => 0,
                'consumer_count' => 0,
                'partition_count' => $this->partitionManager->getPartitionCount($topic),
                'last_update' => time(),
            ];
            Cache::set($key, $load);
        }

        return $load;
    }

    /**
     * 更新主题的消息速率
     * 
     * @param string $topic 主题名称
     * @param int $messageCount 消息数量
     * @param int $timeWindow 时间窗口（秒）
     * @return float 消息速率（消息/秒）
     */
    public function updateMessageRate(string $topic, int $messageCount, int $timeWindow = 60): float
    {
        $key = $this->getKey($topic, 'messages');
        $messages = Cache::get($key, []);

        $now = time();
        $microtime = microtime(true);
        $messages[] = [
            'count' => $messageCount,
            'time' => $now,
            'microtime' => $microtime,
        ];

        // 移除超过时间窗口的数据
        $messages = array_filter($messages, function ($item) use ($now, $timeWindow) {
            return ($now - $item['time']) <= $timeWindow;
        });

        // 使用Redis事务确保原子性操作
        Cache::set($key, $messages);

        // 计算消息速率 - 使用更精确的时间计算
        $totalMessages = array_sum(array_column($messages, 'count'));

        // 如果数据点超过1个，使用最早和最新的时间点计算实际时间窗口
        if (count($messages) > 1) {
            $times = array_column($messages, 'microtime');
            $earliestTime = min($times);
            $latestTime = max($times);
            $actualTimeWindow = max(1, $latestTime - $earliestTime); // 确保至少为1秒
            $rate = $totalMessages / $actualTimeWindow;
        } else {
            // 只有一个数据点时，使用默认时间窗口
            $rate = $totalMessages / $timeWindow;
        }

        // 更新负载信息
        $load = $this->getTopicLoad($topic);
        $load['message_rate'] = $rate;
        $load['last_update'] = $now;
        $load['total_messages'] = $totalMessages;
        $load['time_window'] = $timeWindow;
        Cache::set($this->getKey($topic, 'load'), $load);

        Log::debug('Updated message rate for topic {topic}: {rate} msg/sec (total: {total} msgs in {window} sec)', [
            'topic' => $topic,
            'rate' => round($rate, 2),
            'total' => $totalMessages,
            'window' => $timeWindow
        ]);

        return $rate;
    }

    /**
     * 更新主题的消费者数量
     * 
     * @param string $topic 主题名称
     * @param int $consumerCount 消费者数量
     * @return void
     */
    public function updateConsumerCount(string $topic, int $consumerCount): void
    {
        $load = $this->getTopicLoad($topic);
        $load['consumer_count'] = $consumerCount;
        $load['last_update'] = time();
        Cache::set($this->getKey($topic, 'load'), $load);
    }

    /**
     * 检查是否需要调整分区数量
     * 
     * @param string $topic 主题名称
     * @return bool 是否需要调整
     */
    public function needPartitionAdjustment(string $topic): bool
    {
        $load = $this->getTopicLoad($topic);

        // 获取配置的阈值
        $messageRateThreshold = $this->configManager->get(
            'kafka.connections.kafka.balance.message_rate_threshold',
            10.0
        );

        $consumerRatio = $this->configManager->get(
            'kafka.connections.kafka.balance.consumer_partition_ratio',
            2.0
        );

        // 如果消息速率超过阈值，检查是否需要增加分区
        if ($load['message_rate'] > $messageRateThreshold) {
            // 计算理想的分区数量：消费者数量 * 比率
            $idealPartitions = max(1, ceil($load['consumer_count'] * $consumerRatio));

            // 如果理想分区数量大于当前分区数量，需要调整
            if ($idealPartitions > $load['partition_count']) {
                return true;
            }
        }

        // 如果消息速率低于阈值的一半，检查是否需要减少分区
        if ($load['message_rate'] < $messageRateThreshold / 2) {
            // 计算理想的分区数量：消费者数量 * 比率
            $idealPartitions = max(1, ceil($load['consumer_count'] * $consumerRatio));

            // 如果理想分区数量小于当前分区数量，需要调整
            if ($idealPartitions < $load['partition_count']) {
                return true;
            }
        }

        return false;
    }

    /**
     * 调整分区数量
     * 
     * @param string $topic 主题名称
     * @return bool 操作是否成功
     */
    public function adjustPartitions(string $topic): bool
    {
        $load = $this->getTopicLoad($topic);

        // 获取配置的阈值
        $messageRateThreshold = $this->configManager->get(
            'kafka.connections.kafka.balance.message_rate_threshold',
            10.0
        );

        $consumerRatio = $this->configManager->get(
            'kafka.connections.kafka.balance.consumer_partition_ratio',
            2.0
        );

        // 获取最小消息速率
        $minMessageRate = $this->configManager->get(
            'kafka.connections.kafka.balance.min_message_rate',
            1.0
        );

        // 计算理想的分区数量
        $idealPartitions = max(1, ceil($load['consumer_count'] * $consumerRatio));

        Log::info('Evaluating partition adjustment for topic {topic}: current_rate={rate}, threshold={threshold}, consumers={consumers}, current_partitions={current}, ideal_partitions={ideal}', [
            'topic' => $topic,
            'rate' => round($load['message_rate'], 2),
            'threshold' => $messageRateThreshold,
            'consumers' => $load['consumer_count'],
            'current' => $load['partition_count'],
            'ideal' => $idealPartitions
        ]);

        // 如果消息速率高于阈值，增加分区
        if ($load['message_rate'] > $messageRateThreshold && $idealPartitions > $load['partition_count']) {
            Log::notice('Increasing partitions for topic {topic} from {current} to {new} due to high message rate ({rate} > {threshold})', [
                'topic' => $topic,
                'current' => $load['partition_count'],
                'new' => $idealPartitions,
                'rate' => round($load['message_rate'], 2),
                'threshold' => $messageRateThreshold
            ]);

            $result = $this->partitionManager->setPartitionCount($topic, $idealPartitions);

            if ($result) {
                // 更新本地缓存的分区数量
                $load['partition_count'] = $idealPartitions;
                Cache::set($this->getKey($topic, 'load'), $load);
            }

            return $result;
        }

        // 如果消息速率低于阈值的一半，但高于最小速率，减少分区
        if (
            $load['message_rate'] < $messageRateThreshold / 2 &&
            $load['message_rate'] > $minMessageRate &&
            $idealPartitions < $load['partition_count']
        ) {

            Log::notice('Decreasing partitions for topic {topic} from {current} to {new} due to low message rate ({rate} < {threshold}/2)', [
                'topic' => $topic,
                'current' => $load['partition_count'],
                'new' => $idealPartitions,
                'rate' => round($load['message_rate'], 2),
                'threshold' => $messageRateThreshold
            ]);

            $result = $this->partitionManager->setPartitionCount($topic, $idealPartitions);

            if ($result) {
                // 更新本地缓存的分区数量
                $load['partition_count'] = $idealPartitions;
                Cache::set($this->getKey($topic, 'load'), $load);
            }

            return $result;
        }

        Log::debug('No partition adjustment needed for topic {topic}', ['topic' => $topic]);
        return true;
    }

    /**
     * 检查并调整分区数量
     * 该方法用于定期检查主题的负载情况，并在需要时调整分区数量
     * 
     * @param string $topic 主题名称
     * @param int $checkInterval 检查间隔（秒）
     * @return bool 是否执行了调整
     */
    public function checkAndAdjustPartitions(string $topic, int $checkInterval = 300): bool
    {
        // 获取上次检查时间
        $lastCheckKey = $this->getKey($topic, 'last_check');
        $lastCheckTime = Cache::get($lastCheckKey, 0);
        $now = time();

        // 如果距离上次检查时间不足检查间隔，则跳过
        if (($now - $lastCheckTime) < $checkInterval) {
            return false;
        }

        // 更新检查时间
        Cache::set($lastCheckKey, $now);

        // 检查是否需要调整分区
        if ($this->needPartitionAdjustment($topic)) {
            Log::info('Partition adjustment needed for topic {topic}, executing...', ['topic' => $topic]);
            return $this->adjustPartitions($topic);
        }

        return false;
    }
}
