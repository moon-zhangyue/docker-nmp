<?php
/**
 * WebSocket 服务配置
 * 所有值可用环境变量覆盖，容器部署时通过 -e 传入，无需改代码。
 */
return [
    // 监听地址（容器内），由 Nginx 以 /cs/ws 同源反代
    'listen' => getenv('CS_WS_LISTEN') ?: 'websocket://0.0.0.0:8098',

    'redis' => [
        'host'   => getenv('CS_REDIS_HOST') ?: 'redis',
        'port'   => (int) (getenv('CS_REDIS_PORT') ?: 6379),
        'pass'   => getenv('CS_REDIS_PASS') ?: '',
        'prefix' => getenv('CS_REDIS_PREFIX') ?: 'cs:',
        'timeout' => 2.0,
    ],

    // 允许的浏览器来源，逗号分隔；为空表示不校验（不推荐）
    'origins' => array_values(array_filter(array_map('trim', explode(',', (string) (getenv('CS_WS_ORIGINS') ?: 'http://127.0.0.1:8099,http://localhost:8099'))))),

    // 连接建立后必须在多少秒内完成鉴权
    'auth_timeout' => 5,
    // 超过多少秒没有任何上行帧则断开（客户端每 25s 发 ping）
    'idle_timeout' => 90,
    // 单连接每秒最多上行帧数
    'max_frames_per_sec' => 6,

    // 连接数保护
    'max_conn_total'  => (int) (getenv('CS_WS_MAX_CONN') ?: 1000),
    'max_conn_per_ip' => (int) (getenv('CS_WS_MAX_CONN_PER_IP') ?: 5),

    // Redis Stream 轮询间隔（毫秒）：越小越实时，Redis 压力略增
    'poll_interval_ms' => (int) (getenv('CS_WS_POLL_MS') ?: 100),
    // 单次 XREAD 拉取上限
    'poll_batch' => 200,
];
