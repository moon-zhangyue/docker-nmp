<?php
// +----------------------------------------------------------------------
// | Elasticsearch配置
// +----------------------------------------------------------------------

return [
    // Elasticsearch服务器地址，支持多节点集群
    'hosts'           => [env('ELASTICSEARCH_HOST', 'elasticsearch:9200')], 
    
    // API Key认证 (如果使用)
    'apiKey'          => env('ELASTICSEARCH_API_KEY', ''),
    
    // 请求超时时间（秒）
    'timeout'         => env('ELASTICSEARCH_TIMEOUT', 10),
    
    // 连接超时时间（秒）
    'connect_timeout' => env('ELASTICSEARCH_CONNECT_TIMEOUT', 5),
    
    // 重试次数
    'retries'         => env('ELASTICSEARCH_RETRIES', 2),

    // 索引名称前缀，日期将自动附加形成 prefix-YYYY.MM.DD 格式
    'index_prefix'    => env('ELASTICSEARCH_INDEX_PREFIX', 'logs'),
    
    // 是否按天创建索引
    'day_rotate'      => env('ELASTICSEARCH_DAY_ROTATE', true),
    
    // 索引分片数
    'number_of_shards'    => env('ELASTICSEARCH_SHARDS', 3),
    
    // 索引副本数
    'number_of_replicas'  => env('ELASTICSEARCH_REPLICAS', 1),

    // 基本身份验证
    'auth'            => [
        env('ELASTICSEARCH_USER', ''),
        env('ELASTICSEARCH_PASSWORD', '')
    ],
    
    // SSL设置
    'ssl' => [
        // 是否启用SSL
        'enabled' => env('ELASTICSEARCH_SSL_ENABLED', false),
        // 是否验证SSL证书
        'verify' => env('ELASTICSEARCH_SSL_VERIFY', true),
        // 证书路径
        'cert' => env('ELASTICSEARCH_SSL_CERT', ''),
        // 自签名CA证书路径
        'ca' => env('ELASTICSEARCH_SSL_CA', ''),
    ],
    
    // 调试模式
    'debug'           => env('ELASTICSEARCH_DEBUG', false),
];
