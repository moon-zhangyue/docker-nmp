<?php
declare(strict_types=1);

namespace app\process;

use app\service\Hub;
use app\service\Logger;
use app\service\RedisConn;
use app\service\StreamPoller;
use app\service\Ticket;
use Workerman\Connection\TcpConnection;
use Workerman\Timer;
use Workerman\Worker;

/**
 * WebSocket 服务进程（常驻，Workerman 事件循环）。
 *
 * 只做三件事：握手校验、票据鉴权、把 Redis Stream 中的事件分发给本进程连接。
 * 不读写 MySQL、不生成回复，业务逻辑唯一来源仍是 TP8，避免两套实现漂移。
 */
class WsServer
{
    public function onWorkerStart(Worker $worker): void
    {
        RedisConn::init();
        StreamPoller::boot();
        Timer::add(1, [Hub::class, 'sweep']);
        Logger::info('ws worker started, listen=' . $worker->getSocketName());
    }

    public function onWorkerStop(Worker $worker): void
    {
        Logger::info('ws worker stopped');
    }

    /** 握手阶段校验 Origin，防止任意站点借用用户凭证建立连接 */
    public function onWebSocketConnect(TcpConnection $conn, $request): void
    {
        $origin = (string) ($request->header('origin') ?? '');
        $allowed = (array) config('ws.origins');
        if ($allowed && !in_array($origin, $allowed, true)) {
            Logger::warn('handshake rejected origin=' . $origin . ' ip=' . $conn->getRemoteIp());
            $conn->close("HTTP/1.1 403 Forbidden\r\nContent-Type: text/plain\r\n\r\norigin not allowed", true);
            return;
        }
        if (!Hub::open($conn)) {
            Logger::warn('connection rejected by limit ip=' . $conn->getRemoteIp());
            $conn->close("HTTP/1.1 429 Too Many Requests\r\nContent-Type: text/plain\r\n\r\ntoo many connections", true);
        }
    }

    public function onMessage(TcpConnection $conn, $data): void
    {
        Hub::touch($conn);

        if (!Hub::allowFrame($conn)) {
            $this->reject($conn, 'rate_limited');
            return;
        }

        $msg = json_decode((string) $data, true);
        if (!is_array($msg)) {
            $this->reject($conn, 'bad_message');
            return;
        }

        $meta = Hub::meta($conn);
        if ($meta === null) {
            $conn->close();
            return;
        }

        $type = (string) ($msg['type'] ?? '');

        if ($type === 'auth') {
            if ($meta['scope'] !== 'anon') {
                $this->reject($conn, 'already_authenticated');
                return;
            }
            $grant = Ticket::consume((string) ($msg['ticket'] ?? ''));
            if ($grant === null) {
                Logger::warn('auth failed ip=' . $meta['ip']);
                $this->reject($conn, 'auth_failed');
                return;
            }
            if (!Hub::authenticate($conn, $grant['scope'], $grant['sid'])) {
                $conn->close();
                return;
            }
            if ($grant['scope'] === 'session') {
                StreamPoller::ensureSession($grant['sid']);
            }
            $conn->send(json_encode([
                'type'  => 'auth_ok',
                'scope' => $grant['scope'],
            ], JSON_UNESCAPED_UNICODE));
            return;
        }

        // 已鉴权连接只允许心跳，其余一律断开（降低被当作注入通道的风险）
        if ($type === 'ping' && $meta['scope'] !== 'anon') {
            $conn->send('{"type":"pong"}');
            return;
        }

        $this->reject($conn, 'unknown_type');
    }

    public function onClose(TcpConnection $conn): void
    {
        Hub::close($conn);
    }

    private function reject(TcpConnection $conn, string $reason): void
    {
        $conn->send(json_encode(['type' => 'error', 'msg' => $reason], JSON_UNESCAPED_UNICODE));
        $conn->close();
    }
}
