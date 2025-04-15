<?php
/**
 * 用户模块路由配置
 */

use Webman\Route;
use app\controller\UserController;

// 用户模块路由
Route::post('/user/register', [UserController::class, 'register']);
Route::post('/user/login', [UserController::class, 'login']);
Route::get('/user/info', [UserController::class, 'info']);
Route::get('/user/get-by-id', [UserController::class, 'getUserById']);
Route::post('/user/logout', [UserController::class, 'logout']);