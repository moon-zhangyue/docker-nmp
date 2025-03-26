<?php

namespace think\queue\pool;

use think\facade\Log;
use think\facade\Config;
use RdKafka\Producer;
use RdKafka\Conf;
use RdKafka\TopicPartition;
use RdKafka\Topic;
use RdKafka\Message;

/**
 * Kafka管理器
 * 
 * 用于管理Kafka连接、主题和消息
 */
class KafkaManager
{
    /**
     * 连接池
     * @var KafkaConnectionPool
     */
    protected $pool;
    
    /**
     * 配置信息
     * @var array
     */
    protected $config;
    
    /**
     * 构造函数
     * 
     * @param array $config Kafka配置
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->pool = new KafkaConnectionPool($config);
    }
    
    /**
     * 获取主题列表
     * 
     * @return array
     */
    public function getTopics()
    {
        try {
            $producer = $this->pool->get();
            
            // 获取元数据
            $metadata = $producer->getMetadata(true, null, 10000);
            
            // 提取主题列表
            $topics = [];
            foreach ($metadata->getTopics() as $topic) {
                $topics[] = [
                    'name' => $topic->getTopic(),
                    'partitions' => $topic->getPartitionCount(),
                    'replicas' => $topic->getReplicaCount()
                ];
            }
            
            // 归还连接
            $this->pool->recycleObj($producer);
            
            Log::info('获取Kafka主题列表成功,count:{count}', ['count' => count($topics)]);
            return $topics;
        } catch (\Exception $e) {
            Log::error('获取Kafka主题列表失败: {message}', ['message' => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * 获取Broker列表
     * 
     * @return array
     */
    public function getBrokers()
    {
        try {
            $producer = $this->pool->get();
            
            // 获取元数据
            $metadata = $producer->getMetadata(true, null, 10000);
            
            // 提取Broker列表
            $brokers = [];
            foreach ($metadata->getBrokers() as $broker) {
                $brokers[] = [
                    'id' => $broker->getId(),
                    'host' => $broker->getHost(),
                    'port' => $broker->getPort()
                ];
            }
            
            // 归还连接
            $this->pool->recycleObj($producer);
            
            Log::info('获取Kafka Broker列表成功,count:{count}', ['count' => count($brokers)]);
            return $brokers;
        } catch (\Exception $e) {
            Log::error('获取Kafka Broker列表失败: {message}', ['message' => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * 发送消息
     * 
     * @param string $topic 主题
     * @param string $message 消息内容
     * @param string|null $key 消息键
     * @param int $partition 分区
     * @return bool
     */
    public function sendMessage(string $topic, string $message, ?string $key = null, int $partition = RD_KAFKA_PARTITION_UA)
    {
        try {
            $producer = $this->pool->get();
            
            // 获取主题对象
            $topicObj = $producer->newTopic($topic);
            
            // 发送消息
            $result = $topicObj->produce($partition, 0, $message, $key);
            
            // 刷新消息
            $producer->flush(10000);
            
            // 归还连接
            $this->pool->recycleObj($producer);
            
            Log::info('Kafka消息发送成功,topic:{topic},partition:{partition}', [
                'topic' => $topic,
                'partition' => $partition
            ]);
            return true;
        } catch (\Exception $e) {
            Log::error('Kafka消息发送失败: {message},topic:{topic}', [
                'message' => $e->getMessage(),
                'topic' => $topic
            ]);
            throw $e;
        }
    }
    
    /**
     * 批量发送消息
     * 
     * @param string $topic 主题
     * @param array $messages 消息数组
     * @param string|null $key 消息键
     * @param int $partition 分区
     * @return bool
     */
    public function sendBatchMessages(string $topic, array $messages, ?string $key = null, int $partition = RD_KAFKA_PARTITION_UA)
    {
        try {
            $producer = $this->pool->get();
            
            // 获取主题对象
            $topicObj = $producer->newTopic($topic);
            
            // 批量发送消息
            foreach ($messages as $message) {
                $topicObj->produce($partition, 0, $message, $key);
            }
            
            // 刷新消息
            $producer->flush(10000);
            
            // 归还连接
            $this->pool->recycleObj($producer);
            
            Log::info('Kafka批量消息发送成功,topic:{topic},count:{count}', [
                'topic' => $topic,
                'count' => count($messages)
            ]);
            return true;
        } catch (\Exception $e) {
            Log::error('Kafka批量消息发送失败: {message},topic:{topic}', [
                'message' => $e->getMessage(),
                'topic' => $topic
            ]);
            throw $e;
        }
    }
    
    /**
     * 关闭管理器
     */
    public function close()
    {
        try {
            $this->pool->close();
            Log::info('Kafka管理器已关闭');
        } catch (\Exception $e) {
            Log::error('关闭Kafka管理器失败: {message}', ['message' => $e->getMessage()]);
        }
    }
} 