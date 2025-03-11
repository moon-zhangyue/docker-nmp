<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
use think\facade\Route;

Route::get('think', function () {
    return 'hello,ThinkPHP8!';
});

Route::get('hello/:name', 'index/hello');

//用户注册
Route::post('register', 'user/register');

// 队列相关路由
Route::post('queue/push', 'Queue/push');
Route::get('queue/status', 'Queue/status');
Route::post('queue/clear', 'Queue/clear');

//think-queue队列
Route::post('user/redis_queue', 'user/redis_queue'); //redis队列
Route::post('kafkaqueue/push', 'kafkaqueue/push'); //kafka队列

//监控指标
Route::get('metrics/prometheus', 'metrics/prometheus'); //Prometheus指标

// 队列测试路由
Route::post('queue/test', 'QueueTest/push');
Route::post('queue/test/delay', 'QueueTest/pushDelay');
