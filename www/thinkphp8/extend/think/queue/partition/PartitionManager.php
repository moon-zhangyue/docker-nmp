<?php
declare(strict_types=1);

namespace think\queue\partition;

use think\facade\Cache;
use think\facade\Log;
use think\queue\config\HotReloadManager;

/**
 * Kafka分区管理器
 * 用于动态调整Kafka主题分区数量和实现分区负载均衡
 */
class PartitionManager
{
    /**
     * Redis缓存键前缀
     */
    protected $keyPrefix = 'queue:partition:';
    
    /**
     * 配置热加载管理器
     */
    protected $configManager;
    
    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->configManager = HotReloadManager::getInstance();
    }
    
    /**
     * 获取主题的分区数量
     * 
     * @param string $topic 主题名称
     * @return int 分区数量
     */
    public function getPartitionCount(string $topic): int
    {
        $key = $this->getKey($topic, 'count');
        $count = Cache::get($key);
        
        if ($count === null) {
            // 如果缓存中没有，则从配置中获取默认值
            $count = $this->configManager->get('kafka.connections.kafka.partitions.' . $topic, 1);
            Cache::set($key, $count);
        }
        
        return (int)$count;
    }
    
    /**
     * 设置主题的分区数量
     * 
     * @param string $topic 主题名称
     * @param int $count 分区数量
     * @return bool 操作是否成功
     */
    public function setPartitionCount(string $topic, int $count): bool
    {
        if ($count < 1) {
            Log::error('Invalid partition count: {topic}, count: {count}', ['topic' => $topic, 'count' => $count]);
            return false;
        }
        
        $key = $this->getKey($topic, 'count');
        $result = Cache::set($key, $count);
        
        if ($result) {
            // 同时更新配置热加载管理器中的配置
            $this->configManager->set('kafka.connections.kafka.partitions.' . $topic, $count);
            Log::info('Partition count updated: {topic}, count: {count}', ['topic' => $topic, 'count' => $count]);
        }
        
        return $result;
    }
    
    /**
     * 获取消费者应该消费的分区
     * 
     * @param string $topic 主题名称
     * @param string $consumerId 消费者ID
     * @return array 分区ID数组
     */
    public function getConsumerPartitions(string $topic, string $consumerId): array
    {
        // 获取主题的分区数量
        $partitionCount = $this->getPartitionCount($topic);
        
        // 获取主题的所有消费者
        $consumers = $this->getTopicConsumers($topic);
        
        // 如果消费者不在列表中，添加它
        if (!in_array($consumerId, $consumers)) {
            $consumers[] = $consumerId;
            $this->setTopicConsumers($topic, $consumers);
        }
        
        // 计算每个消费者应该消费的分区
        $consumerCount = count($consumers);
        $partitionsPerConsumer = ceil($partitionCount / $consumerCount);
        
        // 找出当前消费者在列表中的索引
        $consumerIndex = array_search($consumerId, $consumers);
        
        // 计算当前消费者应该消费的分区
        $startPartition = $consumerIndex * $partitionsPerConsumer;
        $endPartition = min($startPartition + $partitionsPerConsumer, $partitionCount) - 1;
        
        $partitions = [];
        for ($i = $startPartition; $i <= $endPartition; $i++) {
            $partitions[] = $i;
        }
        
        Log::debug('Consumer partitions calculated: {topic}, consumer_id: {consumer_id}, partitions: {partitions}', [
            'topic' => $topic,
            'consumer_id' => $consumerId,
            'partitions' => $partitions,
            'total_partitions' => $partitionCount,
            'total_consumers' => $consumerCount
        ]);
        
        return $partitions;
    }
    
    /**
     * 获取主题的所有消费者
     * 
     * @param string $topic 主题名称
     * @return array 消费者ID数组
     */
    public function getTopicConsumers(string $topic): array
    {
        $key = $this->getKey($topic, 'consumers');
        $consumers = Cache::get($key);
        
        return is_array($consumers) ? $consumers : [];
    }
    
    /**
     * 设置主题的所有消费者
     * 
     * @param string $topic 主题名称
     * @param array $consumers 消费者ID数组
     * @return bool 操作是否成功
     */
    public function setTopicConsumers(string $topic, array $consumers): bool
    {
        $key = $this->getKey($topic, 'consumers');
        return Cache::set($key, $consumers);
    }
    
    /**
     * 注册消费者
     * 
     * @param string $topic 主题名称
     * @param string $consumerId 消费者ID
     * @return bool 操作是否成功
     */
    public function registerConsumer(string $topic, string $consumerId): bool
    {
        $consumers = $this->getTopicConsumers($topic);
        
        if (!in_array($consumerId, $consumers)) {
            $consumers[] = $consumerId;
            $result = $this->setTopicConsumers($topic, $consumers);
            
            if ($result) {
                Log::info('Consumer registered: {topic}, consumer_id: {consumer_id}', ['topic' => $topic, 'consumer_id' => $consumerId]);
                
                // 触发分区重新平衡
                $this->rebalancePartitions($topic);
            }
            
            return $result;
        }
        
        return true;
    }
    
    /**
     * 注销消费者
     * 
     * @param string $topic 主题名称
     * @param string $consumerId 消费者ID
     * @return bool 操作是否成功
     */
    public function unregisterConsumer(string $topic, string $consumerId): bool
    {
        $consumers = $this->getTopicConsumers($topic);
        
        $key = array_search($consumerId, $consumers);
        if ($key !== false) {
            unset($consumers[$key]);
            $consumers = array_values($consumers); // 重新索引数组
            $result = $this->setTopicConsumers($topic, $consumers);
            
            if ($result) {
                Log::info('Consumer unregistered: {topic}, consumer_id: {consumer_id}', ['topic' => $topic, 'consumer_id' => $consumerId]);
                
                // 触发分区重新平衡
                $this->rebalancePartitions($topic);
            }
            
            return $result;
        }
        
        return true;
    }
    
    /**
     * 重新平衡分区
     * 
     * @param string $topic 主题名称
     * @return void
     */
    public function rebalancePartitions(string $topic): void
    {
        $consumers = $this->getTopicConsumers($topic);
        $partitionCount = $this->getPartitionCount($topic);
        
        if (empty($consumers)) {
            return;
        }
        
        Log::info('Rebalancing partitions: {topic}, consumers: {consumers}', [
            'topic' => $topic,
            'partition_count' => $partitionCount,
            'consumer_count' => count($consumers)
        ]);
        
        // 通知所有消费者重新平衡
        $rebalanceKey = $this->getKey($topic, 'rebalance');
        Cache::set($rebalanceKey, time());
    }
    
    /**
     * 检查是否需要重新平衡
     * 
     * @param string $topic 主题名称
     * @param int $lastCheckTime 上次检查时间
     * @return bool 是否需要重新平衡
     */
    public function needRebalance(string $topic, int $lastCheckTime): bool
    {
        $rebalanceKey = $this->getKey($topic, 'rebalance');
        $rebalanceTime = Cache::get($rebalanceKey, 0);
        
        return $rebalanceTime > $lastCheckTime;
    }
    
    /**
     * 获取分区负载情况
     * 
     * @param string $topic 主题名称
     * @return array 分区负载数据
     */
    public function getPartitionLoad(string $topic): array
    {
        $key = $this->getKey($topic, 'load');
        $load = Cache::get($key);
        
        return is_array($load) ? $load : [];
    }
    
    /**
     * 更新分区负载情况
     * 
     * @param string $topic 主题名称
     * @param int $partition 分区ID
     * @param int $messageCount 消息数量
     * @return bool 操作是否成功
     */
    public function updatePartitionLoad(string $topic, int $partition, int $messageCount): bool
    {
        $load = $this->getPartitionLoad($topic);
        $load[$partition] = $messageCount;
        
        $key = $this->getKey($topic, 'load');
        return Cache::set($key, $load);
    }
    
    /**
     * 生成Redis缓存键
     * 
     * @param string $topic 主题名称
     * @param string $type 键类型
     * @return string Redis缓存键
     */
    protected function getKey(string $topic, string $type): string
    {
        return $this->keyPrefix . $topic . ':' . $type;
    }
}