<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

use Webman\Route;
use app\controller\UserController;

// 用户模块路由
Route::post('/user/register', [UserController::class, 'register']);
Route::post('/user/login', [UserController::class, 'login']);
Route::get('/user/info', [UserController::class, 'info']);
Route::get('/user/get-by-id', [app\controller\UserController::class, 'getUserById']);
Route::post('/user/logout', [app\controller\UserController::class, 'logout']);

// 简单 GET 路由
Route::get('/', function (Request $request) {
    return response('Hello Webman!');
});

// 带参数路由
Route::get('/user/{id}', function (Request $request, $id) {
    return response("User ID: $id");
});

// POST 路由
Route::post('/login', function (Request $request) {
    $data = $request->post();
    return json(['message' => 'Logged in', 'data' => $data]);
});

// 分组路由
Route::group('/api', function () {
    Route::get('/test', function () {
        return 'API Test';
    });
});

// 任意方法路由
Route::any('/anything', function () {
    return 'Any method allowed';
});

// 关闭默认路由（如果需要自定义所有路由）
Route::disableDefaultRoute();