<?php
/**
 * WebSocket 联调客户端（仅测试用，不参与线上逻辑）
 * 用法: php tests/wsclient.php <url> <ticket> <origin> <holdSeconds>
 */
require __DIR__ . '/../vendor/autoload.php';

use Workerman\Connection\AsyncTcpConnection;
use Workerman\Timer;
use Workerman\Worker;

$args = $argv;
array_shift($args);                    // 脚本名
if (($args[0] ?? '') === 'start') {    // Workerman 要求的启动命令
    array_shift($args);
}

$url    = $args[0] ?? 'ws://127.0.0.1:8098';
$ticket = $args[1] ?? '';
$origin = $args[2] ?? '';
$hold   = (int) ($args[3] ?? 8);

$conn                  = new AsyncTcpConnection($url);
$conn->websocketOrigin = $origin;

$conn->onConnect = function ($c) use ($ticket) {
    echo "[OPEN]\n";
    if ($ticket !== '') {
        $c->send(json_encode(['type' => 'auth', 'ticket' => $ticket]));
    }
};
$conn->onMessage = function ($c, $data) {
    echo '[RECV] ' . $data . PHP_EOL;
};
$conn->onError   = function ($c, $code, $msg) {
    echo '[ERR] ' . $code . ' ' . $msg . PHP_EOL;
};
$conn->onClose   = function () use ($conn) {
    echo "[CLOSED]\n";
    // 对端关闭后不再重连；票据是一次性的，重连只会得到 auth_failed 并刷日志。
    // 需要结束时由外部 timeout/Ctrl+C 停止，不在回调里 stopAll（会触发 master 重新拉起 worker）。
    $conn->cancelReconnect();
};

// 避免与服务端及并发测试实例争用同一个 pid 文件
Worker::$pidFile = sys_get_temp_dir() . '/wsclient-' . getmypid() . '.pid';
Worker::$logFile = sys_get_temp_dir() . '/wsclient.log';

// AsyncTcpConnection 需要事件循环，因此把 connect 放到 onWorkerStart 中
$worker                = new Worker();
$worker->onWorkerStart = function () use ($conn, $hold) {
    $conn->connect();
    Timer::add($hold, function () use ($conn) {
        $conn->cancelReconnect();
        $conn->close();
    }, [], false);
};

Worker::runAll();
