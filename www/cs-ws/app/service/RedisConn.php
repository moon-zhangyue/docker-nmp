<?php
declare(strict_types=1);

namespace app\service;

use Redis;

/**
 * WS 服务专用的 Redis 连接（phpredis，同步、无序列化）。
 *
 * 与 TP8 侧共享同一个库和键前缀，但不共享代码：WS 只读 Stream / 消费票据，
 * 不做任何业务写入，避免两套业务逻辑。
 */
final class RedisConn
{
    private static ?Redis $conn = null;

    public static function init(): void
    {
        self::conn();
    }

    public static function conn(): Redis
    {
        if (self::$conn === null) {
            $c = (array) config('ws.redis');
            $r = new Redis();
            $r->connect((string) $c['host'], (int) $c['port'], (float) $c['timeout']);
            if (($c['pass'] ?? '') !== '') {
                $r->auth((string) $c['pass']);
            }
            $r->setOption(Redis::OPT_READ_TIMEOUT, (float) $c['timeout']);
            self::$conn = $r;
        }
        return self::$conn;
    }

    /** 拼接全局键前缀 */
    public static function key(string $logical): string
    {
        return (string) config('ws.redis.prefix') . $logical;
    }

    /**
     * 丢弃当前连接，下次调用时重建。
     * phpredis 在读超时/连接中断后不会自动恢复，必须在异常路径显式重置，
     * 否则轮询器会一直报 read error。
     */
    public static function reset(): void
    {
        if (self::$conn !== null) {
            try {
                self::$conn->close();
            } catch (\Throwable $e) {
                // 忽略：连接已不可用
            }
        }
        self::$conn = null;
    }
}
