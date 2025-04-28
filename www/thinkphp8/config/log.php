<?php

// +----------------------------------------------------------------------
// | 日志设置
// +----------------------------------------------------------------------

use Elasticsearch\ClientBuilder;
use Monolog\Handler\ElasticsearchHandler;
use Monolog\Formatter\JsonFormatter; // 使用 JSON 格式化器将日志记录为 JSON
use Monolog\Processor\WebProcessor;     // 添加 Web 相关信息 (URL, IP, HTTP Method)
use Monolog\Processor\IntrospectionProcessor; // 添加代码位置信息 (文件, 行号, 类, 方法)
use Monolog\Handler\NullHandler;      // 用于创建 Handler 失败时的备用

// 尝试加载 Elasticsearch 配置
$esConfig = [];
if (file_exists(config_path() . 'elasticsearch.php')) {
    $esConfig = config('elasticsearch');
}

return [
    // 默认日志记录通道
    'default'      => env('log.channel', 'monolog_es'),
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
        'elasticsearch' => [  // 添加elasticsearch通道作为monolog_es的别名
            'type'    => 'monolog',
            'handler' => function () use ($esConfig) {
                // 复用monolog_es通道的处理器
                return app()->make('log')->channel('monolog_es')->getMonolog()->getHandlers()[0];
            },
            'level'   => ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'],
            'close'   => false,
        ],
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

        // 直接使用 Monolog Elasticsearch Handler 的通道
        'monolog_es'    => [
            // 'type' => 'monolog' // ThinkPHP 可能不需要显式指定 type，如果 handler 是闭包
            // 使用闭包作为 Handler 工厂
            'handler'    => function () use ($esConfig) {
                try {
                    // 1. 创建 Elasticsearch PHP 客户端
                    $clientBuilder = ClientBuilder::create()
                        ->setHosts($esConfig['hosts'] ?? ['localhost:9200']); // 从配置或默认值获取 hosts
            
                    // 处理身份验证 (如果配置了)
                    if (isset($esConfig['auth']) && is_array($esConfig['auth']) && !empty($esConfig['auth'][0])) {
                        $clientBuilder->setBasicAuthentication($esConfig['auth'][0], $esConfig['auth'][1] ?? '');
                    }
                    // 设置连接和请求超时
            
                    $clientBuilder->setConnectionParams([
                        'client' => [
                            'timeout'         => $esConfig['timeout'] ?? 5,
                            'connect_timeout' => $esConfig['connect_timeout'] ?? 3
                        ]
                    ]);

                    $clientBuilder->setRetries(0); // 发送日志失败时不自动重试
            
                    $esClient = $clientBuilder->build();

                    // 2. 定义 Elasticsearch Handler 的选项
                    $options = [
                        // 动态索引名称：前缀 + 日期
                        'index'        => ($esConfig['index_prefix'] ?? 'logs') . '-' . date('Y.m.d'),
                        'type'         => '_doc', // 现代 Elasticsearch 版本推荐使用 _doc
                        'ignore_error' => true // 设为 true 以忽略 ES 发送错误，避免应用崩溃
                    ];

                    // 3. 创建 Elasticsearch Handler 实例
                    $handler = new ElasticsearchHandler($esClient, $options);

                    // 4. 设置格式化器 (Formatter) - JsonFormatter 很常用
                    // 这会将 Monolog 的日志记录数组转换为 JSON 字符串发送给 ES
                    $handler->setFormatter(new JsonFormatter());

                    return $handler; // 返回配置好的 Handler
            
                } catch (\Throwable $e) {
                    // 如果创建 Handler 失败 (例如 ES 连接不上)
                    // 记录错误到 PHP 错误日志
                    error_log("创建 Monolog Elasticsearch handler 失败: " . $e->getMessage());
                    
                    // 不再返回 NullHandler，改为使用文件日志作为后备
                    // 获取 file 通道的处理器
                    return app()->make('log')->channel('file')->getMonolog()->getHandlers()[0];
                }
            },
            // 要发送到此通道的日志级别
            'level'      => ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'],
            // 可选：添加 Monolog 处理器 (Processor) 来丰富日志内容
            'processors' => [
                    // 添加请求 URI, 请求方法, 客户端 IP, referer 等信息
                WebProcessor::class,
                // 添加触发日志的文件, 行号, 类名, 方法名 (可能影响性能，按需使用)
                // IntrospectionProcessor::class,
            ],
            // ThinkPHP 的配置项，对于 Monolog Handler 可能不直接适用其生命周期管理，但保留
            'close'      => false,
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
