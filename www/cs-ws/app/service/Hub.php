<?php
declare(strict_types=1);

namespace app\service;

use Throwable;
use Workerman\Connection\TcpConnection;

/**
 * 连接注册表与消息分发。
 *
 * 所有连接状态集中在此，避免在 TcpConnection 上挂动态属性（PHP 8.2+ 弃用）。
 * 分发只写"自己的"连接，天然支持多进程横向扩展：每个进程只持有本进程连接。
 */
final class Hub
{
    /** @var array<int, array{conn:TcpConnection,scope:string,sid:string,ip:string,authedAt:int,lastSeen:int,deadline:int,win:int,frames:int}> */
    private static array $conns = [];

    /** @var array<string, int> 已鉴权会话连接数 */
    private static array $sids = [];

    private static int $admins = 0;

    /** 新连接登记，超过全局/单 IP 上限直接拒绝 */
    public static function open(TcpConnection $conn): bool
    {
        $id = spl_object_id($conn);
        if (isset(self::$conns[$id])) {
            return true;
        }
        if (count(self::$conns) >= (int) config('ws.max_conn_total')) {
            return false;
        }

        $ip = (string) $conn->getRemoteIp();
        $limit = (int) config('ws.max_conn_per_ip');
        $sameIp = 0;
        foreach (self::$conns as $m) {
            if ($m['ip'] === $ip && ++$sameIp >= $limit) {
                return false;
            }
        }

        $now = time();
        self::$conns[$id] = [
            'conn'     => $conn,
            'scope'    => 'anon',
            'sid'      => '',
            'ip'       => $ip,
            'authedAt' => 0,
            'lastSeen' => $now,
            'deadline' => $now + (int) config('ws.auth_timeout'),
            'win'      => $now,
            'frames'   => 0,
        ];
        return true;
    }

    /** 票据兑换成功后绑定作用域 */
    public static function authenticate(TcpConnection $conn, string $scope, string $sid = ''): bool
    {
        $id = spl_object_id($conn);
        if (!isset(self::$conns[$id]) || self::$conns[$id]['scope'] !== 'anon') {
            return false;
        }
        self::$conns[$id]['scope'] = $scope;
        self::$conns[$id]['sid'] = $sid;
        self::$conns[$id]['authedAt'] = time();

        if ($scope === 'admin') {
            self::$admins++;
        } else {
            self::$sids[$sid] = (self::$sids[$sid] ?? 0) + 1;
        }
        return true;
    }

    public static function close(TcpConnection $conn): void
    {
        $id = spl_object_id($conn);
        if (!isset(self::$conns[$id])) {
            return;
        }
        $meta = self::$conns[$id];
        unset(self::$conns[$id]);

        if ($meta['scope'] === 'admin') {
            self::$admins = max(0, self::$admins - 1);
        } elseif ($meta['scope'] === 'session') {
            $sid = $meta['sid'];
            self::$sids[$sid] = max(0, (self::$sids[$sid] ?? 1) - 1);
            if (self::$sids[$sid] === 0) {
                unset(self::$sids[$sid]);
            }
        }
    }

    /** @return array{scope:string,sid:string,ip:string,lastSeen:int,deadline:int}|null */
    public static function meta(TcpConnection $conn): ?array
    {
        return self::$conns[spl_object_id($conn)] ?? null;
    }

    public static function touch(TcpConnection $conn): void
    {
        $id = spl_object_id($conn);
        if (isset(self::$conns[$id])) {
            self::$conns[$id]['lastSeen'] = time();
        }
    }

    /** 每连接每秒帧数限制 */
    public static function allowFrame(TcpConnection $conn): bool
    {
        $id = spl_object_id($conn);
        if (!isset(self::$conns[$id])) {
            return false;
        }
        $now = time();
        if (self::$conns[$id]['win'] !== $now) {
            self::$conns[$id]['win'] = $now;
            self::$conns[$id]['frames'] = 0;
        }
        self::$conns[$id]['frames']++;
        return self::$conns[$id]['frames'] <= (int) config('ws.max_frames_per_sec');
    }

    /**
     * 清理超时连接：未鉴权超时、心跳超时、发送缓冲积压（慢消费者）。
     * @return int 关闭数量
     */
    public static function sweep(): int
    {
        $now = time();
        $idle = (int) config('ws.idle_timeout');
        $closed = 0;

        foreach (self::$conns as $meta) {
            $conn = $meta['conn'];
            $reason = '';
            if ($meta['scope'] === 'anon' && $now > $meta['deadline']) {
                $reason = 'auth_timeout';
            } elseif ($now - $meta['lastSeen'] > $idle) {
                $reason = 'idle_timeout';
            } elseif ($conn->getSendBufferQueueSize() > 1024 * 1024) {
                $reason = 'slow_consumer';
            }
            if ($reason !== '') {
                Logger::warn("close conn ip={$meta['ip']} reason={$reason}");
                $conn->close();
                $closed++;
            }
        }
        return $closed;
    }

    public static function adminCount(): int
    {
        return self::$admins;
    }

    public static function sessionCount(string $sid): int
    {
        return self::$sids[$sid] ?? 0;
    }

    /** @return string[] */
    public static function activeSids(): array
    {
        return array_keys(self::$sids);
    }

    public static function total(): int
    {
        return count(self::$conns);
    }

    public static function pushSession(string $sid, array $payload): void
    {
        self::push([
            'scope' => 'session',
            'sid'   => $sid,
        ], $payload);
    }

    public static function pushAdmin(array $payload): void
    {
        self::push([
            'scope' => 'admin',
            'sid'   => '',
        ], $payload);
    }

    private static function push(array $match, array $payload): void
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return;
        }
        foreach (self::$conns as $meta) {
            if ($meta['scope'] !== $match['scope']) {
                continue;
            }
            if ($match['scope'] === 'session' && $meta['sid'] !== $match['sid']) {
                continue;
            }
            try {
                $meta['conn']->send($json);
            } catch (Throwable $e) {
                // 单个连接写失败不影响其他连接，由 onClose 统一清理
            }
        }
    }
}
