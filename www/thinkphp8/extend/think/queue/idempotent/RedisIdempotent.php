<?php

declare(strict_types=1); // 严格类型声明，确保代码中的类型安全

namespace think\queue\idempotent; // 定义命名空间，用于组织代码和避免类名冲突

use think\facade\Cache; // 引入think框架的Cache门面，用于操作缓存
use think\facade\Log; // 引入think框架的Log门面，用于记录日志

/**
 * Redis幂等性检查工具
 * 使用Redis存储已处理的消息ID，确保消息不会被重复处理
 */
class RedisIdempotent
{
    /**
     * Redis缓存键前缀
     */
    protected $keyPrefix = 'queue:idempotent:'; // 定义缓存键的前缀，用于区分不同类型的缓存数据

    /**
     * 过期时间（秒）
     */
    protected $expireTime = 86400; // 默认24小时，设置缓存数据的过期时间

    /**
     * 构造函数
     * 
     * @param array $options 配置选项
     */
    public function __construct(array $options = [])
    {
        if (isset($options['key_prefix'])) {
            $this->keyPrefix = $options['key_prefix']; // 如果配置选项中包含键前缀，则使用配置中的值
        }

        if (isset($options['expire_time'])) {
            $this->expireTime = (int)$options['expire_time']; // 如果配置选项中包含过期时间，则使用配置中的值，并转换为整数
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
        $key = $this->getKey($messageId, $queue); // 生成缓存键
        return Cache::has($key); // 检查缓存中是否存在该键，存在则表示消息已处理
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
        $key = $this->getKey($messageId, $queue); // 生成缓存键
        $data = [ // 准备要存储的数据
            'message_id' => $messageId,
            'queue' => $queue,
            'processed_at' => time(), // 当前时间戳
            'metadata' => $metadata
        ];

        $result = Cache::set($key, $data, $this->expireTime); // 将数据存储到缓存中，并设置过期时间

        if ($result) {
            Log::debug('Marked message as processed: {message_id}', [ // 记录调试日志
                'message_id' => $messageId,
                'queue' => $queue
            ]);
        } else {
            Log::error('Failed to mark message as processed: {message_id}', [ // 记录错误日志
                'message_id' => $messageId,
                'queue' => $queue
            ]);
        }

        return $result; // 返回操作结果
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
        $key = $this->getKey($messageId, $queue); // 生成缓存键
        return Cache::get($key); // 从缓存中获取数据，如果不存在则返回null
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
        $key = $this->getKey($messageId, $queue); // 生成缓存键
        return Cache::delete($key); // 从缓存中删除该键，并返回操作结果
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
        return $this->keyPrefix . $queue . ':' . $messageId; // 根据前缀、队列名称和消息ID生成缓存键
    }
}
