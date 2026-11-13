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
use app\middleware\AuthCheck;
use support\Request;

// ============================================================
// 公开路由：默认不挂中间件，可匿名访问
// ============================================================

// 注册 / 登录必须公开，否则无法建立会话
Route::post('/user/register', [UserController::class, 'register']);
Route::post('/user/login', [UserController::class, 'login']);

// 首页
Route::get('/', function (Request $request) {
    return response('Hello Webman!');
});

// 测试路由
Route::post('/login', function (Request $request) {
    $data = $request->post();
    return json(['message' => 'Logged in', 'data' => $data]);
});

Route::group('/api', function () {
    Route::get('/test', function () {
        return 'API Test';
    });
});

Route::any('/anything', function () {
    return 'Any method allowed';
});

// ============================================================
// 需要登录的路由组：显式挂载 AuthCheck
// 新增敏感接口请放进本组，或单独 ->middleware(AuthCheck::class)
// ============================================================
Route::group('/user', function () {
    Route::get('/info', [UserController::class, 'info']);
    Route::get('/get-by-id', [UserController::class, 'getUserById']);
    Route::post('/logout', [UserController::class, 'logout']);
})->middleware(AuthCheck::class);

// 带参数的测试路由（公开）。
// 必须放在所有 /user 静态路由之后注册：nikic/fast-route 不允许先定义变量路由
// 再定义同前缀静态路由，否则抛 BadRouteException "Static route ... is shadowed"。
Route::get('/user/{id}', function (Request $request, $id) {
    return response("User ID: $id");
});

// 关闭默认路由（避免控制器方法被自动映射成不受鉴权约束的 URL）
Route::disableDefaultRoute();