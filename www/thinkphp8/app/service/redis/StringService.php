<?php
declare(strict_types=1);

namespace app\service\redis;

use app\service\RedisService;
use Redis;
use Closure;

/**
 * Redis String类型数据服务
 * 
 * 提供对Redis String类型的操作封装，并包含防止缓存穿透和雪崩的机制
 */
class StringService
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
     * 空值标记
     *
     * @var string
     */
    protected string $nilValue = 'NIL';

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
     * 设置字符串值
     *
     * @param string $key 键名
     * @param mixed $value 值
     * @param int $expire 过期时间（秒）
     * @return bool
     */
    public function set(string $key, $value, int $expire = 0): bool
    {
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        $options = [];
        if ($expire > 0) {
            $options['ex'] = $this->redisService->getRandomExpire($expire);
        }

        return (bool)$this->redis->set($key, $value, $options);
    }

    /**
     * 获取字符串值
     *
     * @param string $key 键名
     * @param bool $isJson 是否为JSON数据
     * @return mixed
     */
    public function get(string $key, bool $isJson = false)
    {
        $value = $this->redis->get($key);
        
        if ($value === false) {
            return null;
        }
        
        if ($value === $this->nilValue) {
            return null;
        }
        
        if ($isJson && $value) {
            return json_decode($value, true);
        }
        
        return $value;
    }

    /**
     * 带防穿透机制的获取，当数据不存在时会执行回调函数获取数据并缓存
     *
     * @param string $key 键名
     * @param Closure $callback 回调函数，当缓存不存在时调用
     * @param int $expire 过期时间（秒）
     * @param bool $isJson 是否为JSON数据
     * @param int $emptyExpire 空值过期时间（秒）
     * @return mixed
     */
    public function remember(string $key, Closure $callback, int $expire = 0, bool $isJson = true, int $emptyExpire = 0): mixed
    {
        // 尝试从缓存获取数据
        $value = $this->get($key, $isJson);
        
        // 如果缓存中存在数据，直接返回
        if ($value !== null && $value !== false) {
            return $value;
        }
        
        // 尝试获取分布式锁，防止缓存击穿
        $lockKey = "lock:{$key}";
        $gotLock = $this->redisService->acquireLock($lockKey, 10);
        
        if (!$gotLock) {
            // 如果没有获取到锁，等待一段时间后再次尝试从缓存获取
            usleep(200000); // 等待200毫秒
            return $this->get($key, $isJson);
        }
        
        try {
            // 再次检查缓存（双重检查，避免锁竞争后重复生成）
            $value = $this->get($key, $isJson);
            if ($value !== null && $value !== false) {
                return $value;
            }
            
            // 执行回调函数获取数据
            $value = $callback();
            
            // 处理空值结果，防止缓存穿透
            if ($value === null || $value === false || (is_array($value) && empty($value))) {
                // 对于空值，设置一个较短的过期时间
                $this->set($key, $this->nilValue, $emptyExpire ?: $this->redisService->getEmptyValueExpire());
                return null;
            }
            
            // 缓存结果
            $this->set($key, $value, $expire);
            
            return $value;
        } finally {
            // 释放锁
            $this->redisService->releaseLock($lockKey);
        }
    }

    /**
     * 自增操作
     *
     * @param string $key 键名
     * @param int $step 步长
     * @return int 自增后的值
     */
    public function increment(string $key, int $step = 1): int
    {
        return $this->redis->incrBy($key, $step);
    }

    /**
     * 自减操作
     *
     * @param string $key 键名
     * @param int $step 步长
     * @return int 自减后的值
     */
    public function decrement(string $key, int $step = 1): int
    {
        return $this->redis->decrBy($key, $step);
    }

    /**
     * 批量设置多个字符串值
     *
     * @param array $data 键值对数组
     * @return bool
     */
    public function mSet(array $data): bool
    {
        return (bool)$this->redis->mSet($data);
    }

    /**
     * 批量获取多个字符串值
     *
     * @param array $keys 键名数组
     * @return array
     */
    public function mGet(array $keys): array
    {
        return $this->redis->mGet($keys);
    }

    /**
     * 原子性地设置值并返回旧值
     *
     * @param string $key 键名
     * @param mixed $value 新值
     * @return mixed 旧值
     */
    public function getSet(string $key, $value)
    {
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        
        return $this->redis->getSet($key, $value);
    }

    /**
     * 设置键的过期时间
     *
     * @param string $key 键名
     * @param int $seconds 过期时间（秒）
     * @return bool
     */
    public function expire(string $key, int $seconds): bool
    {
        return (bool)$this->redis->expire($key, $seconds);
    }

    /**
     * 获取键的剩余生存时间
     *
     * @param string $key 键名
     * @return int 剩余秒数，-1表示永久，-2表示键不存在
     */
    public function ttl(string $key): int
    {
        return $this->redis->ttl($key);
    }

    /**
     * 检查键是否存在
     *
     * @param string $key 键名
     * @return bool
     */
    public function exists(string $key): bool
    {
        return (bool)$this->redis->exists($key);
    }

    /**
     * 删除一个或多个键
     *
     * @param string|array $keys 键名或键名数组
     * @return int 删除的键数量
     */
    public function delete($keys): int
    {
        return $this->redis->del($keys);
    }
} 