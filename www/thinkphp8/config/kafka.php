<?php

return [
    'default' => 'kafka',
    'connections' => [
        'kafka' => [
            'driver' => 'kafka',
            'queue' => 'default',
            'consumer_group_id' => 'thinkphp_consumer_group',
            'brokers' => 'kafka:9092',
            'topics' => ['default'],
            'auto.create.topics.enable' => 'true',
            'allow.auto.create.topics' => 'true',
            'consumer' => [
                'enable.auto.commit' => 'true',
                'auto.commit.interval.ms' => '1000',
                'auto.offset.reset' => 'earliest',
                'session.timeout.ms' => '30000',
            ],
            'producer' => [
                'compression.codec' => 'snappy',
                'message.send.max.retries' => 3,
                'batch.size' => 1000,
            ],
            'debug' => true,
        ],
    ],
];
