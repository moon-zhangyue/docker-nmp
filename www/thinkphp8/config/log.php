<?php

// +----------------------------------------------------------------------
// | 日志设置
// +----------------------------------------------------------------------
return [
    // 默认日志记录通道
    'default'      => env('log.channel', 'elasticsearch'),
    // 日志记录级别
    'level'        => [],
    // 日志类型记录的通道 ['error'=>'email',...]
    'type_channel' => [],
    // 关闭全局日志写入
    'close'        => false,
    // 全局日志处理 支持闭包
    'processor'    => null,

    // 日志通道列表
    'channels'     => [
        'file'          => [
            // 日志记录方式
            'type'           => 'File',
            // 日志保存目录
            'path'           => runtime_path('log'),
            // 单文件日志写入
            'single'         => false,
            // 独立日志级别
            'apart_level'    => [],
            // 最大日志文件数量
            'max_files'      => 0,
            // 使用JSON格式记录
            'json'           => false,
            // 日志处理
            'processor'      => null,
            // 关闭通道日志写入
            'close'          => false,
            // 日志输出格式化
            'time_format'    => 'Y-m-d H:i:s',
            'format'         => '[%s][%s] %s',
            // 是否实时写入
            'realtime_write' => true,
        ],
        'elasticsearch' => [
            // 日志记录方式
            'type'            => \think\log\driver\MonologElasticsearch::class,
            // ES服务器地址
            'hosts'           => ['localhost:9200'],
            // 索引前缀
            'index_prefix'    => 'es_log_',
            // 日志级别 (注释掉以避免与框架内部合并冲突)
            // 'level'           => 'debug',
            // 是否冒泡
            'bubble'          => true,
            // 连接超时时间
            'timeout'         => 5,
            // 是否验证SSL证书
            'ssl_verify'      => false,
            // API密钥
            'apiKey'          => '',
            // 用户名
            'username'        => '',
            // 密码
            'password'        => '',
            // 时间格式
            'time_format'     => 'Y-m-d H:i:s',
            // 是否记录上下文信息
            'context_logging' => true,
            // 独立日志级别
            'apart_level'     => ['error', 'critical', 'alert', 'emergency'],
            // 最大重试次数
            'max_retry'       => 3,
            // 文档类型 'type' => '_doc' 已移除，驱动将使用默认值
        ],
        'audit'         => [
            // 日志记录方式
            'type'           => 'File',
            // 日志保存目录
            'path'           => runtime_path('log/audit'),
            // 单文件日志写入
            'single'         => false,
            // 独立日志级别
            'apart_level'    => [],
            // 最大日志文件数量
            'max_files'      => 0,
            // 使用JSON格式记录
            'json'           => false,
            // 日志处理
            'processor'      => null,
            // 关闭通道日志写入
            'close'          => false,
            // 日志输出格式化
            'time_format'    => 'Y-m-d H:i:s',
            'format'         => '[%s][%s] %s',
            // 是否实时写入
            'realtime_write' => true,
        ],
    ],

];
