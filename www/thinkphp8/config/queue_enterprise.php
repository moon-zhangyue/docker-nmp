<?php

// 企业级队列配置

return [
    // 默认队列连接
    'default' => env('QUEUE_CONNECTION', 'redis'),

    // 队列连接
    'connections' => [
        'redis' => [
            'driver'        => 'redis',
            'queue'         => 'default',
            'retry_after'   => 90,
            'block_for'     => null,
        ],

        'kafka' => [
            'driver'        => 'kafka',
            'connection'    => 'default',
            'queue'         => 'default',
            'retry_after'   => 90,
            'block_for'     => null,
            'batch_size'    => 10,
            'idempotent'    => [
                'enable'     => true,
                'expire_time' => 86400, // 24小时
            ],
            'dead_letter'   => [
                'enable'     => true,
                'expire_time' => 604800, // 7天
                'alert_threshold' => 10,
            ],
        ],
    ],

    // Kafka配置
    'kafka' => [
        'connections' => [
            'default' => [
                'brokers' => env('KAFKA_BROKERS', 'localhost:9092'),
                'consumer_group_id' => env('KAFKA_CONSUMER_GROUP', 'thinkphp-queue'),
                'consumer' => [
                    'enable.auto.commit' => 'true',
                    'auto.commit.interval.ms' => '1000',
                    'session.timeout.ms' => '30000',
                ],
                'producer' => [
                    'compression.codec' => 'snappy',
                    'message.send.max.retries' => 3,
                ],
            ],
        ],
    ],

    // 配置提供者
    'config_provider' => [
        'type' => env('QUEUE_CONFIG_PROVIDER', 'redis'), // redis, consul, etcd, zookeeper
        'refresh_interval' => 60, // 秒

        // Redis配置提供者
        'redis' => [],

        // Consul配置提供者
        'consul' => [
            'api_url' => env('CONSUL_API_URL', 'http://localhost:8500'),
        ],

        // Etcd配置提供者
        'etcd' => [
            'api_url' => env('ETCD_API_URL', 'http://localhost:2379'),
        ],

        // ZooKeeper配置提供者
        'zookeeper' => [
            'connection_string' => env('ZOOKEEPER_CONNECTION', 'localhost:2181'),
        ],
    ],

    // 多租户配置
    'tenant' => [
        'enable' => env('QUEUE_TENANT_ENABLE', false),
        'default_tenant' => 'default',
        'topic_prefix' => true, // 是否使用租户ID作为主题前缀
    ],

    // 自动扩展配置
    'autoscale' => [
        'enable' => env('QUEUE_AUTOSCALE_ENABLE', false),
        'min_instances' => 1,
        'max_instances' => 10,
        'cpu_threshold' => 70, // CPU使用率阈值（百分比）
        'memory_threshold' => 70, // 内存使用率阈值（百分比）
        'queue_length_threshold' => 1000, // 队列长度
    ],
];
