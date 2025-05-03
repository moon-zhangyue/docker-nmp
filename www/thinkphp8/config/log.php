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
        // Elasticsearch日志通道
        'elasticsearch' => [
            // 日志记录方式
            'type'         => 'Elasticsearch',
            // 日志级别
            'level'        => ['info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency', 'debug'],
            // 使用JSON格式记录
            'json'         => true,
            // 日志处理
            'processor'    => null,
            // 关闭通道日志写入
            'close'        => false,
            // 日志输出格式化
            'time_format'  => 'Y-m-d H:i:s',
            'format'       => '[%s][%s] %s',
            // 是否按天轮转索引
            'day_rotate'   => true,
            // 索引前缀
            'index_prefix' => env('ELASTICSEARCH_INDEX_PREFIX', 'logs'),
        ],
    ],

];
