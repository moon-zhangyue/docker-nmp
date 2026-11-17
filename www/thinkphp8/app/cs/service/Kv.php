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

    public static function incr(string $k, int $by = 1): int
    {
        try {
            $r = self::redis();
            $r->incrBy(self::key($k), $by);
            return (int) $r->get(self::key($k));
        } catch (RedisException $e) {
            return 0;
        }
    }

    /** 向列表右侧追加 */
    public static function rpush(string $k, $v): int
    {
        try {
            return (int) self::redis()->rPush(self::key($k), $v);
        } catch (RedisException $e) {
            return 0;
        }
    }

    public static function lrange(string $k, int $start, int $end): array
    {
        try {
            return self::redis()->lRange(self::key($k), $start, $end) ?: [];
        } catch (RedisException $e) {
            return [];
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
}
