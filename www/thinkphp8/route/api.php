<?php

use think\facade\Route;

// API路由组
Route::group('api', function () {
    // 认证相关路由
    Route::group('auth', function () {
        Route::post('login', 'api.Auth/login');  // 登录
        Route::post('register', 'api.Auth/register');  // 注册
        Route::post('refresh', 'api.Auth/refresh');  // 刷新令牌
        Route::post('logout', 'api.Auth/logout')->middleware(['jwt_auth']);  // 登出
        Route::get('me', 'api.Auth/me')->middleware(['jwt_auth']);  // 获取当前用户信息
    });
    
    // 队列管理相关路由
    Route::group('queue', function () {
        // 队列配置
        Route::get('connections', 'api.QueueManager/getConnections')->middleware(['jwt_auth:viewer,operator,admin']);  // 获取所有队列连接
        Route::post('connections/update', 'api.QueueManager/updateConnection')->middleware(['jwt_auth:admin']);  // 更新队列连接配置
        
        // 队列状态
        Route::get('status', 'api.QueueManager/getStatus')->middleware(['jwt_auth:viewer,operator,admin']);  // 获取队列状态
        
        // 队列操作
        Route::post('push', 'api.QueueManager/push')->middleware(['jwt_auth:operator,admin']);  // 推送消息到队列
        
        // 死信队列管理
        Route::get('dead-letters', 'api.QueueManager/getDeadLetters')->middleware(['jwt_auth:viewer,operator,admin']);  // 获取死信队列消息
        Route::post('dead-letters/clear', 'api.QueueManager/clearDeadLetters')->middleware(['jwt_auth:admin']);  // 清空死信队列
        Route::post('dead-letters/retry', 'api.QueueManager/retryDeadLetter')->middleware(['jwt_auth:operator,admin']);  // 重试死信队列消息
    });
    
    // Kafka相关路由
    Route::group('kafka', function () {
        Route::get('topics', 'api.KafkaManager/getTopics')->middleware(['jwt_auth:viewer,operator,admin']);  // 获取所有主题
        Route::post('topics/create', 'api.KafkaManager/createTopic')->middleware(['jwt_auth:admin']);  // 创建主题
        Route::post('topics/delete', 'api.KafkaManager/deleteTopic')->middleware(['jwt_auth:admin']);  // 删除主题
        Route::get('brokers', 'api.KafkaManager/getBrokers')->middleware(['jwt_auth:viewer,operator,admin']);  // 获取所有Broker
    });
    
    // 系统管理相关路由
    Route::group('system', function () {
        // 用户管理
        Route::get('users', 'api.System/getUsers')->middleware(['jwt_auth:admin']);  // 获取所有用户
        Route::post('users/create', 'api.System/createUser')->middleware(['jwt_auth:admin']);  // 创建用户
        Route::post('users/update', 'api.System/updateUser')->middleware(['jwt_auth:admin']);  // 更新用户
        Route::post('users/delete', 'api.System/deleteUser')->middleware(['jwt_auth:admin']);  // 删除用户
        
        // 角色管理
        Route::get('roles', 'api.System/getRoles')->middleware(['jwt_auth:admin']);  // 获取所有角色
        Route::post('roles/create', 'api.System/createRole')->middleware(['jwt_auth:admin']);  // 创建角色
        Route::post('roles/update', 'api.System/updateRole')->middleware(['jwt_auth:admin']);  // 更新角色
        Route::post('roles/delete', 'api.System/deleteRole')->middleware(['jwt_auth:admin']);  // 删除角色
        
        // 日志查看
        Route::get('logs', 'api.System/getLogs')->middleware(['jwt_auth:admin,operator']);  // 获取系统日志
        Route::get('audit-logs', 'api.System/getAuditLogs')->middleware(['jwt_auth:admin']);  // 获取审计日志
    });
    
    // 监控相关路由
    Route::group('monitoring', function () {
        Route::get('metrics', 'api.Monitoring/getMetrics')->middleware(['jwt_auth:viewer,operator,admin']);  // 获取系统指标
        Route::get('health', 'api.Monitoring/getHealth');  // 获取系统健康状态（不需要认证）
        Route::get('consumers', 'api.Monitoring/getConsumers')->middleware(['jwt_auth:viewer,operator,admin']);  // 获取消费者状态
    });
    
    // 连接池相关路由
    Route::group('pool', function () {
        Route::get('status', 'api.Pool/getStatus')->middleware(['jwt_auth:admin,operator']);  // 获取连接池状态
        Route::post('config', 'api.Pool/updateConfig')->middleware(['jwt_auth:admin']);  // 更新连接池配置
    });
});

// 注册中间件
// 在app/middleware.php中添加如下内容：
// 'jwt_auth' => \app\middleware\JwtAuth::class, 