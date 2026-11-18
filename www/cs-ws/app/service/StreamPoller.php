<?php
declare(strict_types=1);

namespace app\service;

use Throwable;
use Workerman\Timer;

/**
 * Redis Stream 轮询器。
 *
 * 选型说明：WS 进程需要"业务侧写入 → 实时下发"，但不引入 Pub/Sub 阻塞订阅，
 * 也不额外依赖异步 Redis 客户端。改用每 100ms 一次的非阻塞 XREAD：
 *   - 延迟 ≤100ms，客服对话场景无感；
 *   - Stream 有 MAXLEN 上限，内存可控；
 *   - WS 进程重启后从游标继续，不会丢未推送事件；
 *   - 每进程独立消费，多实例部署时每个实例只推自己的连接，不会重复打扰用户。
 */
final class StreamPoller
{
    private const ADMIN_STREAM = 'events:admin';
    private const SESSION_PREFIX = 'events:session:';

    /** @var array<string, string> sid => 游标 */
    private static array $sidCursor = [];

    private static string $adminCursor = '0-0';

    /** 后台是否处于"有连接"状态，用于 0→1 时跳过错过的历史事件 */
    private static bool $adminActive = false;

    private static bool $started = false;

    public static function boot(): void
    {
        if (self::$started) {
            return;
        }
        self::$started = true;

        self::$adminCursor = self::tail(self::ADMIN_STREAM);

        $interval = max(0.05, ((int) config('ws.poll_interval_ms')) / 1000);
        Timer::add($interval, [self::class, 'tick']);
    }

    /** 会话首连接建立时初始化游标：只接收此后产生的事件，避免重放历史 */
    public static function ensureSession(string $sid): void
    {
        if (!isset(self::$sidCursor[$sid])) {
            self::$sidCursor[$sid] = self::tail(self::SESSION_PREFIX . $sid);
        }
    }

    public static function tick(): void
    {
        $streams = [];
        $ids = [];

        if (Hub::adminCount() > 0) {
            // 从无人监听到有人监听：直接跳到流末尾，避免把断线期间的历史事件
            // 一次性重放给新打开的后台页面（前端会自行拉取列表补齐）
            if (!self::$adminActive) {
                self::$adminCursor = self::tail(self::ADMIN_STREAM);
                self::$adminActive = true;
            }
            $streams[] = RedisConn::key(self::ADMIN_STREAM);
            $ids[] = self::$adminCursor;
        } else {
            self::$adminActive = false;
        }

        foreach (self::$sidCursor as $sid => $cursor) {
            if (Hub::sessionCount($sid) === 0) {
                unset(self::$sidCursor[$sid]);
                continue;
            }
            $streams[] = RedisConn::key(self::SESSION_PREFIX . $sid);
            $ids[] = $cursor;
        }

        if (!$streams) {
            return;
        }

        try {
            // 注意：phpredis 的 block 参数为 0 时对应 Redis 的 BLOCK 0（永久阻塞），
            // 必须传 -1 才是"不带 BLOCK 子句"的非阻塞读取。
            $result = RedisConn::conn()->xRead(array_combine($streams, $ids), (int) config('ws.poll_batch'), -1);
        } catch (Throwable $e) {
            Logger::error('xread failed: ' . $e->getMessage());
            RedisConn::reset();
            return;
        }
        if (!is_array($result)) {
            return;
        }

        $prefixLen = strlen((string) config('ws.redis.prefix'));

        foreach ($result as $key => $entries) {
            if (!is_array($entries)) {
                continue;
            }
            $logical = substr((string) $key, $prefixLen);

            foreach ($entries as $id => $fields) {
                $id = (string) $id;
                if ($logical === self::ADMIN_STREAM) {
                    self::$adminCursor = $id;
                } else {
                    self::$sidCursor[substr($logical, strlen(self::SESSION_PREFIX))] = $id;
                }

                $payload = self::payload($id, is_array($fields) ? $fields : []);
                if ($payload === null) {
                    continue;
                }

                if ($logical === self::ADMIN_STREAM) {
                    Hub::pushAdmin($payload);
                } else {
                    Hub::pushSession(substr($logical, strlen(self::SESSION_PREFIX)), $payload);
                }
            }
        }
    }

    private static function payload(string $id, array $fields): ?array
    {
        $type = (string) ($fields['type'] ?? '');
        if ($type === '') {
            return null;
        }
        $data = json_decode((string) ($fields['data'] ?? ''), true);
        return [
            'type'  => 'event',
            'event' => $type,
            'data'  => is_array($data) ? $data : [],
            'id'    => $id,
        ];
    }

    /** 取流末尾 ID，作为"只收新事件"的起点 */
    private static function tail(string $logical): string
    {
        try {
            $rows = RedisConn::conn()->xRevRange(RedisConn::key($logical), '+', '-', 1);
        } catch (Throwable $e) {
            Logger::error('xrevrange failed: ' . $e->getMessage());
            RedisConn::reset();
            return '0-0';
        }
        if (!is_array($rows) || !$rows) {
            return '0-0';
        }
        $id = array_key_first($rows);
        return is_string($id) ? $id : '0-0';
    }
}
