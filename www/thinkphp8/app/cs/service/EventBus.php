<?php
declare(strict_types=1);

namespace app\cs\service;

/**
 * 实时事件总线（Redis Stream）
 * 会话流：cs:events:session:{id}  管理流：cs:events:admin
 *
 * 仅作实时通道：由独立的 cs-ws（webman/Workerman）服务消费并推送给浏览器，
 * 不替代 MySQL 持久化；Stream 保留最近 500 条，便于 WS 重启后按游标续推。
 */
class EventBus
{
    /**
     * 推送给某个会话的浏览器端。
     * 只用于"用户当前页面不知道、需要即时追加"的事件，例如人工客服回复。
     */
    public static function session(string $sessionId, string $type, array $data): void
    {
        self::push('events:session:' . $sessionId, $type, $data);
    }

    /** 推送给管理后台（所有后台标签页可见） */
    public static function admin(string $type, array $data): void
    {
        self::push('events:admin', $type, $data);
    }

    private static function push(string $stream, string $type, array $data): void
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return;
        }
        Kv::xadd($stream, ['type' => $type, 'data' => $json], 500);
    }
}
