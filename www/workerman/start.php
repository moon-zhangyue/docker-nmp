<?php

use App\Api\Redis\Controllers\RedisController;
use App\Api\Redis\Services\RedisService;
use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use App\Core\Http\JsonResponse;

require_once __DIR__ . '/vendor/autoload.php';

// 加载Redis配置
$config = require __DIR__ . '/config/redis.php';

// 创建HTTP服务
$http_worker = new Worker("http://0.0.0.0:2345");
$http_worker->count = 4;

// 当Worker进程启动时
$http_worker->onWorkerStart = function() use ($config) {
    global $redisController;
    $redisService = new RedisService($config['default']);
    $redisController = new RedisController($redisService);
    Worker::log("Worker进程启动，Redis服务初始化完成");
};

// 处理请求
$http_worker->onMessage = function(TcpConnection $connection, Request $request) {
    global $redisController;
    
    // 解析请求路径
    $path = trim($request->path(), '/');
    $method = $request->method();
    
    try {
        switch ($path) {
            case 'redis/set':
                $redisController->set($connection, $request);
                break;
            case 'redis/get':
                $redisController->get($connection, $request);
                break;
            case 'redis/hash/set':
                $redisController->hSet($connection, $request);
                break;
            case 'redis/list/push':
                $redisController->lPush($connection, $request);
                break;
            case 'redis/set/add':
                $redisController->sAdd($connection, $request);
                break;
            case 'redis/zset/add':
                $redisController->zAdd($connection, $request);
                break;
            case 'redis/delete':
                $redisController->del($connection, $request);
                break;
            case 'redis/expire':
                $redisController->expire($connection, $request);
                break;
            default:
                JsonResponse::error($connection, '路由未找到: ' . $path);
        }
    } catch (Throwable $e) {
        Worker::log("请求处理失败: " . $e->getMessage());
        JsonResponse::error($connection, $e->getMessage());
    }
};

// 运行worker
Worker::runAll();