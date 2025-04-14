<?php

use App\Services\RedisService;
use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;

require_once __DIR__ . '/../vendor/autoload.php';

// 加载Redis配置
$config = require __DIR__ . '/../config/redis.php';

// 创建Worker实例
$worker = new Worker('http://0.0.0.0:2345');
$worker->count = 4;

// 当Worker进程启动时
$worker->onWorkerStart = function() use ($config) {
    // 创建Redis服务实例
    global $redis;
    $redis = new RedisService($config['default']);
    Worker::log("Worker进程启动，Redis服务初始化完成");
};

// 处理请求
$worker->onMessage = function(TcpConnection $connection, Request $request) {
    global $redis;
    
    try {
        // 示例1：字符串操作
        $redis->set('user:1:name', 'Zhang San', 3600);
        
        // 获取值并返回响应
        $redis->getRedis()->get('user:1:name')->then(
            function ($name) use ($connection, $redis) {
                // 示例2：哈希表操作
                $redis->hSet('user:1', 'email', 'zhangsan@example.com');
                
                // 示例3：列表操作
                $redis->lPush('notifications', 'New message received');
                
                // 示例4：集合操作
                $redis->sAdd('online_users', 'user:1');
                
                // 示例5：有序集合操作
                $redis->zAdd('leaderboard', 100.0, 'player:1');
                
                // 返回响应
                $response = new Response(200, [
                    'Content-Type' => 'application/json'
                ], json_encode([
                    'code' => 0,
                    'msg' => 'Redis操作示例',
                    'data' => [
                        'name' => $name
                    ]
                ]));
                
                $connection->send($response);
            },
            function ($e) use ($connection) {
                Worker::log("Redis GET操作失败: " . $e->getMessage());
                
                $response = new Response(500, [
                    'Content-Type' => 'application/json'
                ], json_encode([
                    'code' => 500,
                    'msg' => '获取数据失败',
                    'error' => $e->getMessage()
                ]));
                
                $connection->send($response);
            }
        );
    } catch (Throwable $e) {
        Worker::log("请求处理失败: " . $e->getMessage());
        
        $response = new Response(500, [
            'Content-Type' => 'application/json'
        ], json_encode([
            'code' => 500,
            'msg' => '服务器内部错误',
            'error' => $e->getMessage()
        ]));
        
        $connection->send($response);
    }
};

// 运行worker
Worker::runAll(); 