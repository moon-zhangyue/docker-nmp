<?php
declare(strict_types=1);

require __DIR__ . '/../../../../vendor/autoload.php';

use think\Container;
use think\facade\App;
use think\facade\Config;
use think\facade\Cache;
use think\facade\Log;

$container = Container::getInstance();
Container::setInstance($container);

$app = new \think\App();
$container->instance('app', $app);

$config = new \think\Config();
$container->instance('config', $config);

$configData = [
    'debug' => true,
    'cache' => [
        'default' => 'file',
        'stores' => [
            'file' => [
                'type' => 'File',
                'path' => __DIR__ . '/runtime/cache/',
                'prefix' => '',
                'expire' => 0,
            ],
            'redis' => [
                'type' => 'Redis',
                'host' => 'redis',
                'port' => 6379,
                'prefix' => 'think_',
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
                'group_id' => 'think-queue',
                'topics' => ['default'],
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
];

$config->set($configData);

$cache = new \think\Cache($app);
$container->instance('cache', $cache);

$log = new \think\Log($app);
$container->instance('log', $log);

$runtimePath = __DIR__ . '/runtime/';
if (!is_dir($runtimePath)) {
    mkdir($runtimePath, 0777, true);
}

$logPath = $runtimePath . 'log/';
if (!is_dir($logPath)) {
    mkdir($logPath, 0777, true);
}

$cachePath = $runtimePath . 'cache/';
if (!is_dir($cachePath)) {
    mkdir($cachePath, 0777, true);
} 