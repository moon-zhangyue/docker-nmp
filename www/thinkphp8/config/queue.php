<?php
return [
    'default'     => 'kafka',
    'connections' => [
        'kafka' => [
            'type'       => 'kafka', // 指定队列类型为kafka
            'queue'      => 'default-kafka', // 指定队列名称为default-kafka
            'brokers'    => 'kafka:9092', // 指定Kafka服务器的地址和端口
            'bootstrap.servers' => 'kafka:9092', // 指定Kafka的启动服务器地址和端口
            'group_id'   => 'thinkphp_consumer_group', // 指定消费者组ID
            'topic'      => 'thinkphp_queue', // 指定要消费的主题
            'auto_commit' => true, // 是否自动提交偏移量
            'client_id'  => 'thinkphp_client_001', // 指定客户端ID
            'partition'  => 0, // 指定要消费的分区
            'offset_reset' => 'earliest', // 当没有初始偏移量时，或者当前偏移量不存在（例如，数据被删除），从何处开始消费
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
