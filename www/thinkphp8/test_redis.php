<?php

try {
    // 读取环境配置
    $host = 'redis'; // 使用本地地址
    $port = 6379;
    
    $redis = new Redis();
    echo "Connecting to Redis at {$host}:{$port}...\n";
    
    // 设置超时时间为5秒
    $timeout = 5;
    $redis->connect($host, $port, $timeout);
    
    // 测试连接
    $pong = $redis->ping();
    echo "Ping response: " . $pong . "\n";
    
    // 测试基本操作
    $redis->set('test_key', 'Hello Redis');
    $value = $redis->get('test_key');
    echo "Test value: " . $value . "\n";
    
    echo "Redis connection test successful!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Error Code: " . $e->getCode() . "\n";
    echo "Stack Trace:\n" . $e->getTraceAsString() . "\n";
} 