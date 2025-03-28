<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
use Hyperf\HttpServer\Router\Router;

Router::addRoute(['GET', 'POST', 'HEAD'], '/', 'App\Controller\IndexController@index');

Router::get('/favicon.ico', function () {
    return '';
});

// 红包相关路由
Router::addGroup('/api/red-packet', function () {
    // 创建红包
    Router::post('/create', 'App\Controller\RedPacketController@create');
    // 抢红包
    Router::post('/grab', 'App\Controller\RedPacketController@grab');
    // 红包详情
    Router::get('/detail', 'App\Controller\RedPacketController@detail');
});

// //设置一个 GET 请求的路由，绑定访问地址 '/get' 到 App\Controller\IndexController 的 get 方法
// Router::get('/get', 'App\Controller\IndexController::get');

// //设置一个 POST 请求的路由，绑定访问地址