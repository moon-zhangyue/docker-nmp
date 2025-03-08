<?php
return [
    'default'     => 'kafka',
    'connections' => [
        'kafka' => [
            'type'       => 'kafka',
            'queue'      => 'default-kafka',
            'brokers'    => 'kafka:9092',
            'bootstrap.servers' => 'kafka:9092',
            'group_id'   => 'thinkphp_consumer_group',
            'topic'      => 'thinkphp_queue',
            'auto_commit' => true,
            'client_id'  => 'thinkphp_client_001',
            'partition'  => 0,
            'offset_reset' => 'earliest',
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
