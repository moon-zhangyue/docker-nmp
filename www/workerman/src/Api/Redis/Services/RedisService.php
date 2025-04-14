<?php

namespace App\Api\Redis\Services;

use App\Api\Redis\Interfaces\RedisServiceInterface;
use Throwable;
use Workerman\Worker;
use Workerman\Redis\Client;

/**
 * Redis服务实现类
 */
class RedisService implements RedisServiceInterface
{
    private ?Client $redis = null;
    private array $config;

    /**
     * RedisService constructor.
     * @param array $config Redis配置
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->connect();
    }

    /**
     * 连接Redis
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
            $this->redis = null;
        }
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        try {
            if (!$this->redis) {
                Worker::log("Redis未连接，尝试重新连接");
                $this->connect();
                if (!$this->redis) {
                    throw new \Exception("Redis连接失败");
                }
            }

            if ($ttl === null) {
                $this->redis->set($key, (string)$value);
                Worker::log("Redis SET操作 - key:{$key}, value:{$value}");
            } else {
                $this->redis->setex($key, $ttl, (string)$value);
                Worker::log("Redis SET操作 - key:{$key}, value:{$value}, ttl:{$ttl}");
            }
        } catch (Throwable $e) {
            Worker::log("Redis SET操作失败 - key:{$key} - {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * @inheritDoc
     */
    public function get(string $key): mixed
    {
        try {
            if (!$this->redis) {
                Worker::log("Redis未连接，尝试重新连接");
                $this->connect();
                if (!$this->redis) {
                    throw new \Exception("Redis连接失败");
                }
            }

            $value = $this->redis->get($key);
            Worker::log("Redis GET操作 - key:{$key}, value:" . ($value === null ? "null" : $value));
            return $value;
        } catch (Throwable $e) {
            Worker::log("Redis GET操作失败 - key:{$key} - {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * @inheritDoc
     */
    public function hSet(string $key, string $field, mixed $value): void
    {
        try {
            if (!$this->redis) {
                Worker::log("Redis未连接，尝试重新连接");
                $this->connect();
                if (!$this->redis) {
                    throw new \Exception("Redis连接失败");
                }
            }

            $this->redis->hSet($key, $field, (string)$value);
            Worker::log("Redis HSET操作 - key:{$key}, field:{$field}, value:{$value}");
        } catch (Throwable $e) {
            Worker::log("Redis HSET操作失败 - key:{$key}, field:{$field} - {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * @inheritDoc
     */
    public function lPush(string $key, mixed $value): void
    {
        try {
            if (!$this->redis) {
                Worker::log("Redis未连接，尝试重新连接");
                $this->connect();
                if (!$this->redis) {
                    throw new \Exception("Redis连接失败");
                }
            }

            $this->redis->lPush($key, (string)$value);
            Worker::log("Redis LPUSH操作 - key:{$key}, value:{$value}");
        } catch (Throwable $e) {
            Worker::log("Redis LPUSH操作失败 - key:{$key} - {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * @inheritDoc
     */
    public function sAdd(string $key, mixed $member): void
    {
        try {
            if (!$this->redis) {
                Worker::log("Redis未连接，尝试重新连接");
                $this->connect();
                if (!$this->redis) {
                    throw new \Exception("Redis连接失败");
                }
            }

            $this->redis->sAdd($key, (string)$member);
            Worker::log("Redis SADD操作 - key:{$key}, member:{$member}");
        } catch (Throwable $e) {
            Worker::log("Redis SADD操作失败 - key:{$key} - {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * @inheritDoc
     */
    public function zAdd(string $key, float $score, mixed $member): void
    {
        try {
            if (!$this->redis) {
                Worker::log("Redis未连接，尝试重新连接");
                $this->connect();
                if (!$this->redis) {
                    throw new \Exception("Redis连接失败");
                }
            }

            $this->redis->zAdd($key, $score, (string)$member);
            Worker::log("Redis ZADD操作 - key:{$key}, score:{$score}, member:{$member}");
        } catch (Throwable $e) {
            Worker::log("Redis ZADD操作失败 - key:{$key} - {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * @inheritDoc
     */
    public function del(string $key): void
    {
        try {
            if (!$this->redis) {
                Worker::log("Redis未连接，尝试重新连接");
                $this->connect();
                if (!$this->redis) {
                    throw new \Exception("Redis连接失败");
                }
            }

            $this->redis->del($key);
            Worker::log("Redis DEL操作 - key:{$key}");
        } catch (Throwable $e) {
            Worker::log("Redis DEL操作失败 - key:{$key} - {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * @inheritDoc
     */
    public function expire(string $key, int $ttl): void
    {
        try {
            if (!$this->redis) {
                Worker::log("Redis未连接，尝试重新连接");
                $this->connect();
                if (!$this->redis) {
                    throw new \Exception("Redis连接失败");
                }
            }

            $this->redis->expire($key, $ttl);
            Worker::log("Redis EXPIRE操作 - key:{$key}, ttl:{$ttl}");
        } catch (Throwable $e) {
            Worker::log("Redis EXPIRE操作失败 - key:{$key} - {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * 获取Redis客户端实例
     * @return Client|null
     */
    public function getRedis(): ?Client
    {
        if (!$this->redis) {
            $this->connect();
        }
        return $this->redis;
    }
} 