<?php
return [
    'default'     => 'kafka',
    'connections' => [
        'kafka' => [
            'type'  => 'kafka',
            'queue' => 'default-kafka',
            'host'  => 'kafka:9092',
            'brokers'    => 'kafka:9092',
            'topic'      => 'default-topic', // 添加默认的topic配置
            'options' => [
                // Kafka消费者组的唯一标识符，用于区分不同的消费者组
                'group.id' => 'thinkphp_consumer_group',
                // 当消费者第一次启动时，如果没有初始偏移量或者当前偏移量不存在（例如数据被删除），则从何处开始消费消息
                // 'earliest' 表示从最早的消息开始消费
                'auto.offset.reset' => 'earliest',
                // 是否自动提交偏移量，true 表示自动提交
                'enable.auto.commit' => true,
                // 自动提交偏移量的间隔时间，单位为毫秒
                'auto.commit.interval.ms' => 1000,
                // socket超时时间，单位为毫秒，超过此时间未完成操作则抛出异常
                'socket.timeout.ms' => 30000,
                // 会话超时时间，单位为毫秒，超过此时间Kafka认为消费者已死亡
                'session.timeout.ms' => 30000,
                // 最大轮询间隔时间，单位为毫秒，超过此时间Kafka认为消费者已死亡
                'max.poll.interval.ms' => 300000,
                // 客户端的唯一标识符，用于跟踪请求来源
                'client.id'  => 'thinkphp_client_001',
            ],
            // 生产者配置
            'producer' => [
                // 设置压缩编解码器为 'snappy'
                'compression.codec' => 'snappy',
                // 设置消息发送的最大重试次数为 3 次
                'message.send.max.retries' => 3,
                // 设置队列中允许的最大消息数为 100000 条
                'queue.buffering.max.messages' => 100000,
                // 设置队列中消息缓冲的最大时间为 1000 毫秒（1 秒）
                'queue.buffering.max.ms' => 1000,
                // 设置每个批次中消息的最大数量为 1000 条
                'batch.num.messages' => 1000,
            ],
            // 消费者配置
            'consumer' => [
                // 设置Kafka消费者组的ID，用于标识消费者所属的组
                'group.id' => 'thinkphp_consumer_group',
                // 启用自动提交偏移量，即消费者在处理完消息后自动提交偏移量到Kafka
                'enable.auto.commit' => true,
                // 设置自动提交偏移量的间隔时间，单位为毫秒，这里设置为1000毫秒（1秒）
                'auto.commit.interval.ms' => 1000,
                // 设置当发现没有初始偏移量或当前偏移量不存在（例如数据被删除）时的策略
                // 'earliest' 表示自动重置偏移量为最早的偏移量
                'auto.offset.reset' => 'earliest',
                // 设置会话超时时间，单位为毫秒，这里设置为30000毫秒（30秒）
                // 如果消费者在指定时间内没有向Kafka发送心跳，则会被认为离线
                'session.timeout.ms' => 30000,
            ],
        ],
        'database' => [
            'type'       => 'database',
            'queue'      => 'default',
            'table'      => 'jobs',
            'connection' => null,
        ],
        'redis'    => [
            'type'       => 'redis',
            'queue'      => 'default',
            'host'       => 'redis',
            'port'       => 6379,
            'password'   => '',
            'select'     => 0,
            'timeout'    => 0,
            'persistent' => false,
        ],
    ],
];
