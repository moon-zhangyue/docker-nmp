<?php
declare(strict_types=1);

namespace app\cs\service;

/**
 * WebSocket 一次性接入票据
 *
 * 浏览器 WebSocket API 无法携带自定义 Header，令牌若直接拼在 URL 上会进入
 * 访问日志/浏览器历史，且长期有效。因此改为：
 *   1. HTTP 侧完成原有鉴权（会话签名令牌 / 后台签名 Cookie）后签发一次性票据；
 *   2. WS 侧用 Lua 原子 GET+DEL 消费，60 秒内有效且只能用一次。
 * 票据本身不含用户身份，只声明作用域与会话 id，泄露后也无法复用。
 */
class WsTicket
{
    /** 票据有效期（秒） */
    public const TTL = 60;

    /** 票据存放在 Redis 的键前缀（Kv 会再拼 cs: 全局前缀） */
    private const KEY_PREFIX = 'ws:ticket:';

    public static function issueSession(string $sessionId): string
    {
        return self::issue(['scope' => 'session', 'sid' => $sessionId]);
    }

    public static function issueAdmin(): string
    {
        return self::issue(['scope' => 'admin']);
    }

    /** 签发票据；Redis 不可用时返回空串，由调用方转成 503 */
    private static function issue(array $payload): string
    {
        $ticket = bin2hex(random_bytes(24));
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return '';
        }
        return Kv::setRaw(self::KEY_PREFIX . $ticket, $json, self::TTL) ? $ticket : '';
    }
}
