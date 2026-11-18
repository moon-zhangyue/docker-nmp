<?php
/**
 * 进程配置
 *
 * 本服务只承担 WebSocket 推送：
 *   - websocket：对外提供 WS 连接（经 Nginx /cs/ws 反代）
 *   - webman：框架内置 HTTP 进程，仅绑定回环地址用于健康检查/状态，不对外暴露
 */
use support\Log;
use support\Request;
use app\process\Http;
use app\process\WsServer;

return [
    'webman' => [
        'handler' => Http::class,
        'listen' => 'http://127.0.0.1:18787',
        'count' => 1,
        'user' => '',
        'group' => '',
        'reusePort' => false,
        'context' => [],
        'constructor' => [
            'requestClass' => Request::class,
            'logger' => Log::channel('default'),
            'appPath' => app_path(),
            'publicPath' => public_path()
        ]
    ],

    'websocket' => [
        'handler' => WsServer::class,
        'listen' => config('ws.listen'),
        'count' => 1,
        'reloadable' => true,
        'reusePort' => false,
        'context' => [],
    ],
];
