<?php

use think\facade\Route;

// Mongo路由组
Route::group('mongo', function () {
    // MongoDB产品目录相关路由
    Route::group('products', function () {
        Route::post('create', 'mongo/ProductController/create');//创建产品  
        Route::put('update', 'mongo/ProductController/update');//更新产品   
        Route::get('search', 'mongo/ProductController/search');//搜索产品   
        Route::get('detail/:id', 'mongo/ProductController/detail');//获取产品详情
        Route::get('list', 'mongo/ProductController/list');//获取产品列表
    });

    // MongoDB IoT数据分片相关路由
    Route::group('iot', function () {
        Route::post('batch-data', 'mongo/IoTController/receiveBatchData');
        Route::get('device-metrics/:device_id', 'mongo/IoTController/getDeviceMetrics');
        Route::post('archive-data', 'mongo/IoTController/archiveOldDeviceData');
        Route::post('receive-data', 'mongo/IoTController/receiveData');//接收设备数据
    });
    
    // MongoDB地理位置相关路由
    Route::group('location', function () {
        Route::post('add', 'mongo/LocationController/add');//添加位置
        Route::get('nearby', 'mongo/LocationController/nearby');//查找附近位置
        Route::post('check-polygon', 'mongo/LocationController/checkPointInPolygon');//检查点是否在多边形内
        Route::post('save', 'mongo/LocationController/save');//保存位置信息
        Route::put('update', 'mongo/LocationController/update');//更新位置信息
        Route::get('nearbyLocation', 'mongo/LocationController/nearbyLocation');//查找附近位置
    });

    // MongoDB分析聚合相关路由
    Route::group('analytics', function () {
        Route::post('record', 'mongo/AnalyticsController/record');//记录用户行为
        Route::post('timeStats', 'mongo/AnalyticsController/timeStats');//按时间段统计用户行为
        Route::get('activeUsers', 'mongo/AnalyticsController/activeUsers');//获取活跃用户数据
        Route::post('seedOrders', 'mongo/AnalyticsController/seedOrders');//生成订单数据
        Route::get('productSales', 'mongo/AnalyticsController/productSales');//获取产品销售数据
        Route::get('typeDistribution', 'mongo/AnalyticsController/typeDistribution');//获取行为类型占比
        Route::get('userPath', 'mongo/AnalyticsController/userPath');//获取用户行为路径
    });

    // MongoDB全球数据复制相关路由
    Route::group('global-data', function () {
        Route::post('create', 'GlobalDataController/createGlobalRecord');
        Route::get('regional/:region', 'GlobalDataController/getRegionalData');
        Route::post('replicate', 'GlobalDataController/replicateData');
    });
});
