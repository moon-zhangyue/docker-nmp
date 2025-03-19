<?php

// 返回一个配置数组，包含队列连接和错误追踪等配置信息
return [
    'default'     => 'kafka',  // 设置默认的队列连接为 'kafka'
    'connections' => [  // 定义多个队列连接的配置
        'sync'     => [  // 同步队列配置
            'type' => 'sync',  // 队列类型为同步
        ],
        'database' => [  // 数据库队列配置
            'type'       => 'database',  // 队列类型为数据库
            'queue'      => 'default',  // 队列名称为 'default'
            'table'      => 'jobs',  // 存储任务的数据库表名为 'jobs'
            'connection' => null,  // 数据库连接，默认为 null
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
        'kafka' => [  // Kafka 队列配置
            'type'  => 'kafka',  // 队列类型为 Kafka
            'queue' => 'default-kafka',  // 队列名称为 'default-kafka'
            'host'  => 'kafka:9092',  // Kafka 服务器地址
            'brokers'    => 'kafka:9092',  // Kafka 经纪人地址
            'topic'      => 'default-topic',  // Kafka 主题名称
            'options' => [  // Kafka 消费者配置选项
                'group.id' => 'thinkphp_consumer_group',  // 消费者组 ID
                'auto.offset.reset' => 'earliest',  // 自动重置偏移量为最早
                'enable.auto.commit' => true,  // 启用自动提交偏移量
                'auto.commit.interval.ms' => 1000,  // 自动提交偏移量的间隔时间（毫秒）
                'socket.timeout.ms' => 30000,  // 套接字超时时间（毫秒）
                'session.timeout.ms' => 30000,  // 会话超时时间（毫秒）
                'max.poll.interval.ms' => 300000,  // 最大轮询间隔时间（毫秒）
                'client.id'  => 'thinkphp_client',  // 客户端 ID
            ],
            'producer' => [  // Kafka 生产者配置
                'compression.codec' => 'snappy',  // 压缩编码为 Snappy
                'message.send.max.retries' => 3,  // 消息发送最大重试次数
                'queue.buffering.max.messages' => 100000,  // 队列缓冲区最大消息数
                'queue.buffering.max.ms' => 1000,  // 队列缓冲区最大时间（毫秒）
                'batch.num.messages' => 1000,  // 批次最大消息数
            ],
            'consumer' => [  // Kafka 消费者配置
                'group.id' => 'thinkphp_consumer_group',  // 消费者组 ID
                'enable.auto.commit' => true,  // 启用自动提交偏移量
                'auto.commit.interval.ms' => 1000,  // 自动提交偏移量的间隔时间（毫秒）
                'auto.offset.reset' => 'earliest',  // 自动重置偏移量为最早
                'session.timeout.ms' => 30000,  // 会话超时时间（毫秒）
                'max.poll.interval.ms' => 300000,  // 最大轮询间隔时间（毫秒）
            ],
            // 事务支持配置
            'transaction' => [
                'enabled' => true,  // 启用事务支持
                'timeout' => 10000,  // 事务超时时间（毫秒）
            ],
            // 负载均衡配置
            'balance' => [
                'message_rate_threshold' => 10.0,  // 消息速率阈值
                'consumer_partition_ratio' => 2.0,  // 消费者分区比例
                'min_message_rate' => 1.0,  // 最小消息速率
                'check_interval' => 300,  // 检查间隔时间（秒）
            ],
            // 健康检查配置
            'health' => [
                'enabled' => true,  // 启用健康检查
                'heartbeat_interval' => 30,  // 心跳间隔时间（秒）
                'heartbeat_timeout' => 60,  // 心跳超时时间（秒）
            ],
            // 幂等性检查配置
            'idempotent' => [
                'enabled' => true,  // 启用幂等性检查
                'expire_time' => 86400,  // 幂等性检查的过期时间（秒）
            ],
            // 死信队列配置
            'dead_letter' => [
                'enabled' => true,  // 启用死信队列
                'expire_time' => 604800,  // 死信队列的过期时间（秒）
                'alert_threshold' => 10,  // 死信队列的警报阈值
            ],
        ],
    ],
    // Sentry错误追踪配置
    'sentry' => 'https://6baf7b16aaedd3124c0da0714349bc6f@o4508997599166464.ingest.us.sentry.io/4508997601001472',  // Sentry DSN（Data Source Name）
    'sentry_config' => [  // 定义一个名为 'sentry_config' 的数组，用于配置 Sentry 错误追踪服务
        'environment' => 'developmentddd',  // 设置当前环境为 'developmentddd'，通常用于标识开发环境
        'release' => '1.0.0',  // 设置当前应用的版本号为 '1.0.0'
        'traces_sample_rate' => 1.0,  // 设置性能追踪的采样率为 100%，即每个请求都进行追踪
        'max_breadcrumbs' => 50,  // 设置最大面包屑数量为 50，面包屑用于记录用户操作路径
        'profiles_sample_rate' => 1.0,  // 设置性能分析文件的采样率为 100%，即每个请求都进行性能分析
    ],
    // 配置验证
    'config_validation' => [
        'enabled' => true,  // 启用配置验证
        'strict' => false,  // 配置验证是否为严格模式，默认为 false
    ],
];
