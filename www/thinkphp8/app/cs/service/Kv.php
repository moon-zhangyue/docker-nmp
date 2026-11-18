<?php

declare(strict_types=1);

namespace app\cs\service;

use Redis;
use RedisException;

/**
 * Redis 缓存层：会话上下文、FAQ缓存、统计计数、限流
 */
class Kv
{
    private static ?Redis $redis = null;
    private static ?Redis $rawRedis = null;

    /**
     * 不启用序列化的连接，专供 INCR / 限流等数值操作。
     * PHP 序列化连接下 INCRBY 写入的是纯数字字符串，再用 get() 读取会被
     * unserialize 成 false，导致计数读取恒为 0。
     */
    private static function raw(): Redis
    {
        if (self::$rawRedis === null) {
            $c = Config::redis();
            $r = new Redis();
            $r->connect($c['host'], $c['port'], 2.0);
            if ($c['pass'] !== '') {
                $r->auth($c['pass']);
            }
            if ($c['select'] > 0) {
                $r->select($c['select']);
            }
            self::$rawRedis = $r;
        }
        return self::$rawRedis;
    }

    public static function redis(): Redis
    {
        if (self::$redis === null) {
            $c = Config::redis();
            $r = new Redis();
            $r->connect($c['host'], $c['port'], 2.0);
            if ($c['pass'] !== '') {
                $r->auth($c['pass']);
            }
            if ($c['select'] > 0) {
                $r->select($c['select']);
            }
            $r->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
            self::$redis = $r;
        }
        return self::$redis;
    }

    private static function key(string $k): string
    {
        return Config::redisPrefix() . $k;
    }

    public static function get(string $k)
    {
        try {
            return self::redis()->get(self::key($k));
        } catch (RedisException $e) {
            return false;
        }
    }

    public static function set(string $k, $v, int $ttl = 0): bool
    {
        try {
            return $ttl > 0
                ? self::redis()->setex(self::key($k), $ttl, $v)
                : self::redis()->set(self::key($k), $v);
        } catch (RedisException $e) {
            return false;
        }
    }

    public static function del(string $k): bool
    {
        try {
            return (bool) self::redis()->del(self::key($k));
        } catch (RedisException $e) {
            return false;
        }
    }

    /**
     * 写入不经过 PHP 序列化的原始字符串（带 TTL）。
     * 供跨语言/跨进程读取的数据使用，例如 WebSocket 一次性票据：
     * WS 服务用 Lua 脚本 GET+DEL 原子消费，必须拿到纯 JSON 而不是序列化串。
     */
    public static function setRaw(string $k, string $value, int $ttl): bool
    {
        if ($ttl <= 0) {
            return false;
        }
        try {
            return (bool) self::raw()->setex(self::key($k), $ttl, $value);
        } catch (RedisException $e) {
            return false;
        }
    }

    public static function incr(string $k, int $by = 1): int
    {
        try {
            return (int) self::raw()->incrBy(self::key($k), $by);
        } catch (RedisException $e) {
            return 0;
        }
    }

    /** 固定窗口限流：窗口内不超过 limit 次返回 true */
    public static function rateLimit(string $k, int $limit, int $window): bool
    {
        try {
            $key = self::key('rl:' . $k);
            $r = self::raw();
            $n = (int) $r->incrBy($key, 1);
            if ($n === 1) {
                $r->expire($key, $window);
            }
            return $n <= $limit;
        } catch (RedisException $e) {
            // Redis 故障时不误伤正常流量
            return true;
        }
    }

    public static function expire(string $k, int $ttl): bool
    {
        try {
            return (bool) self::redis()->expire(self::key($k), $ttl);
        } catch (RedisException $e) {
            return false;
        }
    }

    /**
     * 追加一条 Redis Stream 记录（不走 PHP 序列化）。
     * 消费端为独立的 cs-ws 服务，此处只负责写入。
     * @return string 条目 ID，失败返回空串
     */
    public static function xadd(string $k, array $fields, int $maxLen = 500): string
    {
        try {
            $id = self::raw()->xAdd(self::key($k), '*', $fields, $maxLen, true);
            return is_string($id) ? $id : '';
        } catch (RedisException $e) {
            return '';
        }
    }
}
