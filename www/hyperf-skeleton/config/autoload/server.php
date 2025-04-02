<?php

// 严格类型声明，确保代码中的类型严格匹配
declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
use Hyperf\Server\Event;
use Hyperf\Server\Server;
use Swoole\Constant;

return [
    'mode'      => SWOOLE_PROCESS,
    'servers'   => [
        [
            'name'      => 'http',
            'type'      => Server::SERVER_HTTP,
            'host'      => '0.0.0.0',
            'port'      => 9501,
            'sock_type' => SWOOLE_SOCK_TCP,
            'callbacks' => [
                Event::ON_REQUEST => [Hyperf\HttpServer\Server::class, 'onRequest'],
            ],
            'options'   => [
                // Whether to enable request lifecycle event
                'enable_request_lifecycle' => false,
            ],
        ],
    ],
    'settings'  => [
            // 启用协程
        Constant::OPTION_ENABLE_COROUTINE    => true,
            // 设置工作进程数，根据CPU核心数动态调整
        Constant::OPTION_WORKER_NUM          => swoole_cpu_num(),
            // 设置进程ID文件路径
        Constant::OPTION_PID_FILE            => BASE_PATH . '/runtime/hyperf.pid',
            // 开启TCP_NODELAY，减少TCP延迟
        Constant::OPTION_OPEN_TCP_NODELAY    => true,
            // 设置最大协程数
        Constant::OPTION_MAX_COROUTINE       => 100000,
            // 开启HTTP2协议
        Constant::OPTION_OPEN_HTTP2_PROTOCOL => true,
            // 设置最大请求数
        Constant::OPTION_MAX_REQUEST         => 100000,
            // 设置Socket缓冲区大小，单位为字节
        Constant::OPTION_SOCKET_BUFFER_SIZE  => 2 * 1024 * 1024,
            // 设置输出缓冲区大小，单位为字节
        Constant::OPTION_BUFFER_OUTPUT_SIZE  => 2 * 1024 * 1024,
    ],
    'callbacks' => [
            // 工作进程启动时的回调函数
        Event::ON_WORKER_START => [Hyperf\Framework\Bootstrap\WorkerStartCallback::class, 'onWorkerStart'],
            // 管道消息接收时的回调函数
        Event::ON_PIPE_MESSAGE => [Hyperf\Framework\Bootstrap\PipeMessageCallback::class, 'onPipeMessage'],
            // 工作进程退出时的回调函数
        Event::ON_WORKER_EXIT  => [Hyperf\Framework\Bootstrap\WorkerExitCallback::class, 'onWorkerExit'],
    ],
];
