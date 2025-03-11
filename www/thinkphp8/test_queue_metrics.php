<?php

// 引入ThinkPHP框架的自动加载文件
require __DIR__ . '/vendor/autoload.php';

// 初始化应用
$app = new \think\App();
$app->initialize();

echo "正在测试队列监控数据...\n";

try {
    // 创建测试监控数据
    $metrics = [
        'default' => [
            'success' => 10,
            'failed' => 2,
            'last_processed_at' => time()
        ],
        'emails' => [
            'success' => 5,
            'failed' => 1,
            'last_processed_at' => time() - 300
        ]
    ];
    
    // 写入队列监控数据到缓存
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
    echo "测试失败: " . $e->getMessage() . " ✗\n";
    echo "错误堆栈: " . $e->getTraceAsString() . "\n";
}