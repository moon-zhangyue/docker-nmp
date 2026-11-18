<?php
declare(strict_types=1);

namespace app\service;

use Throwable;

/**
 * 一次性接入票据消费端。
 * 与 TP8 侧 app\cs\service\WsTicket 成对：那边 setex，这边 Lua 原子 GET+DEL。
 * 用 Lua 而不是 GET 后 DEL，避免并发下同一张票被两个连接同时兑换。
 */
final class Ticket
{
    private const LUA_CONSUME = "local v = redis.call('GET', KEYS[1]); if v then redis.call('DEL', KEYS[1]) end; return v";

    /**
     * @return array{scope:string, sid:string}|null 消费失败返回 null
     */
    public static function consume(string $ticket): ?array
    {
        if (!preg_match('/^[a-f0-9]{48}$/', $ticket)) {
            return null;
        }

        try {
            $raw = RedisConn::conn()->eval(self::LUA_CONSUME, [RedisConn::key('ws:ticket:' . $ticket)], 1);
        } catch (Throwable $e) {
            Logger::error('ticket consume failed: ' . $e->getMessage());
            RedisConn::reset();
            return null;
        }

        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return null;
        }

        $scope = (string) ($data['scope'] ?? '');
        if ($scope === 'admin') {
            return ['scope' => 'admin', 'sid' => ''];
        }

        $sid = (string) ($data['sid'] ?? '');
        // 会话 id 由服务端生成：S + 时间戳 + 随机十六进制
        if ($scope === 'session' && preg_match('/^S[0-9A-F]{15,40}$/', $sid)) {
            return ['scope' => 'session', 'sid' => $sid];
        }

        return null;
    }
}
