<?php
declare(strict_types=1);

// 加载Composer自动加载文件
require __DIR__ . '/vendor/autoload.php';

// 初始化容器
$container = new \think\Container();
\think\Container::setInstance($container);

// 注册ThinkPHP核心类
$container->bind([
    'app'    => \think\App::class,
    'config' => \think\Config::class,
    'cache'  => \think\Cache::class,
    'log'    => \think\Log::class,
]);

// 初始化门面类
\think\facade\App::setFacadeClass(\think\App::class);
\think\facade\Config::setFacadeClass(\think\Config::class);
\think\facade\Cache::setFacadeClass(\think\Cache::class);
\think\facade\Log::setFacadeClass(\think\Log::class);

// 测试Config门面是否正常工作
echo "测试Config门面\n";
try {
    \think\facade\Config::set('test', 'value');
    $value = \think\facade\Config::get('test');
    echo "设置和获取Config值成功：{$value}\n";
} catch (\Exception $e) {
    echo "Config门面测试失败：" . $e->getMessage() . "\n";
}

// 测试Cache门面是否正常工作
echo "\n测试Cache门面\n";
try {
    $cache = \think\facade\Cache::store();
    echo "Cache门面初始化成功\n";
    
    // 尝试基本操作
    \think\facade\Cache::set('test_key', 'test_value', 60);
    $cacheValue = \think\facade\Cache::get('test_key');
    echo "设置和获取Cache值成功：{$cacheValue}\n";
} catch (\Exception $e) {
    echo "Cache门面测试失败：" . $e->getMessage() . "\n";
} 