#!/usr/bin/env php
<?php

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/var/log/php/kafka_consumer_error.log');

// 配置
$conf = new RdKafka\Conf();
$conf->set('group.id', 'mygroup');
$conf->set('metadata.broker.list', 'kafka:9092');

// 设置消费者偏移量，从最早的消息开始消费
$conf->set('auto.offset.reset', 'earliest');

// 创建消费者
$consumer = new RdKafka\KafkaConsumer($conf);

// 订阅主题
$consumer->subscribe(['test-topic']);

echo "Starting Kafka consumer...\n";

while (true) {
    try {
        // 尝试消费消息，超时时间设置为 120 秒
        $message = $consumer->consume(120 * 1000);
        
        switch ($message->err) {
            case RD_KAFKA_RESP_ERR_NO_ERROR:
                echo "Received message: " . $message->payload . "\n";
                // 这里处理你的业务逻辑
                break;
            case RD_KAFKA_RESP_ERR__PARTITION_EOF:
                echo "No more messages; waiting...\n";
                break;
            case RD_KAFKA_RESP_ERR__TIMED_OUT:
                echo "Timed out...\n";
                break;
            default:
                echo "Error: " . $message->errstr() . "\n";
                break;
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
} 