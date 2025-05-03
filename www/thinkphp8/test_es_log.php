<?php

// 定义应用目录
define('APP_PATH', __DIR__ . '/app/');

// 加载基础文件
require __DIR__ . '/vendor/autoload.php';

// 执行应用并响应
$http     = (new think\App())->http;
$response = $http->run();

use think\facade\Log;

echo "开始测试Elasticsearch日志记录...\n";

try {
    echo "1. 测试基本日志记录\n";
    Log::info('测试信息日志 - ' . date('Y-m-d H:i:s'));
    Log::error('测试错误日志 - ' . date('Y-m-d H:i:s'));
    Log::warning('测试警告日志 - ' . date('Y-m-d H:i:s'));

    echo "2. 测试带上下文的日志\n";
    Log::info('测试带上下文的日志 {time},{data}', [
        'time' => date('Y-m-d H:i:s'),
        'data' => json_encode(['key' => 'value', 'test' => true, 'number' => 123])
    ]);

    echo "3. 测试特定通道日志\n";
    Log::channel('elasticsearch')->info('仅Elasticsearch通道 - ' . date('Y-m-d H:i:s'));

    echo "日志记录成功完成！\n";
    echo "请使用 `php think es:manager --action=list` 检查索引是否创建成功\n";
    echo "然后使用 `php think es:manager --action=view --index=索引名称` 查看日志内容\n";
} catch (\Exception $e) {
    echo "出现错误: " . $e->getMessage() . "\n";
    echo "文件: " . $e->getFile() . " 行: " . $e->getLine() . "\n";
}

// 检查是否存在错误日志
// $logPath = rtrim(runtime_path('log'), '/\\') . DIRECTORY_SEPARATOR . 'es_error.log';
// if (file_exists($logPath)) {
//     echo "\nElasticsearch错误日志内容:\n";
//     echo file_get_contents($logPath);
// }