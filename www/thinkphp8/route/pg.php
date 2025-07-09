<?php
// +----------------------------------------------------------------------
// | PostgreSQL API 路由
// +----------------------------------------------------------------------

use think\facade\Route;

// 用户相关路由
Route::group('pg/user', function () {
    // 无需登录验证的接口
    Route::post('register', 'pg.User/register'); // 用户注册
    Route::post('login', 'pg.User/login');       // 用户登录

    // 需要登录验证的接口
    Route::get('info', 'pg.User/info');                      // 获取用户信息
    Route::post('update', 'pg.User/update');                 // 更新用户信息
    Route::post('change_password', 'pg.User/changePassword'); // 修改密码
    
    // 地址管理
    Route::get('address', 'pg.User/addressList');            // 获取用户地址列表
    Route::post('address', 'pg.User/addAddress');            // 添加用户地址
    Route::post('address/:id', 'pg.User/updateAddress');     // 更新用户地址
    Route::delete('address/:id', 'pg.User/deleteAddress');   // 删除用户地址
    Route::post('address/:id/default', 'pg.User/setDefaultAddress'); // 设置默认地址
})->middleware(\app\middleware\JwtAuth::class, ['except' => ['register', 'login']]);

// 购物车相关路由
Route::group('pg/cart', function () {
    Route::get('', 'pg.Cart/list');                   // 获取购物车列表
    Route::post('', 'pg.Cart/add');                   // 添加商品到购物车
    Route::put(':id/quantity', 'pg.Cart/updateQuantity');  // 更新购物车商品数量
    Route::put(':id/selected', 'pg.Cart/updateSelected');  // 更新购物车商品选中状态
    Route::put('select_all', 'pg.Cart/selectAll');    // 全选/全不选
    Route::delete(':id', 'pg.Cart/delete');           // 删除购物车商品
    Route::delete('', 'pg.Cart/clear');               // 清空购物车
    Route::get('count', 'pg.Cart/count');             // 获取购物车商品数量
})->middleware(\app\middleware\JwtAuth::class);

// 商品相关路由
Route::group('pg/goods', function () {
    Route::get('', 'pg.Goods/list');                  // 获取商品列表
    Route::get(':id', 'pg.Goods/detail');             // 获取商品详情
    Route::get('sku/:id', 'pg.Goods/sku');            // 获取商品SKU信息
    Route::get('category', 'pg.Goods/categoryList');  // 获取分类列表
    Route::get('brand', 'pg.Goods/brandList');        // 获取品牌列表
}); 