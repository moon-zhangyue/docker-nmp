<?php
declare(strict_types=1);

namespace app\service\redis;

use app\service\RedisService;
use Redis;
use Closure;

/**
 * Redis Hash类型数据服务
 * 
 * 提供对Redis Hash类型的操作封装
 */
class HashService
{
    /**
     * Redis服务实例
     *
     * @var RedisService
     */
    protected RedisService $redisService;

    /**
     * Redis实例
     *
     * @var Redis
     */
    protected Redis $redis;

    /**
     * 构造函数
     *
     * @param RedisService|null $redisService
     */
    public function __construct(?RedisService $redisService = null)
    {
        $this->redisService = $redisService ?? new RedisService();
        $this->redis = $this->redisService->getRedis();
    }

    /**
     * 设置哈希表字段值
     *
     * @param string $key 哈希表键名
     * @param string $field 字段名
     * @param mixed $value 字段值
     * @return bool
     */
    public function hSet(string $key, string $field, $value): bool
    {
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        
        return (bool)$this->redis->hSet($key, $field, $value);
    }

    /**
     * 获取哈希表字段值
     *
     * @param string $key 哈希表键名
     * @param string $field 字段名
     * @param bool $isJson 是否为JSON数据
     * @return mixed
     */
    public function hGet(string $key, string $field, bool $isJson = false)
    {
        $value = $this->redis->hGet($key, $field);
        
        if ($value === false) {
            return null;
        }
        
        if ($isJson && $value) {
            return json_decode($value, true);
        }
        
        return $value;
    }

    /**
     * 带防穿透机制的获取哈希表字段值，当数据不存在时会执行回调函数获取数据并缓存
     *
     * @param string $key 哈希表键名
     * @param string $field 字段名
     * @param Closure $callback 回调函数，当缓存不存在时调用
     * @param bool $isJson 是否为JSON数据
     * @return mixed
     */
    public function hRemember(string $key, string $field, Closure $callback, bool $isJson = true): mixed
    {
        // 尝试从缓存获取数据
        $value = $this->hGet($key, $field, $isJson);
        
        // 如果缓存中存在数据，直接返回
        if ($value !== null && $value !== false) {
            return $value;
        }
        
        // 尝试获取分布式锁，防止缓存击穿
        $lockKey = "lock:{$key}:{$field}";
        $gotLock = $this->redisService->acquireLock($lockKey, 10);
        
        if (!$gotLock) {
            // 如果没有获取到锁，等待一段时间后再次尝试从缓存获取
            usleep(200000); // 等待200毫秒
            return $this->hGet($key, $field, $isJson);
        }
        
        try {
            // 再次检查缓存（双重检查，避免锁竞争后重复生成）
            $value = $this->hGet($key, $field, $isJson);
            if ($value !== null && $value !== false) {
                return $value;
            }
            
            // 执行回调函数获取数据
            $value = $callback();
            
            // 处理空值结果，防止缓存穿透
            if ($value === null || $value === false || (is_array($value) && empty($value))) {
                // 对于空值，设置一个空字符串，避免穿透
                $this->hSet($key, $field, '');
                return null;
            }
            
            // 缓存结果
            $this->hSet($key, $field, $value);
            
            return $value;
        } finally {
            // 释放锁
            $this->redisService->releaseLock($lockKey);
        }
    }

    /**
     * 设置哈希表的多个字段值
     *
     * @param string $key 哈希表键名
     * @param array $data 字段值数组
     * @return bool
     */
    public function hMSet(string $key, array $data): bool
    {
        foreach ($data as $field => $value) {
            if (is_array($value) || is_object($value)) {
                $data[$field] = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
        }
        
        return (bool)$this->redis->hMSet($key, $data);
    }

    /**
     * 获取哈希表的多个字段值
     *
     * @param string $key 哈希表键名
     * @param array $fields 字段名数组
     * @return array
     */
    public function hMGet(string $key, array $fields): array
    {
        return $this->redis->hMGet($key, $fields);
    }

    /**
     * 获取哈希表的所有字段和值
     *
     * @param string $key 哈希表键名
     * @return array
     */
    public function hGetAll(string $key): array
    {
        return $this->redis->hGetAll($key);
    }

    /**
     * 哈希表字段值自增
     *
     * @param string $key 哈希表键名
     * @param string $field 字段名
     * @param int $step 增加的步长
     * @return int
     */
    public function hIncrBy(string $key, string $field, int $step = 1): int
    {
        return $this->redis->hIncrBy($key, $field, $step);
    }

    /**
     * 哈希表字段值浮点数自增
     *
     * @param string $key 哈希表键名
     * @param string $field 字段名
     * @param float $step 增加的步长
     * @return float
     */
    public function hIncrByFloat(string $key, string $field, float $step): float
    {
        return $this->redis->hIncrByFloat($key, $field, $step);
    }

    /**
     * 检查哈希表字段是否存在
     *
     * @param string $key 哈希表键名
     * @param string $field 字段名
     * @return bool
     */
    public function hExists(string $key, string $field): bool
    {
        return (bool)$this->redis->hExists($key, $field);
    }

    /**
     * 获取哈希表字段数量
     *
     * @param string $key 哈希表键名
     * @return int
     */
    public function hLen(string $key): int
    {
        return $this->redis->hLen($key);
    }

    /**
     * 获取哈希表所有字段名
     *
     * @param string $key 哈希表键名
     * @return array
     */
    public function hKeys(string $key): array
    {
        return $this->redis->hKeys($key);
    }

    /**
     * 获取哈希表所有字段值
     *
     * @param string $key 哈希表键名
     * @return array
     */
    public function hVals(string $key): array
    {
        return $this->redis->hVals($key);
    }

    /**
     * 删除哈希表的一个或多个字段
     *
     * @param string $key 哈希表键名
     * @param string|array $fields 字段名或字段名数组
     * @return int 删除的字段数量
     */
    public function hDel(string $key, $fields): int
    {
        if (is_array($fields)) {
            $params = array_merge([$key], $fields);
            return $this->redis->hDel(...$params);
        } else {
            return $this->redis->hDel($key, $fields);
        }
    }
} 