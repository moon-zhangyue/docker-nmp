<?php

return [
    'default' => [
        'brokers' => env('KAFKA_BROKERS', 'kafka:9092'),
        'debug' => env('KAFKA_DEBUG', false),
        'topics' => [
            'default' => 'kafka-topic'
        ],
        'group_id' => env('KAFKA_GROUP_ID', 'thinkphp-group'),
        'client_id' => env('KAFKA_CLIENT_ID', 'thinkphp-client'),
    ]
];