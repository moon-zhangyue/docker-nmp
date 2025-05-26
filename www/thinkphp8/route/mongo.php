<?php

use think\facade\Route;

// Mongo路由组
Route::group('mongo', function () {
    // MongoDB产品目录相关路由
    Route::group('products', function () {
        Route::post('create', 'mongo/ProductController/create');
        Route::put('update', 'mongo/ProductController/update');
        Route::get('search', 'mongo/ProductController/search');
        Route::get('detail/:id', 'mongo/ProductController/detail');
    });

    // MongoDB IoT数据分片相关路由
    Route::group('iot', function () {
        Route::post('batch-data', 'IoTDataShardedController/receiveBatchData');
        Route::get('device-metrics/:device_id', 'IoTDataShardedController/getDeviceMetrics');
        Route::post('archive-data', 'IoTDataShardedController/archiveOldDeviceData');
    });
    // MongoDB地理位置相关路由
    Route::group('locations', function () {
        Route::post('add', 'mongo/LocationController/addLocation');
        Route::get('nearby', 'mongo/LocationController/findNearby');
        Route::post('check-polygon', 'mongo/LocationController/checkPointInPolygon');
    });

    // MongoDB分析聚合相关路由
    Route::group('analytics', function () {
        Route::post('record', 'mongo/AnalyticsController/record');//记录用户行为
        Route::post('timeStats', 'mongo/AnalyticsController/timeStats');//按时间段统计用户行为
        Route::get('activeUsers', 'mongo/AnalyticsController/activeUsers');//获取活跃用户数据
        Route::post('seedOrders', 'mongo/AnalyticsController/seedOrders');//生成订单数据
        Route::get('productSales', 'mongo/AnalyticsController/productSales');//获取产品销售数据
    });

    // MongoDB全球数据复制相关路由
    Route::group('global-data', function () {
        Route::post('create', 'GlobalDataController/createGlobalRecord');
        Route::get('regional/:region', 'GlobalDataController/getRegionalData');
        Route::post('replicate', 'GlobalDataController/replicateData');
    });
});
