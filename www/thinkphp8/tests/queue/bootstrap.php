<?php

declare(strict_types=1);

use think\Container;
use think\App;
use think\Config;
use think\Log;
use think\Cache;
use think\facade\Config as ConfigFacade;

// 加载Composer自动加载文件
require __DIR__ . '/../../../../vendor/autoload.php';

// 初始化容器
$container = Container::getInstance();

$app = new App(__DIR__ . '/../../../../');
$container->instance('app', $app);

$config = new Config();
$container->instance('config', $config);

$log = new Log($app);
$container->instance('log', $log);

$cache = new Cache($app);
$container->instance('cache', $cache);

$config->set([
    'app' => [
        'debug' => true,
    ],
    'cache' => [
        'default' => 'file',
        'stores' => [
            'file' => [
                'type' => 'file',
                'path' => __DIR__ . '/runtime/cache/',
                'prefix' => '',
                'expire' => 0,
            ],
        ],
    ],
    'queue' => [
        'default' => 'redis',
        'connections' => [
            'redis' => [
                'type' => 'redis',
                'queue' => 'default',
                'host' => 'redis',
                'port' => 6379,
                'password' => '',
                'select' => 0,
                'timeout' => 0,
                'persistent' => false,
            ],
            'kafka' => [
                'type' => 'kafka',
                'queue' => 'default',
                'brokers' => ['kafka:9092'],
                'consumer_group_id' => 'test_group',
            ],
        ],
    ],
]);

// 创建临时目录
if (!is_dir(__DIR__ . '/runtime/cache/')) {
    mkdir(__DIR__ . '/runtime/cache/', 0777, true);
}

if (!is_dir(__DIR__ . '/runtime/log/')) {
    mkdir(__DIR__ . '/runtime/log/', 0777, true);
}

if (!function_exists('think\\queue\\log\\config')) {
    function config($name = null, $default = null)
    {
        return ConfigFacade::get($name, $default);
    }
}

// 初始化 Cache 驱动
$cache->init($config->get('cache'));
