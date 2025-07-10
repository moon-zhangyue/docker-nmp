<?php

return [
    // 默认使用的数据库连接配置
    'default'         => env('database.driver', 'postgresql'),

    // 自定义时间查询规则
    'time_query_rule' => [],

    // 自动写入时间戳字段
    // true为自动识别类型 false关闭
    // 字符串则明确指定时间字段类型 支持 int timestamp datetime date
    'auto_timestamp'  => true,

    // 时间字段取出后的默认时间格式
    'datetime_format' => 'Y-m-d H:i:s',

    // 时间字段配置 配置格式：create_time,update_time
    'datetime_field'  => '',

    // 数据库连接配置信息
    'connections'     => [
        'mysql'         => [
            // 数据库类型
            'type'            => 'mysql',
            // 服务器地址
            'hostname'        => env('database.db_host', 'mysql5'),
            // 数据库名
            'database'        => env('database.db_name', 'tp8'),
            // 用户名
            'username'        => env('database.db_user', 'root'),
            // 密码
            'password'        => env('database.db_pass', '123456'),
            // 端口
            'hostport'        => env('database.db_port', '3306'),
            // 数据库连接参数
            'params'          => [],
            // 数据库编码默认采用utf8
            'charset'         => env('database.db_charset', 'utf8mb4'),
            // 数据库表前缀
            'prefix'          => env('database.db_prefix', ''),

            // 数据库部署方式:0 集中式(单一服务器),1 分布式(主从服务器)
            'deploy'          => 0,
            // 数据库读写是否分离 主从式有效
            'rw_separate'     => false,
            // 读写分离后 主服务器数量
            'master_num'      => 1,
            // 指定从服务器序号
            'slave_no'        => '',
            // 是否严格检查字段是否存在
            'fields_strict'   => true,
            // 是否需要断线重连
            'break_reconnect' => false,
            // 监听SQL
            'trigger_sql'     => env('app_debug', true),
            // 开启字段缓存
            'fields_cache'    => false,
        ],

        // PostgreSQL连接配置
        'postgresql'    => [
            // 数据库类型
            'type'                 => 'pgsql',
            // 'type'                 => '\think\db\connector\PgsqlExt',
            // 服务器地址
            'hostname'             => env('pgsql.hostname', 'postgres'),
            // 数据库名
            'database'             => env('pgsql.database', 'tp8'),
            // 用户名
            'username'             => env('pgsql.username', 'postgres'),
            // 密码
            'password'             => env('pgsql.password', '123456'),
            // 端口
            'hostport'             => env('pgsql.hostport', '5432'),
            // 数据库连接参数
            'params'               => [],
            // 数据库编码默认采用utf8
            'charset'              => env('pgsql.charset', 'utf8'),
            // 数据库表前缀
            'prefix'               => env('pgsql.prefix', ''),
            // 数据库部署方式:0 集中式(单一服务器),1 分布式(主从服务器)
            'deploy'               => 0,
            // 数据库读写是否分离 主从式有效
            'rw_separate'          => false,
            // 读写分离后 主服务器数量
            'master_num'           => 1,
            // 指定从服务器序号
            'slave_no'             => '',
            // 是否严格检查字段是否存在
            'fields_strict'        => true,
            // 是否需要断线重连
            'break_reconnect'      => true,
            // 监听SQL
            'trigger_sql'          => env('app_debug', true),
            // 开启字段缓存
            'fields_cache'         => false,
            // 慢查询阈值
            'slow_query_threshold' => 1000,
            // 慢查询日志
            'slow_query_log'       => true,
            // Schema配置
            'schema'               => 'public',
            // 是否使用连接池
            'use_pool'             => true,
            // 连接池数量
            'max_connections'      => 50,
        ],

        // MongoDB副本集连接配置
        'mongo'         => [
            // 数据库类型
            'type'                 => 'mongo',
            // 服务器地址
            'hostname'             => env('MONGO.MONGO_HOSTNAME', 'mongodb'),
            // 连接dsn，支持副本集
            'dsn'                  => env('MONGO.MONGO_DSN', 'mongodb://root:123456@mongodb:27017/thinkphp8?authSource=admin'),
            // 数据库名
            'database'             => env('MONGO.MONGO_DATABASE', 'thinkphp8'),
            // 用户名
            'username'             => env('MONGO.MONGO_USERNAME', 'root'),
            // 密码
            'password'             => env('MONGO.MONGO_PASSWORD', '123456'),
            // 副本集设置
            'replica_set'          => env('MONGO.replica_set', 'rs0'),
            // 是否开启读写分离
            'rw_separate'          => true,
            // 读偏好设置（nearest, primaryPreferred, secondary, secondaryPreferred）
            'read_preference'      => 'secondaryPreferred',
            // 是否启用查询缓存
            'query_cache_enable'   => true,
            // 查询缓存有效期
            'query_cache_expire'   => 7200,
            // 查询缓存前缀
            'query_cache_prefix'   => 'mongodb:',
            // 是否需要断线重连
            'break_reconnect'      => true,
            // 慢查询阈值
            'slow_query_threshold' => 1000,
            // 慢查询日志
            'slow_query_log'       => true,
            // 监听SQL
            'trigger_sql'          => env('app_debug', true),
            // 数据库部署方式:0 集中式(单一服务器),1 分布式(主从服务器)
            'deploy'               => 0,
            // 端口
            'hostport'             => env('MONGO.MONGO_HOSTPORT', '27017'),
            // 数据库编码默认采用utf8
            'charset'              => env('MONGO.MONGO_CHARSET', 'utf8mb4'),
            // 集合（表）前缀，MongoDB 通常不使用
            'prefix'               => env('MONGO.PREFIX', ''),
            // 连接参数
            'options'              => [
                // 连接超时时间（毫秒）
                'connectTimeoutMS' => 5000,
                // Socket超时时间（毫秒）
                'socketTimeoutMS'  => 60000,
                // 是否启用SSL连接
                'ssl'              => env('MONGO.SSL', false),
                // 是否自动重连
                'retryWrites'      => true,
                // 连接池数量
                'maxPoolSize'      => 50,
                // 最少连接数
                'minPoolSize'      => 5,
                // 自动重连尝试次数
                'maxRetries'       => 3,
                // 写入关注
                'w'                => 'majority',  // 确保数据写入到大多数节点
                // 读关注
                'readConcern'      => 'majority', // 确保从大多数节点读取最新数据
                // 写入超时（毫秒）
                'wTimeoutMS'       => 10000,
                // 认证源
                // 'authSource'       => 'admin',
            ],
        ],

        // MongoDB分片集群连接配置 
        'mongo_sharded' => [
            // 数据库类型
            'type'                 => 'mongodb',
            // 连接dsn，支持分片集群（mongos路由）
            'dsn'                  => env('mongo_sharded.dsn', 'mongodb://mongos1.example.com:27017,mongos2.example.com:27017/admin'),
            // 数据库名
            'database'             => env('mongo_sharded.database', 'sharded_db'),
            // 用户名
            'username'             => env('mongo_sharded.username', ''),
            // 密码
            'password'             => env('mongo_sharded.password', ''),
            // 是否启用查询缓存
            'query_cache_enable'   => true,
            // 查询缓存有效期
            'query_cache_expire'   => 3600,
            // 查询缓存前缀
            'query_cache_prefix'   => 'mongodb_sharded:',
            // 是否需要断线重连
            'break_reconnect'      => true,
            // 慢查询阈值
            'slow_query_threshold' => 1000,
            // 慢查询日志
            'slow_query_log'       => true,
            // 连接参数
            'options'              => [
                // 连接超时时间（毫秒）
                'connectTimeoutMS' => 5000,
                // Socket超时时间（毫秒）
                'socketTimeoutMS'  => 60000,
                // 是否启用SSL连接
                'ssl'              => env('mongo_sharded.ssl', false),
                // 是否自动重连
                'retryWrites'      => true,
                // 连接池数量
                'maxPoolSize'      => 100,
                // 最少连接数
                'minPoolSize'      => 10,
                // 自动重连尝试次数
                'maxRetries'       => 5,
                // 写入关注
                'w'                => 'majority',
                // 读关注
                'readConcern'      => 'majority',
                // 写入超时（毫秒）
                'wTimeoutMS'       => 15000
            ],
        ],
    ],
];
