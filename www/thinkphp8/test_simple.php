<?php
declare(strict_types=1);

// 加载Composer自动加载文件
require __DIR__ . '/vendor/autoload.php';

// 创建容器并注册
$container = new \think\Container();
\think\Container::setInstance($container);

// 注册核心类
$container->bind([
    'app'    => \think\App::class,
    'config' => \think\Config::class,
    'cache'  => \think\Cache::class,
    'log'    => \think\Log::class,
]);

// 设置配置
$config = $container->make('config');
$config->set([
    'cache' => [
        'default' => 'file',
        'stores' => [
            'file' => [
                'type'       => 'file',
                'path'       => __DIR__ . '/runtime/cache/',
                'prefix'     => '',
                'expire'     => 0,
            ],
        ],
    ],
    'log' => [
        'default' => 'file',
        'channels' => [
            'file' => [
                'type' => 'file',
                'path' => __DIR__ . '/runtime/log/',
                'level' => ['error', 'info'],
            ],
        ],
    ],
]);

try {
    // 尝试使用缓存门面
    echo "通过Config门面设置值...\n";
    \think\facade\Config::set(['test_key' => 'test_value']);
    
    echo "通过Config门面获取值: " . \think\facade\Config::get('test_key') . "\n";
    
    // 创建日志目录
    if (!is_dir(__DIR__ . '/runtime/log/')) {
        mkdir(__DIR__ . '/runtime/log/', 0777, true);
    }
    
    // 创建缓存目录
    if (!is_dir(__DIR__ . '/runtime/cache/')) {
        mkdir(__DIR__ . '/runtime/cache/', 0777, true);
    }
    
    // 尝试使用缓存
    echo "设置缓存...\n";
    \think\facade\Cache::set('test_cache_key', 'test_cache_value', 300);
    
    echo "获取缓存: " . \think\facade\Cache::get('test_cache_key') . "\n";
    
    echo "测试成功!\n";
} catch (\Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "在 " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
} 