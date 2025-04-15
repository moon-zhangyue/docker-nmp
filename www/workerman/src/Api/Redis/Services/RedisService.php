<?php

namespace App\Api\Redis\Services;

use App\Api\Redis\Interfaces\RedisServiceInterface;
use Throwable;
use Workerman\Worker;
use Workerman\Redis\Client;
use Workerman\Connection\TcpConnection;
use App\Core\Http\JsonResponse;

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
            $address = 'redis://';
            if (!empty($this->config['password'])) {
                $address .= urlencode($this->config['password']) . '@';
            }
            $address .= $this->config['host'] . ':' . $this->config['port'];
            
            Worker::log("正在连接Redis: {$address}");
            $this->redis = new Client($address);
        } catch (Throwable $e) {
            Worker::log("Redis连接失败: " . $e->getMessage());
            $this->redis = null;
        }
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, mixed $value, ?int $ttl = null, TcpConnection $connection = null): void
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
                $this->redis->set($key, (string)$value)->then(
                    function ($result) use ($key, $value, $connection) {
                        Worker::log("Redis SET操作成功 - key:{$key}, value:{$value}");
                        if ($connection) {
                            JsonResponse::success($connection);
                        }
                    },
                    function ($e) use ($key, $connection) {
                        Worker::log("Redis SET操作失败 - key:{$key} - {$e->getMessage()}");
                        if ($connection) {
                            JsonResponse::error($connection, $e->getMessage());
                        }
                    }
                );
            } else {
                $this->redis->setex($key, $ttl, (string)$value)->then(
                    function ($result) use ($key, $value, $ttl, $connection) {
                        Worker::log("Redis SET操作成功 - key:{$key}, value:{$value}, ttl:{$ttl}");
                        if ($connection) {
                            JsonResponse::success($connection);
                        }
                    },
                    function ($e) use ($key, $connection) {
                        Worker::log("Redis SET操作失败 - key:{$key} - {$e->getMessage()}");
                        if ($connection) {
                            JsonResponse::error($connection, $e->getMessage());
                        }
                    }
                );
            }
        } catch (Throwable $e) {
            Worker::log("Redis SET操作失败 - key:{$key} - {$e->getMessage()}");
            if ($connection) {
                JsonResponse::error($connection, $e->getMessage());
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function get(string $key, TcpConnection $connection = null): void
    {
        try {
            if (!$this->redis) {
                Worker::log("Redis未连接，尝试重新连接");
                $this->connect();
                if (!$this->redis) {
                    throw new \Exception("Redis连接失败");
                }
            }

            $this->redis->get($key)->then(
                function ($value) use ($key, $connection) {
                    Worker::log("Redis GET操作成功 - key:{$key}, value:" . ($value === null ? "null" : $value));
                    if ($connection) {
                        JsonResponse::success($connection, ['value' => $value]);
                    }
                },
                function ($e) use ($key, $connection) {
                    Worker::log("Redis GET操作失败 - key:{$key} - {$e->getMessage()}");
                    if ($connection) {
                        JsonResponse::error($connection, $e->getMessage());
                    }
                }
            );
        } catch (Throwable $e) {
            Worker::log("Redis GET操作失败 - key:{$key} - {$e->getMessage()}");
            if ($connection) {
                JsonResponse::error($connection, $e->getMessage());
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function hSet(string $key, string $field, mixed $value, TcpConnection $connection = null): void
    {
        try {
            if (!$this->redis) {
                Worker::log("Redis未连接，尝试重新连接");
                $this->connect();
                if (!$this->redis) {
                    throw new \Exception("Redis连接失败");
                }
            }

            $this->redis->hSet($key, $field, (string)$value)->then(
                function ($result) use ($key, $field, $value, $connection) {
                    Worker::log("Redis HSET操作成功 - key:{$key}, field:{$field}, value:{$value}");
                    if ($connection) {
                        JsonResponse::success($connection);
                    }
                },
                function ($e) use ($key, $connection) {
                    Worker::log("Redis HSET操作失败 - key:{$key} - {$e->getMessage()}");
                    if ($connection) {
                        JsonResponse::error($connection, $e->getMessage());
                    }
                }
            );
        } catch (Throwable $e) {
            Worker::log("Redis HSET操作失败 - key:{$key} - {$e->getMessage()}");
            if ($connection) {
                JsonResponse::error($connection, $e->getMessage());
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function lPush(string $key, mixed $value, TcpConnection $connection = null): void
    {
        try {
            if (!$this->redis) {
                Worker::log("Redis未连接，尝试重新连接");
                $this->connect();
                if (!$this->redis) {
                    throw new \Exception("Redis连接失败");
                }
            }

            $this->redis->lPush($key, (string)$value)->then(
                function ($result) use ($key, $value, $connection) {
                    Worker::log("Redis LPUSH操作成功 - key:{$key}, value:{$value}");
                    if ($connection) {
                        JsonResponse::success($connection);
                    }
                },
                function ($e) use ($key, $connection) {
                    Worker::log("Redis LPUSH操作失败 - key:{$key} - {$e->getMessage()}");
                    if ($connection) {
                        JsonResponse::error($connection, $e->getMessage());
                    }
                }
            );
        } catch (Throwable $e) {
            Worker::log("Redis LPUSH操作失败 - key:{$key} - {$e->getMessage()}");
            if ($connection) {
                JsonResponse::error($connection, $e->getMessage());
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function sAdd(string $key, mixed $member, TcpConnection $connection = null): void
    {
        try {
            if (!$this->redis) {
                Worker::log("Redis未连接，尝试重新连接");
                $this->connect();
                if (!$this->redis) {
                    throw new \Exception("Redis连接失败");
                }
            }

            $this->redis->sAdd($key, (string)$member)->then(
                function ($result) use ($key, $member, $connection) {
                    Worker::log("Redis SADD操作成功 - key:{$key}, member:{$member}");
                    if ($connection) {
                        JsonResponse::success($connection);
                    }
                },
                function ($e) use ($key, $connection) {
                    Worker::log("Redis SADD操作失败 - key:{$key} - {$e->getMessage()}");
                    if ($connection) {
                        JsonResponse::error($connection, $e->getMessage());
                    }
                }
            );
        } catch (Throwable $e) {
            Worker::log("Redis SADD操作失败 - key:{$key} - {$e->getMessage()}");
            if ($connection) {
                JsonResponse::error($connection, $e->getMessage());
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function zAdd(string $key, float $score, mixed $member, TcpConnection $connection = null): void
    {
        try {
            if (!$this->redis) {
                Worker::log("Redis未连接，尝试重新连接");
                $this->connect();
                if (!$this->redis) {
                    throw new \Exception("Redis连接失败");
                }
            }

            $this->redis->zAdd($key, $score, (string)$member)->then(
                function ($result) use ($key, $score, $member, $connection) {
                    Worker::log("Redis ZADD操作成功 - key:{$key}, score:{$score}, member:{$member}");
                    if ($connection) {
                        JsonResponse::success($connection);
                    }
                },
                function ($e) use ($key, $connection) {
                    Worker::log("Redis ZADD操作失败 - key:{$key} - {$e->getMessage()}");
                    if ($connection) {
                        JsonResponse::error($connection, $e->getMessage());
                    }
                }
            );
        } catch (Throwable $e) {
            Worker::log("Redis ZADD操作失败 - key:{$key} - {$e->getMessage()}");
            if ($connection) {
                JsonResponse::error($connection, $e->getMessage());
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function del(string $key, TcpConnection $connection = null): void
    {
        try {
            if (!$this->redis) {
                Worker::log("Redis未连接，尝试重新连接");
                $this->connect();
                if (!$this->redis) {
                    throw new \Exception("Redis连接失败");
                }
            }

            $this->redis->del($key)->then(
                function ($result) use ($key, $connection) {
                    Worker::log("Redis DEL操作成功 - key:{$key}");
                    if ($connection) {
                        JsonResponse::success($connection);
                    }
                },
                function ($e) use ($key, $connection) {
                    Worker::log("Redis DEL操作失败 - key:{$key} - {$e->getMessage()}");
                    if ($connection) {
                        JsonResponse::error($connection, $e->getMessage());
                    }
                }
            );
        } catch (Throwable $e) {
            Worker::log("Redis DEL操作失败 - key:{$key} - {$e->getMessage()}");
            if ($connection) {
                JsonResponse::error($connection, $e->getMessage());
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function expire(string $key, int $ttl, TcpConnection $connection = null): void
    {
        try {
            if (!$this->redis) {
                Worker::log("Redis未连接，尝试重新连接");
                $this->connect();
                if (!$this->redis) {
                    throw new \Exception("Redis连接失败");
                }
            }

            $this->redis->expire($key, $ttl)->then(
                function ($result) use ($key, $ttl, $connection) {
                    Worker::log("Redis EXPIRE操作成功 - key:{$key}, ttl:{$ttl}");
                    if ($connection) {
                        JsonResponse::success($connection);
                    }
                },
                function ($e) use ($key, $connection) {
                    Worker::log("Redis EXPIRE操作失败 - key:{$key} - {$e->getMessage()}");
                    if ($connection) {
                        JsonResponse::error($connection, $e->getMessage());
                    }
                }
            );
        } catch (Throwable $e) {
            Worker::log("Redis EXPIRE操作失败 - key:{$key} - {$e->getMessage()}");
            if ($connection) {
                JsonResponse::error($connection, $e->getMessage());
            }
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