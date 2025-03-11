<?php

// 引入ThinkPHP框架的自动加载文件
require __DIR__ . '/vendor/autoload.php';

// 初始化应用
$app = new \think\App();
$app->initialize();

echo "正在测试Redis连接...\n";

try {
    // 使用ThinkPHP的缓存门面测试Redis连接
    $testKey = 'redis_test_' . uniqid();
    $testValue = 'Test value at ' . date('Y-m-d H:i:s');
    
    // 尝试写入缓存
    \think\facade\Cache::set($testKey, $testValue, 60);
    echo "写入缓存成功: {$testKey} => {$testValue}\n";
    
    // 尝试读取缓存
    $readValue = \think\facade\Cache::get($testKey);
    echo "读取缓存结果: {$readValue}\n";
    
    if ($readValue === $testValue) {
        echo "Redis连接测试成功! ✓\n";
    } else {
        echo "Redis连接测试失败: 写入和读取的值不匹配 ✗\n";
    }
    
    // 测试队列监控数据
    echo "\n正在测试队列监控数据...\n";
    
    // 创建测试监控数据
    $metrics = [
        'default' => [
            'success' => 5,
            'failed' => 1,
            'last_processed_at' => time()
        ]
    ];
    
    // 写入队列监控数据
    \think\facade\Cache::set('queue_metrics', $metrics);
    echo "写入队列监控数据成功\n";
    
    // 读取队列监控数据
    $readMetrics = \think\facade\Cache::get('queue_metrics');
    echo "读取队列监控数据: " . json_encode($readMetrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    
    if (!empty($readMetrics)) {
        echo "队列监控数据测试成功! ✓\n";
        echo "\n请运行以下命令查看队列监控数据:\n";
        echo "php think queue:metrics\n";
    } else {
        echo "队列监控数据测试失败: 无法读取监控数据 ✗\n";
    }
    
} catch (\Exception $e) {
    echo "Redis连接测试失败: " . $e->getMessage() . " ✗\n";
    echo "请检查Redis配置和连接状态\n";
    
    // 输出Redis配置信息
    $redisConfig = config('cache.stores.redis');
    echo "\nRedis配置信息:\n";
    echo "Host: " . $redisConfig['host'] . "\n";
    echo "Port: " . $redisConfig['port'] . "\n";
    echo "Database: " . $redisConfig['select'] . "\n";
    echo "Password: " . (empty($redisConfig['password']) ? '无' : '已设置') . "\n";
}