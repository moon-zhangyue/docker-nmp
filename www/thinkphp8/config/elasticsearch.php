<?php

return [
    'hosts'           => [env('ELASTICSEARCH_HOST', 'elasticsearch:9200')], // Elasticsearch 服务器地址
    'apiKey'          => 'your_api_key', // 可选：如果使用 API Key 认证
    'timeout'         => 5, // 请求超时时间（秒）
    'connect_timeout' => 3, // 连接超时时间（秒）

    // 索引名称前缀。日期将自动附加。
    // 注意：这里的前缀可以与 Monolog Handler 中定义的不同，
    // 但建议保持一致或在 log.php 中明确使用此配置。
    'index_prefix'    => env('ELASTICSEARCH_INDEX_PREFIX', 'logs'),

    // 基本身份验证
    'auth'            => [
        env('ELASTICSEARCH_USER', ''),
        env('ELASTICSEARCH_PASSWORD', '')
    ]
];