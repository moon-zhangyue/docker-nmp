<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Cache;
use think\facade\Config;
use Redis;
use RedisException;
use think\cache\driver\Redis as ThinkRedis;
use app\service\redis\StringService;
use app\service\redis\HashService;
use app\service\redis\ListService;
use app\service\redis\SetService;
use app\service\redis\ZSetService;
use app\service\redis\GeoService;
use app\service\redis\HyperLogLogService;
use app\service\redis\BitMapService;

/**
 * Redis服务类
 * 
 * 提供对Redis各种数据类型的操作封装，并包含防止缓存穿透和雪崩的机制
 * 
 * @author YourName
 */
class RedisService
{
    /**
     * Redis实例
     *
     * @var Redis
     */
    protected Redis $redis;

    /**
     * 连接名称
     *
     * @var string
     */
    protected string $connection = 'default';

    /**
     * 锁定前缀
     *
     * @var string
     */
    protected string $lockPrefix = 'lock:';

    /**
     * 空值过期时间（防止缓存穿透）
     *
     * @var int
     */
    protected int $emptyValueExpire = 60;

    /**
     * 默认过期时间随机范围（防止缓存雪崩）
     *
     * @var array
     */
    protected array $defaultExpireRange = [3600, 4200]; // 1小时到1小时10分钟之间的随机值

    /**
     * 服务实例缓存
     *
     * @var array
     */
    protected array $services = [];

    /**
     * 构造函数
     *
     * @param string $connection Redis连接名称
     * @throws RedisException
     */
    public function __construct(string $connection = 'default')
    {
        $this->connection = $connection;
        $this->connect();
    }

    /**
     * 建立Redis连接
     *
     * @return void
     * @throws RedisException
     */
    protected function connect(): void
    {
        $config = Config::get('redis.' . $this->connection);
        if (!$config) {
            throw new RedisException("Redis connection '{$this->connection}' not configured");
        }

        $redis = new Redis();
        $redis->connect(
            $config['host'] ?? 'localhost',
            (int)($config['port'] ?? 6379),
            (float)($config['timeout'] ?? 0.0)
        );

        if (!empty($config['password'])) {
            $redis->auth($config['password']);
        }

        if (isset($config['select']) && is_numeric($config['select'])) {
            $redis->select((int)$config['select']);
        }

        if (!empty($config['options']) && is_array($config['options'])) {
            foreach ($config['options'] as $key => $value) {
                $redis->setOption($key, $value);
            }
        }

        $this->redis = $redis;
    }

    /**
     * 获取Redis原始实例
     *
     * @return Redis
     */
    public function getRedis(): Redis
    {
        return $this->redis;
    }

    /**
     * 获取ThinkPHP Redis缓存驱动实例
     *
     * @return ThinkRedis
     */
    public function getThinkRedis(): ThinkRedis
    {
        return Cache::store('redis')->handler();
    }

    /**
     * 设置空值过期时间
     *
     * @param int $seconds
     * @return $this
     */
    public function setEmptyValueExpire(int $seconds): self
    {
        $this->emptyValueExpire = $seconds;
        return $this;
    }

    /**
     * 设置默认过期时间范围（防止缓存雪崩）
     *
     * @param int $min 最小过期时间（秒）
     * @param int $max 最大过期时间（秒）
     * @return $this
     */
    public function setDefaultExpireRange(int $min, int $max): self
    {
        $this->defaultExpireRange = [$min, $max];
        return $this;
    }

    /**
     * 获取带有随机过期时间的值（防止缓存雪崩）
     *
     * @param int $expire 过期时间
     * @return int
     */
    public function getRandomExpire(int $expire = 0): int
    {
        if ($expire <= 0) {
            return $expire;
        }
        
        // 在过期时间基础上增加随机值，防止同一时间大量缓存过期
        $min = (int)($expire * 0.8);
        $max = (int)($expire * 1.2);
        
        return mt_rand($min, $max);
    }

    /**
     * 生成分布式锁的键名
     *
     * @param string $key 原始键名
     * @return string
     */
    protected function getLockKey(string $key): string
    {
        return $this->lockPrefix . $key;
    }

    /**
     * 尝试获取分布式锁
     *
     * @param string $key 锁的键名
     * @param int $ttl 锁的生存时间（秒）
     * @param string $value 锁的值，默认为随机字符串
     * @return bool
     */
    public function acquireLock(string $key, int $ttl = 10, string $value = ''): bool
    {
        $lockKey = $this->getLockKey($key);
        $value = $value ?: uniqid('lock_', true);
        
        // 使用 SET key value NX EX seconds 命令尝试获取锁
        return (bool)$this->redis->set($lockKey, $value, ['nx', 'ex' => $ttl]);
    }

    /**
     * 释放分布式锁
     *
     * @param string $key 锁的键名
     * @return bool
     */
    public function releaseLock(string $key): bool
    {
        $lockKey = $this->getLockKey($key);
        return (bool)$this->redis->del($lockKey);
    }

    /**
     * 获取空值过期时间
     *
     * @return int
     */
    public function getEmptyValueExpire(): int
    {
        return $this->emptyValueExpire;
    }

    /**
     * 获取String类型服务
     *
     * @return StringService
     */
    public function string(): StringService
    {
        if (!isset($this->services['string'])) {
            $this->services['string'] = new StringService($this);
        }
        
        return $this->services['string'];
    }

    /**
     * 获取Hash类型服务
     *
     * @return HashService
     */
    public function hash(): HashService
    {
        if (!isset($this->services['hash'])) {
            $this->services['hash'] = new HashService($this);
        }
        
        return $this->services['hash'];
    }

    /**
     * 获取List类型服务
     *
     * @return ListService
     */
    public function list(): ListService
    {
        if (!isset($this->services['list'])) {
            $this->services['list'] = new ListService($this);
        }
        
        return $this->services['list'];
    }

    /**
     * 获取Set类型服务
     *
     * @return SetService
     */
    public function set(): SetService
    {
        if (!isset($this->services['set'])) {
            $this->services['set'] = new SetService($this);
        }
        
        return $this->services['set'];
    }

    /**
     * 获取Sorted Set类型服务
     *
     * @return ZSetService
     */
    public function zset(): ZSetService
    {
        if (!isset($this->services['zset'])) {
            $this->services['zset'] = new ZSetService($this);
        }
        
        return $this->services['zset'];
    }

    /**
     * 获取Geo类型服务
     *
     * @return GeoService
     */
    public function geo(): GeoService
    {
        if (!isset($this->services['geo'])) {
            $this->services['geo'] = new GeoService($this);
        }
        
        return $this->services['geo'];
    }

    /**
     * 获取HyperLogLog类型服务
     *
     * @return HyperLogLogService
     */
    public function hyperLogLog(): HyperLogLogService
    {
        if (!isset($this->services['hyperloglog'])) {
            $this->services['hyperloglog'] = new HyperLogLogService($this);
        }
        
        return $this->services['hyperloglog'];
    }

    /**
     * 获取BitMap类型服务
     *
     * @return BitMapService
     */
    public function bitmap(): BitMapService
    {
        if (!isset($this->services['bitmap'])) {
            $this->services['bitmap'] = new BitMapService($this);
        }
        
        return $this->services['bitmap'];
    }
} 