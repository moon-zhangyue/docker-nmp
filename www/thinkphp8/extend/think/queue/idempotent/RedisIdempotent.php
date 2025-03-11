<?php
declare(strict_types=1);

namespace think\queue\idempotent;

use think\facade\Cache;
use think\facade\Log;

/**
 * Redis幂等性检查工具
 * 使用Redis存储已处理的消息ID，确保消息不会被重复处理
 */
class RedisIdempotent
{
    /**
     * Redis缓存键前缀
     */
    protected $keyPrefix = 'queue:idempotent:';
    
    /**
     * 过期时间（秒）
     */
    protected $expireTime = 86400; // 默认24小时
    
    /**
     * 构造函数
     * 
     * @param array $options 配置选项
     */
    public function __construct(array $options = [])
    {
        if (isset($options['key_prefix'])) {
            $this->keyPrefix = $options['key_prefix'];
        }
        
        if (isset($options['expire_time'])) {
            $this->expireTime = (int)$options['expire_time'];
        }
    }
    
    /**
     * 检查消息是否已处理
     * 
     * @param string $messageId 消息ID
     * @param string $queue 队列名称
     * @return bool 如果消息已处理返回true，否则返回false
     */
    public function isProcessed(string $messageId, string $queue = 'default'): bool
    {
        $key = $this->getKey($messageId, $queue);
        return Cache::has($key);
    }
    
    /**
     * 标记消息为已处理
     * 
     * @param string $messageId 消息ID
     * @param string $queue 队列名称
     * @param array $metadata 额外的元数据
     * @return bool 操作是否成功
     */
    public function markAsProcessed(string $messageId, string $queue = 'default', array $metadata = []): bool
    {
        $key = $this->getKey($messageId, $queue);
        $data = [
            'message_id' => $messageId,
            'queue' => $queue,
            'processed_at' => time(),
            'metadata' => $metadata
        ];
        
        $result = Cache::set($key, $data, $this->expireTime);
        
        if ($result) {
            Log::debug('Marked message as processed', [
                'message_id' => $messageId,
                'queue' => $queue
            ]);
        } else {
            Log::error('Failed to mark message as processed', [
                'message_id' => $messageId,
                'queue' => $queue
            ]);
        }
        
        return $result;
    }
    
    /**
     * 获取已处理消息的元数据
     * 
     * @param string $messageId 消息ID
     * @param string $queue 队列名称
     * @return array|null 消息元数据，如果消息未处理则返回null
     */
    public function getProcessedMetadata(string $messageId, string $queue = 'default'): ?array
    {
        $key = $this->getKey($messageId, $queue);
        return Cache::get($key);
    }
    
    /**
     * 移除已处理的消息记录
     * 
     * @param string $messageId 消息ID
     * @param string $queue 队列名称
     * @return bool 操作是否成功
     */
    public function removeProcessed(string $messageId, string $queue = 'default'): bool
    {
        $key = $this->getKey($messageId, $queue);
        return Cache::delete($key);
    }
    
    /**
     * 生成Redis缓存键
     * 
     * @param string $messageId 消息ID
     * @param string $queue 队列名称
     * @return string Redis缓存键
     */
    protected function getKey(string $messageId, string $queue): string
    {
        return $this->keyPrefix . $queue . ':' . $messageId;
    }
}