<?php

// 测试环境变量加载
require __DIR__ . '/vendor/autoload.php';

use think\App;
use think\facade\Env;

// 创建应用实例
$app = new App();

// 输出调试信息
echo "测试环境变量加载情况：\n";
echo "----------------------------------------\n";

// 检查.env文件
echo ".env文件是否存在: " . (file_exists(__DIR__ . '/.env') ? '是' : '否') . "\n";
echo ".env文件权限: " . substr(sprintf('%o', fileperms(__DIR__ . '/.env')), -4) . "\n";

// 检查SENTRY相关环境变量
echo "\nSENTRY环境变量：\n";
// echo "SENTRY_DSN (env函数): " . (env('SENTRY_DSN') ?: '未设置') . "\n";
echo "SENTRY_DSN (getenv): " . (getenv('SENTRY_DSN') ?: '未设置') . "\n";
echo "SENTRY_DSN (Env类): " . (Env::get('SENTRY_DSN') ?: '未设置') . "\n";
// echo "SENTRY_ENVIRONMENT (env函数): " . (env('SENTRY_ENVIRONMENT') ?: '未设置') . "\n";

// 检查KAFKA相关环境变量
echo "\nKAFKA环境变量：\n";
echo "KAFKA_BROKERS (env函数): " . (env('KAFKA_BROKERS') ?: '未设置') . "\n";
echo "KAFKA_GROUP_ID (env函数): " . (env('KAFKA_GROUP_ID') ?: '未设置') . "\n";

// 检查配置文件中的值
echo "\n配置文件中的值：\n";
echo "queue.sentry: " . (\think\facade\Config::get('queue.sentry') ?: '未设置') . "\n";
echo "queue.sentry_config.environment: " . (\think\facade\Config::get('queue.sentry_config.environment') ?: '未设置') . "\n";

// 输出所有环境变量
echo "\n所有环境变量：\n";
print_r(Env::get());

echo "\n----------------------------------------\n";
echo "测试完成\n";
