<?php

return [
    // 'http'       => [
    //     'enable'     => true,
    //     'host'       => '0.0.0.0',
    //     'port'       => 9501,
    //     'worker_num' => swoole_cpu_num(),
    //     'options'    => [],
    // ],
    // 'websocket'  => [
    //     'enable'        => false,
    //     'route'         => true,
    //     'handler'       => \think\swoole\websocket\Handler::class,
    //     'ping_interval' => 25000,
    //     'ping_timeout'  => 60000,
    //     'room'          => [
    //         'type'  => 'table',
    //         'table' => [
    //             'room_rows'   => 8192,
    //             'room_size'   => 2048,
    //             'client_rows' => 4096,
    //             'client_size' => 2048,
    //         ],
    //         'redis' => [
    //             'host'          => 'redis',
    //             'port'          => 6379,
    //             'max_active'    => 3,
    //             'max_wait_time' => 5,
    //         ],
    //     ],
    //     'listen'        => [],
    //     'subscribe'     => [],
    // ],
    // 'rpc'        => [
    //     'server' => [
    //         'enable'     => false,
    //         'host'       => '0.0.0.0',
    //         'port'       => 9000,
    //         'worker_num' => swoole_cpu_num(),
    //         'services'   => [],
    //     ],
    //     'client' => [],
    // ],
    // //队列
    // 'queue'      => [
    //     'enable'  => false,
    //     'workers' => [],
    // ],
    // 'hot_update' => [
    //     'enable'  => env('APP_DEBUG', false),
    //     'name'    => ['*.php'],
    //     'include' => [app_path()],
    //     'exclude' => [],
    // ],
    // //连接池
    // 'pool'       => [
    //     'min'       => env('SWOOLE_POOL_MIN', 1),
    //     'max'       => env('SWOOLE_POOL_MAX', 10),
    //     'idle_time' => env('SWOOLE_POOL_IDLE_TIME', 60),
    //     'wait_time' => env('SWOOLE_POOL_WAIT_TIME', 3)
    // ],
    // 'ipc'        => [
    //     'type'  => 'unix_socket',
    //     'redis' => [
    //         'host'          => '127.0.0.1',
    //         'port'          => 6379,
    //         'max_active'    => 3,
    //         'max_wait_time' => 5,
    //     ],
    // ],
    // //锁
    // 'lock'       => [
    //     'enable' => false,
    //     'type'   => 'table',
    //     'redis'  => [
    //         'host'          => '127.0.0.1',
    //         'port'          => 6379,
    //         'max_active'    => 3,
    //         'max_wait_time' => 5,
    //     ],
    // ],
    // 'tables'     => [],
    // //每个worker里需要预加载以共用的实例
    // 'concretes'  => [],
    // //重置器
    // 'resetters'  => [],
    // //每次请求前需要清空的实例
    // 'instances'  => [],
    // //每次请求前需要重新执行的服务
    // 'services'   => [],
    // 'coroutine'  => [
    //     'hook_flags' => SWOOLE_HOOK_ALL
    // ],
    // 'options'    => [
    //     'pid_file'           => runtime_path() . 'swoole.pid',
    //     'log_file'           => runtime_path() . 'swoole.log',
    //     'daemonize'          => env('SWOOLE_DAEMONIZE', false),
    //     'worker_num'         => env('SWOOLE_WORKER_NUM', swoole_cpu_num()),
    //     'max_request'        => env('SWOOLE_MAX_REQUEST', 0),
    //     'buffer_output_size' => env('SWOOLE_BUFFER_OUTPUT_SIZE', 2 * 1024 * 1024),
    //     'max_coroutine'      => env('SWOOLE_MAX_COROUTINE', 100000),
    //     'hook_flags'         => SWOOLE_HOOK_ALL
    // ]
];
