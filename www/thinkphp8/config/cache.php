<?php

// +----------------------------------------------------------------------
// | 缓存设置
// +----------------------------------------------------------------------

return [
    // 默认缓存驱动
    'default' => env('cache.driver', 'file'),

    // 缓存连接方式配置
    'stores'  => [
        'redis'    =>    [
            // 驱动方式
            'type'   => 'redis',
            // 服务器地址
            'host'   => env('redis.host', 'redis'),
            // 端口
            'port'   => env('redis.port', 6379),
            // 密码
            'password' => env('redis.password', ''),
            // 缓存有效期 0表示永久缓存
            'expire' => 0,
            // 缓存前缀
            'prefix' => '',
            // 数据库索引
            'select' => env('redis.select', 0),
            // 超时时间
            'timeout' => env('redis.timeout', 0),
        ],
        'file' => [
            // 驱动方式
            'type'       => 'File',
            // 缓存保存目录
            'path'       => '',
            // 缓存前缀
            'prefix'     => '',
            // 缓存有效期 0表示永久缓存
            'expire'     => 0,
            // 缓存标签前缀
            'tag_prefix' => 'tag:',
            // 序列化机制 例如 ['serialize', 'unserialize']
            'serialize'  => [],
        ],
        // 更多的缓存连接
    ],
];
