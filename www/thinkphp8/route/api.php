<?php

use think\facade\Route;

// API路由组
Route::group('api', function () {
    // 认证相关路由
    Route::group('auth', function () {
        Route::post('login', 'api.Auth/login');  // 登录
        Route::post('register', 'api.Auth/register');  // 注册
        Route::post('refresh', 'api.Auth/refresh');  // 刷新令牌
        Route::post('logout', 'api.Auth/logout')->middleware('jwt_auth');  // 登出
        Route::get('me', 'api.Auth/me')->middleware('jwt_auth');  // 获取当前用户信息
    });

    // 队列管理相关路由
    Route::group('queue', function () {
        Route::get('connections', 'api.QueueManager/getConnections')->middleware('jwt_auth', ['viewer', 'operator', 'admin']);
        Route::post('connections/update', 'api.QueueManager/updateConnection')->middleware('jwt_auth', ['admin']);
        Route::get('status', 'api.QueueManager/getStatus')->middleware('jwt_auth', ['viewer', 'operator', 'admin']);
        Route::post('push', 'api.QueueManager/push')->middleware('jwt_auth', ['operator', 'admin']);
        Route::get('dead-letters', 'api.QueueManager/getDeadLetters')->middleware('jwt_auth', ['viewer', 'operator', 'admin']);
        Route::post('dead-letters/clear', 'api.QueueManager/clearDeadLetters')->middleware('jwt_auth', ['admin']);
        Route::post('dead-letters/retry', 'api.QueueManager/retryDeadLetter')->middleware('jwt_auth', ['operator', 'admin']);
    });
    
    // Kafka相关路由
    Route::group('kafka', function () {
        Route::get('topics', 'api.KafkaManager/getTopics')->middleware('jwt_auth', ['viewer', 'operator', 'admin']);
        Route::post('topics/create', 'api.KafkaManager/createTopic')->middleware('jwt_auth', ['admin']);
        Route::post('topics/delete', 'api.KafkaManager/deleteTopic')->middleware('jwt_auth', ['admin']);
        Route::get('brokers', 'api.KafkaManager/getBrokers')->middleware('jwt_auth', ['viewer', 'operator', 'admin']);
    });
    
    // 系统管理相关路由
    Route::group('system', function () {
        Route::get('users', 'api.System/getUsers')->middleware('jwt_auth', ['admin']);
        Route::post('users/create', 'api.System/createUser')->middleware('jwt_auth', ['admin']);
        Route::post('users/update', 'api.System/updateUser')->middleware('jwt_auth', ['admin']);
        Route::post('users/delete', 'api.System/deleteUser')->middleware('jwt_auth', ['admin']);
        Route::get('roles', 'api.System/getRoles')->middleware('jwt_auth', ['admin']);
        Route::post('roles/create', 'api.System/createRole')->middleware('jwt_auth', ['admin']);
        Route::post('roles/update', 'api.System/updateRole')->middleware('jwt_auth', ['admin']);
        Route::post('roles/delete', 'api.System/deleteRole')->middleware('jwt_auth', ['admin']);
        Route::get('logs', 'api.System/getLogs')->middleware('jwt_auth', ['admin', 'operator']);
        Route::get('audit-logs', 'api.System/getAuditLogs')->middleware('jwt_auth', ['admin']);
    });
    
    // 监控相关路由
    Route::group('monitoring', function () {
        Route::get('metrics', 'api.Monitoring/getMetrics')->middleware('jwt_auth', ['viewer', 'operator', 'admin']);
        Route::get('health', 'api.Monitoring/getHealth');
        Route::get('consumers', 'api.Monitoring/getConsumers')->middleware('jwt_auth', ['viewer', 'operator', 'admin']);
    });
    
    // 连接池相关路由
    Route::group('pool', function () {
        Route::get('status', 'api.Pool/getStatus')->middleware('jwt_auth', ['admin', 'operator']);
        Route::post('config', 'api.Pool/updateConfig')->middleware('jwt_auth', ['admin']);
    });
});

// 注册中间件
// 在app/middleware.php中添加如下内容：
// 'jwt_auth' => \app\middleware\JwtAuth::class, 

// 不需要验证的路由
Route::group(function () {
    // 登录相关接口
    Route::post('auth/login', 'api.Auth/login');
    Route::post('auth/register', 'api.Auth/register');
    Route::post('auth/refresh', 'api.Auth/refresh');
});

// 需要验证的路由
Route::group(function () {
    // 用户相关
    Route::post('auth/logout', 'api.Auth/logout');
    Route::get('auth/me', 'api.Auth/me');
    
    // 其他需要验证的接口...
})->middleware('jwt_auth'); 