<?php

// 返回一个配置数组，包含队列连接和错误追踪等配置信息
return [
    'default'           => env('QUEUE_CONNECTION', 'rabbitmq'),

    'connections'       => [
        'sync'     => [  // 同步队列配置
            'type' => 'sync',  // 队列类型为同步
        ],
        'database' => [  // 数据库队列配置
            'type'        => 'database',  // 队列类型为数据库
            'queue'       => 'default',  // 队列名称为 'default'
            'table'       => 'jobs',  // 存储任务的数据库表名为 'jobs'
            'connection'  => null,  // 数据库连接，默认为 null
            'retry_after' => 60,  // 任务失败后重试的等待时间为 60 秒
        ],
        'redis'    => [  // Redis 队列配置
            'type'       => 'redis',  // 队列类型为 Redis
            'queue'      => 'default',  // 队列名称为 'default'
            'host'       => 'redis',  // Redis 服务器地址
            'port'       => 6379,  // Redis 服务器端口
            'password'   => '',  // Redis 服务器密码，默认为空
            'select'     => 0,  // Redis 数据库选择，默认为 0
            'timeout'    => 0,  // 连接超时时间，默认为 0
            'persistent' => false,  // 是否使用持久连接，默认为 false
        ],
        'rabbitmq' => [  // RabbitMQ 队列配置
            'type'             => 'RabbitMQ',  // 队列类型为 RabbitMQ
            'host'             => env('RABBITMQ_HOST', 'rabbitmq'),  // RabbitMQ 服务器地址
            'port'             => env('RABBITMQ_PORT', 5672),  // RabbitMQ 服务器端口
            'login'            => env('RABBITMQ_LOGIN', 'myuser'),  // RabbitMQ 登录用户名
            'password'         => env('RABBITMQ_PASSWORD', 'mypass'),  // RabbitMQ 登录密码
            'vhost'            => env('RABBITMQ_VHOST', '/'),  // RabbitMQ 虚拟主机
            'queue'            => env('RABBITMQ_QUEUE', 'default'),  // 默认队列名称
            'exchange'         => env('RABBITMQ_EXCHANGE', 'default'),  // 交换机名称
            'exchange_type'    => env('RABBITMQ_EXCHANGE_TYPE', 'direct'),  // 交换机类型
            'passive'          => false,  // 是否检查队列/交换机是否存在而不创建
            'durable'          => true,   // 是否持久化
            'exclusive'        => false,  // 是否排他
            'auto_delete'      => false,  // 是否自动删除
            'prefetch_count'   => env('RABBITMQ_PREFETCH_COUNT', 1),  // 预取数量
            'prefetch_size'    => 0,  // 预取大小
            'global_qos'       => false,  // 是否全局QoS
            'delayed_exchange' => env('RABBITMQ_DELAYED_EXCHANGE', 'delayed'),  // 延迟交换机名称
            'queue_arguments'  => [],  // 队列参数
            'publisher_confirms' => env('RABBITMQ_PUBLISHER_CONFIRMS', false),  // 是否启用发布者确认模式
            'wait_for_confirm'   => env('RABBITMQ_WAIT_FOR_CONFIRM', false),    // 是否等待发布者确认
            'confirm_timeout'    => env('RABBITMQ_CONFIRM_TIMEOUT', 5.0),       // 发布者确认超时时间（秒）
        ],
        'kafka'    => [
            'type'                               => 'kafka',
            'bootstrap_servers'                  => env('KAFKA_BROKERS', 'localhost:9092'),
            'client_id'                          => env('KAFKA_CLIENT_ID', 'thinkphp-queue'),
            'group_id'                           => env('KAFKA_GROUP_ID', 'thinkphp-queue-group'),
            'auto_offset_reset'                  => env('KAFKA_AUTO_OFFSET_RESET', 'earliest'),
            'security_protocol'                  => env('KAFKA_SECURITY_PROTOCOL', ''),
            'sasl_mechanism'                     => env('KAFKA_SASL_MECHANISM', 'PLAIN'),
            'sasl_username'                      => env('KAFKA_SASL_USERNAME', ''),
            'sasl_password'                      => env('KAFKA_SASL_PASSWORD', ''),
            'metadata_timeout'                   => env('KAFKA_METADATA_TIMEOUT', 10000),
            'topic_metadata_refresh_interval_ms' => env('KAFKA_TOPIC_METADATA_REFRESH_INTERVAL_MS', 300000),
            'message_max_bytes'                  => env('KAFKA_MESSAGE_MAX_BYTES', 1000000),
            'max_poll_interval_ms'               => env('KAFKA_MAX_POLL_INTERVAL_MS', 300000),
            'compression_type'                   => env('KAFKA_COMPRESSION_TYPE', 'snappy'),
            'batch_size'                         => env('KAFKA_BATCH_SIZE', 16384),
            'batch_num_messages'                 => env('KAFKA_BATCH_NUM_MESSAGES', 10000),
            'batch_timeout'                      => env('KAFKA_BATCH_TIMEOUT', 100),
            'transactional_id'                   => env('KAFKA_TRANSACTIONAL_ID', ''),
            'required_acks'                      => env('KAFKA_REQUIRED_ACKS', -1),
            'request_timeout_ms'                 => env('KAFKA_REQUEST_TIMEOUT_MS', 30000),
            'retries'                            => env('KAFKA_RETRIES', 3),
            'retry_backoff_ms'                   => env('KAFKA_RETRY_BACKOFF_MS', 100),
            'connection_timeout'                 => env('KAFKA_CONNECTION_TIMEOUT', 10000),
            'debug'                              => env('KAFKA_DEBUG', false),
            'pool'                               => [
                'min'       => env('KAFKA_POOL_MIN', 1),
                'max'       => env('KAFKA_POOL_MAX', 10),
                'idle_time' => env('KAFKA_POOL_IDLE_TIME', 60),
                'wait_time' => env('KAFKA_POOL_WAIT_TIME', 3)
            ]
        ],
    ],
    // Sentry错误追踪配置
    'sentry'            => 'https://6baf7b16aaedd3124c0da0714349bc6f@o4508997599166464.ingest.us.sentry.io/4508997601001472',  // Sentry DSN（Data Source Name）
    'sentry_config'     => [  // 定义一个名为 'sentry_config' 的数组，用于配置 Sentry 错误追踪服务
        'environment'          => 'development',  // 修正环境名称
        'release'              => '1.0.0',  // 设置当前应用的版本号为 '1.0.0'
        'traces_sample_rate'   => 1.0,  // 设置性能追踪的采样率为 100%，即每个请求都进行追踪
        'max_breadcrumbs'      => 50,  // 设置最大面包屑数量为 50，面包屑用于记录用户操作路径
        'profiles_sample_rate' => 1.0,  // 设置性能分析文件的采样率为 100%，即每个请求都进行性能分析
    ],
    // 配置验证
    'config_validation' => [
        'enabled' => true,  // 启用配置验证
        'strict'  => false,  // 配置验证是否为严格模式，默认为 false
    ],
    'failed'            => [
        'type'  => 'none',
        'table' => 'failed_jobs',
    ],
];
