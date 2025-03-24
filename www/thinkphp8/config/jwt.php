<?php

return [
    /*
    |--------------------------------------------------------------------------
    | JWT密钥
    |--------------------------------------------------------------------------
    |
    | 用于签名JWT的密钥，为了安全，请在生产环境设置一个复杂的随机字符串
    |
    */
    'secret_key' => env('JWT_SECRET', 'thinkphp8-kafka-manager-secret-key'),
    
    /*
    |--------------------------------------------------------------------------
    | JWT加密算法
    |--------------------------------------------------------------------------
    |
    | 支持的算法: HS256, HS384, HS512, RS256, RS384, RS512, ES256, ES384, ES512
    |
    */
    'algorithm' => env('JWT_ALGORITHM', 'HS256'),
    
    /*
    |--------------------------------------------------------------------------
    | 访问令牌有效期（单位：秒）
    |--------------------------------------------------------------------------
    |
    | 默认2小时
    |
    */
    'token_expire' => env('JWT_TOKEN_EXPIRE', 7200),
    
    /*
    |--------------------------------------------------------------------------
    | 刷新令牌有效期（单位：秒）
    |--------------------------------------------------------------------------
    |
    | 默认7天
    |
    */
    'refresh_token_expire' => env('JWT_REFRESH_TOKEN_EXPIRE', 604800),
    
    /*
    |--------------------------------------------------------------------------
    | 黑名单存储设置
    |--------------------------------------------------------------------------
    |
    | JWT黑名单使用的缓存存储驱动，默认为Redis
    |
    */
    'blacklist_driver' => env('JWT_BLACKLIST_DRIVER', 'redis'),
    
    /*
    |--------------------------------------------------------------------------
    | 黑名单宽限期（单位：秒）
    |--------------------------------------------------------------------------
    |
    | 令牌过期后的一段时间内，旧令牌仍可能被使用，这个配置可以避免并发请求问题
    |
    */
    'blacklist_grace_period' => env('JWT_BLACKLIST_GRACE_PERIOD', 30),
    
    // 是否启用黑名单，用于使令牌失效
    'blacklist_enabled' => true,
    
    // 发行者
    'iss' => 'thinkphp_api',
    
    // 受众
    'aud' => 'thinkphp_clients',
    
    // 使用Redis作为黑名单缓存的配置
    'redis' => [
        'host' => env('JWT_REDIS_HOST', 'redis'),
        'port' => env('JWT_REDIS_PORT', 6379),
        'password' => env('JWT_REDIS_PASSWORD', ''),
        'database' => env('JWT_REDIS_DB', 0),
    ],
    
    // 角色定义
    'roles' => [
        // 管理员角色，拥有所有权限
        'admin' => [
            'can_create' => true,
            'can_read' => true,
            'can_update' => true,
            'can_delete' => true,
            'can_manage_users' => true,
            'can_view_logs' => true,
            'can_configure_system' => true,
        ],
        
        // 普通用户角色，只有读取和基础功能的权限
        'user' => [
            'can_create' => false,
            'can_read' => true,
            'can_update' => false,
            'can_delete' => false,
            'can_manage_users' => false,
            'can_view_logs' => false,
            'can_configure_system' => false,
        ],
        
        // 只读用户角色，只有读取权限
        'viewer' => [
            'can_create' => false,
            'can_read' => true,
            'can_update' => false,
            'can_delete' => false,
            'can_manage_users' => false,
            'can_view_logs' => false,
            'can_configure_system' => false,
        ],
        
        // 操作员角色，有基础管理权限但不能配置系统
        'operator' => [
            'can_create' => true,
            'can_read' => true,
            'can_update' => true,
            'can_delete' => false,
            'can_manage_users' => false,
            'can_view_logs' => true,
            'can_configure_system' => false,
        ],
    ],
]; 