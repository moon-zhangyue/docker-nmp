<?php

use think\facade\Route;

// Redis演示路由
Route::group('redis', function () {
    // BitMap演示
    Route::group('bitmap', function () {
        Route::get('', 'redis.BitMapDemo/index'); // 演示页面
        Route::get('basic', 'redis.BitMapDemo/basic'); // 基本用法示例
        Route::get('user-sign', 'redis.BitMapDemo/userSign'); // 用户签到示例
        Route::get('online-status', 'redis.BitMapDemo/onlineStatus'); // 在线状态示例
        Route::get('bloom-filter', 'redis.BitMapDemo/bloomFilter'); // 布隆过滤器示例
    });
    
    // Geo演示
    Route::group('geo', function () {
        Route::get('', 'redis.GeoDemo/index'); // 演示页面
        Route::get('basic', 'redis.GeoDemo/basic'); // 基本用法示例
        Route::get('nearby-users', 'redis.GeoDemo/nearbyUsers'); // 附近的人示例
        Route::get('store-locator', 'redis.GeoDemo/storeLocator'); // 店铺查找示例
        Route::get('route-planning', 'redis.GeoDemo/routePlanning'); // 路径规划示例
    });
    
    // Hash演示
    Route::group('hash', function () {
        Route::get('', 'redis.HashDemo/index'); // 演示页面
        Route::get('basic', 'redis.HashDemo/basic'); // 基本用法示例
        Route::get('user-profile', 'redis.HashDemo/userProfile'); // 用户资料示例
        Route::get('shopping-cart', 'redis.HashDemo/shoppingCart'); // 购物车示例
        Route::get('config-manager', 'redis.HashDemo/configManager'); // 配置管理示例
    });
    
    // HyperLogLog演示
    Route::group('hyperloglog', function () {
        Route::get('', 'redis.HyperLogLogDemo/index'); // 演示页面
        Route::get('basic', 'redis.HyperLogLogDemo/basic'); // 基本用法示例
        Route::get('uv-statistics', 'redis.HyperLogLogDemo/uvStatistics'); // UV统计示例
    });
    
    // List演示
    Route::group('list', function () {
        Route::get('', 'redis.ListDemo/index'); // 演示页面
        Route::get('basic', 'redis.ListDemo/basic'); // 基本用法示例
        Route::get('message-queue', 'redis.ListDemo/messageQueue'); // 消息队列示例
        Route::get('timeline', 'redis.ListDemo/timeline'); // 时间线示例
        Route::get('latest-news', 'redis.ListDemo/latestNews'); // 最新动态示例
    });
    
    // Set演示
    Route::group('set', function () {
        Route::get('', 'redis.SetDemo/index'); // 演示页面
        Route::get('basic', 'redis.SetDemo/basic'); // 基本用法示例
        Route::get('friend-relation', 'redis.SetDemo/friendRelation'); // 好友关系示例
        Route::get('tag-cloud', 'redis.SetDemo/tagCloud'); // 标签云示例
        Route::get('random-prize', 'redis.SetDemo/randomPrize'); // 随机抽奖示例
    });
    
    // String演示
    Route::group('string', function () {
        Route::get('', 'redis.StringDemo/index'); // 演示页面
        Route::get('basic', 'redis.StringDemo/basic'); // 基本用法示例
        Route::get('cacheuser', 'redis.StringDemo/cacheUser');//缓存用户示例
        Route::get('counter', 'redis.StringDemo/counter'); // 计数器示例
        Route::get('distributed-lock', 'redis.StringDemo/distributedLock'); // 分布式锁示例
        Route::get('cache', 'redis.StringDemo/cache'); // 缓存示例
        Route::get('rate-limiter', 'redis.StringDemo/rateLimit'); // 限流器示例
        Route::get('preventcacheAvalanche', 'redis.StringDemo/preventCacheAvalanche'); // 防止缓存雪崩示例
        Route::get('preventcachePenetration', 'redis.StringDemo/preventCachePenetration'); // 防止缓存穿透示例
    });
    
    // ZSet演示
    Route::group('zset', function () {
        Route::get('', 'redis.ZSetDemo/index'); // 演示页面
        Route::get('basic', 'redis.ZSetDemo/basic'); // 基本用法示例
        Route::get('leaderboard', 'redis.ZSetDemo/leaderboard'); // 排行榜示例
        Route::get('weighted-random', 'redis.ZSetDemo/weightedRandom'); // 权重随机示例
        Route::get('delayed-queue', 'redis.ZSetDemo/delayedQueue'); // 延迟队列示例
    });
});
