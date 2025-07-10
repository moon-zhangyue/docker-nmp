<?php
// +----------------------------------------------------------------------
// | PostgreSQL API 路由
// +----------------------------------------------------------------------

use think\facade\Route;

// 用户相关路由
Route::group('pg/user', function () {
    // 无需登录验证的接口
    Route::post('register', 'pg.UserController/register'); // 用户注册
    Route::post('login', 'pg.UserController/login');       // 用户登录

    // 需要登录验证的接口
    Route::get('info', 'pg.UserController/info');                      // 获取用户信息
    Route::post('update', 'pg.UserController/update');                 // 更新用户信息
    Route::post('change_password', 'pg.UserController/changePassword'); // 修改密码

    // 地址管理
    Route::get('address', 'pg.UserController/addressList');            // 获取用户地址列表
    Route::post('address', 'pg.UserController/addAddress');            // 添加用户地址
    Route::post('address/:id', 'pg.UserController/updateAddress');     // 更新用户地址
    Route::delete('address/:id', 'pg.UserController/deleteAddress');   // 删除用户地址
    Route::post('address/:id/default', 'pg.UserController/setDefaultAddress'); // 设置默认地址
});

// 购物车相关路由
Route::group('pg/cart', function () {
    Route::get('', 'pg.CartController/list');                   // 获取购物车列表
    Route::post('', 'pg.CartController/add');                   // 添加商品到购物车
    Route::put(':id/quantity', 'pg.CartController/updateQuantity');  // 更新购物车商品数量
    Route::put(':id/selected', 'pg.CartController/updateSelected');  // 更新购物车商品选中状态
    Route::put('select_all', 'pg.CartController/selectAll');    // 全选/全不选
    Route::delete(':id', 'pg.CartController/delete');           // 删除购物车商品
    Route::delete('', 'pg.CartController/clear');               // 清空购物车
    Route::get('count', 'pg.CartController/count');             // 获取购物车商品数量
});

// 商品相关路由
Route::group('pg/goods', function () {
    Route::get('', 'pg.GoodsController/list');                  // 获取商品列表
    Route::get(':id', 'pg.GoodsController/detail');             // 获取商品详情
    Route::get('sku/:id', 'pg.GoodsController/sku');            // 获取商品SKU信息
    Route::get('category', 'pg.GoodsController/categoryList');  // 获取分类列表
    Route::get('brand', 'pg.GoodsController/brandList');        // 获取品牌列表
});