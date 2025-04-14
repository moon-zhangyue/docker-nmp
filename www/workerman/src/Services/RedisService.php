<?php

namespace App\Services;

use Throwable;
use Workerman\Worker;
use Workerman\Redis\Client;

/**
 * Redis服务类
 * 提供完整的Redis操作封装
 */
class RedisService
{
    private Client $redis;
    private array $config;

    /**
     * RedisService constructor.
     * @param array $config Redis配置
     * @throws Throwable
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->connect();
    }

    /**
     * 连接Redis
     * @throws Throwable
     */
    private function connect(): void
    {
        try {
            $this->redis = new Client(
                'redis://' . 
                ($this->config['password'] ? urlencode($this->config['password']) . '@' : '') .
                $this->config['host'] . ':' . $this->config['port'] .
                ($this->config['database'] ? '/' . $this->config['database'] : '/0')
            );

            Worker::log("Redis连接成功 - {$this->config['host']}:{$this->config['port']}");
        } catch (Throwable $e) {
            Worker::log("Redis连接失败 - {$this->config['host']}:{$this->config['port']} - {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * 字符串操作 - 设置值
     * @param string $key 键
     * @param mixed $value 值
     * @param int|null $ttl 过期时间（秒）
     * @return void
     */
    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        try {
            if ($ttl === null) {
                $this->redis->set($key, (string)$value)->then(function ($result) use ($key, $value) {
                    Worker::log("Redis SET操作 - key:{$key}, value:{$value}");
                }, function ($e) use ($key) {
                    Worker::log("Redis SET操作失败 - key:{$key} - {$e->getMessage()}");
                });
            } else {
                $this->redis->setex($key, $ttl, (string)$value)->then(function ($result) use ($key, $value, $ttl) {
                    Worker::log("Redis SET操作 - key:{$key}, value:{$value}, ttl:{$ttl}");
                }, function ($e) use ($key) {
                    Worker::log("Redis SET操作失败 - key:{$key} - {$e->getMessage()}");
                });
            }
        } catch (Throwable $e) {
            Worker::log("Redis SET操作失败 - key:{$key} - {$e->getMessage()}");
        }
    }

    /**
     * 字符串操作 - 获取值
     * @param string $key 键
     * @return void
     */
    public function get(string $key): void
    {
        try {
            $this->redis->get($key)->then(function ($value) use ($key) {
                Worker::log("Redis GET操作 - key:{$key}, value:" . ($value === null ? "null" : $value));
            }, function ($e) use ($key) {
                Worker::log("Redis GET操作失败 - key:{$key} - {$e->getMessage()}");
            });
        } catch (Throwable $e) {
            Worker::log("Redis GET操作失败 - key:{$key} - {$e->getMessage()}");
        }
    }

    /**
     * 哈希表操作 - 设置字段值
     * @param string $key 键
     * @param string $field 字段
     * @param mixed $value 值
     * @return void
     */
    public function hSet(string $key, string $field, mixed $value): void
    {
        try {
            $this->redis->hSet($key, $field, (string)$value)->then(function ($result) use ($key, $field, $value) {
                Worker::log("Redis HSET操作 - key:{$key}, field:{$field}, value:{$value}");
            }, function ($e) use ($key, $field) {
                Worker::log("Redis HSET操作失败 - key:{$key}, field:{$field} - {$e->getMessage()}");
            });
        } catch (Throwable $e) {
            Worker::log("Redis HSET操作失败 - key:{$key}, field:{$field} - {$e->getMessage()}");
        }
    }

    /**
     * 列表操作 - 从左侧推入
     * @param string $key 键
     * @param mixed $value 值
     * @return void
     */
    public function lPush(string $key, mixed $value): void
    {
        try {
            $this->redis->lPush($key, (string)$value)->then(function ($result) use ($key, $value) {
                Worker::log("Redis LPUSH操作 - key:{$key}, value:{$value}");
            }, function ($e) use ($key) {
                Worker::log("Redis LPUSH操作失败 - key:{$key} - {$e->getMessage()}");
            });
        } catch (Throwable $e) {
            Worker::log("Redis LPUSH操作失败 - key:{$key} - {$e->getMessage()}");
        }
    }

    /**
     * 集合操作 - 添加成员
     * @param string $key 键
     * @param mixed $member 成员
     * @return void
     */
    public function sAdd(string $key, mixed $member): void
    {
        try {
            $this->redis->sAdd($key, (string)$member)->then(function ($result) use ($key, $member) {
                Worker::log("Redis SADD操作 - key:{$key}, member:{$member}");
            }, function ($e) use ($key) {
                Worker::log("Redis SADD操作失败 - key:{$key} - {$e->getMessage()}");
            });
        } catch (Throwable $e) {
            Worker::log("Redis SADD操作失败 - key:{$key} - {$e->getMessage()}");
        }
    }

    /**
     * 有序集合操作 - 添加成员
     * @param string $key 键
     * @param float $score 分数
     * @param mixed $member 成员
     * @return void
     */
    public function zAdd(string $key, float $score, mixed $member): void
    {
        try {
            $this->redis->zAdd($key, $score, (string)$member)->then(function ($result) use ($key, $score, $member) {
                Worker::log("Redis ZADD操作 - key:{$key}, score:{$score}, member:{$member}");
            }, function ($e) use ($key) {
                Worker::log("Redis ZADD操作失败 - key:{$key} - {$e->getMessage()}");
            });
        } catch (Throwable $e) {
            Worker::log("Redis ZADD操作失败 - key:{$key} - {$e->getMessage()}");
        }
    }

    /**
     * 删除键
     * @param string $key 键
     * @return void
     */
    public function del(string $key): void
    {
        try {
            $this->redis->del($key)->then(function ($result) use ($key) {
                Worker::log("Redis DEL操作 - key:{$key}");
            }, function ($e) use ($key) {
                Worker::log("Redis DEL操作失败 - key:{$key} - {$e->getMessage()}");
            });
        } catch (Throwable $e) {
            Worker::log("Redis DEL操作失败 - key:{$key} - {$e->getMessage()}");
        }
    }

    /**
     * 设置过期时间
     * @param string $key 键
     * @param int $ttl 过期时间（秒）
     * @return void
     */
    public function expire(string $key, int $ttl): void
    {
        try {
            $this->redis->expire($key, $ttl)->then(function ($result) use ($key, $ttl) {
                Worker::log("Redis EXPIRE操作 - key:{$key}, ttl:{$ttl}");
            }, function ($e) use ($key) {
                Worker::log("Redis EXPIRE操作失败 - key:{$key} - {$e->getMessage()}");
            });
        } catch (Throwable $e) {
            Worker::log("Redis EXPIRE操作失败 - key:{$key} - {$e->getMessage()}");
        }
    }

    /**
     * 获取Redis客户端实例
     * @return Client
     */
    public function getRedis(): Client
    {
        return $this->redis;
    }
} 