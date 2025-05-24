<?php
/**
 * MongoDB配置文件
 * 支持MongoDB的五大特性：文档存储、地理空间、聚合框架、分片、副本集
 */

return [
    // 默认连接配置
    'default' => [
        // 连接URI
        'uri' => env('mongodb.uri', 'mongodb://localhost:27017'),
        
        // 数据库名称
        'database' => env('mongodb.database', 'thinkphp_mongodb'),
        
        // 连接选项
        'options' => [
            // 连接超时（毫秒）
            'connectTimeoutMS' => 10000,
            
            // 服务器选择超时（毫秒）
            'serverSelectionTimeoutMS' => 5000,
            
            // 套接字超时（毫秒）
            'socketTimeoutMS' => 30000,
            
            // 最大连接池大小
            'maxPoolSize' => 100,
            
            // 最小连接池大小
            'minPoolSize' => 5,
            
            // 连接空闲时间（毫秒）
            'maxIdleTimeMS' => 300000,
            
            // 应用程序名称
            'appName' => 'ThinkPHP8-MongoDB',
            
            // 写关注
            'w' => 'majority',
            
            // 写超时（毫秒）
            'wtimeoutMS' => 10000,
            
            // 日志级别
            'journal' => true,
        ],
        
        // 驱动选项
        'driverOptions' => [
            // 类型映射
            'typeMap' => [
                'root' => 'array',
                'document' => 'array',
                'array' => 'array',
            ],
        ],
    ],
    
    // 副本集配置（用于全球数据复制）
    'replica_set' => [
        // 副本集URI
        'uri' => env('mongodb.replica_uri', 'mongodb://mongo1:27017,mongo2:27017,mongo3:27017/?replicaSet=rs0'),
        
        // 数据库名称
        'database' => env('mongodb.replica_database', 'thinkphp_replica'),
        
        // 连接选项
        'options' => [
            'connectTimeoutMS' => 10000,
            'serverSelectionTimeoutMS' => 5000,
            'socketTimeoutMS' => 30000,
            'maxPoolSize' => 50,
            'minPoolSize' => 3,
            
            // 副本集名称
            'replicaSet' => 'rs0',
            
            // 读偏好
            'readPreference' => 'secondaryPreferred',
            
            // 读关注
            'readConcern' => 'majority',
            
            // 写关注
            'w' => 'majority',
            'wtimeoutMS' => 10000,
            'journal' => true,
        ],
        
        'driverOptions' => [
            'typeMap' => [
                'root' => 'array',
                'document' => 'array',
                'array' => 'array',
            ],
        ],
    ],
    
    // 分片集群配置（用于IoT数据分片）
    'sharded_cluster' => [
        // 分片集群URI（通过mongos路由）
        'uri' => env('mongodb.sharded_uri', 'mongodb://mongos1:27017,mongos2:27017'),
        
        // 数据库名称
        'database' => env('mongodb.sharded_database', 'thinkphp_sharded'),
        
        // 连接选项
        'options' => [
            'connectTimeoutMS' => 15000,
            'serverSelectionTimeoutMS' => 10000,
            'socketTimeoutMS' => 60000,
            'maxPoolSize' => 200,
            'minPoolSize' => 10,
            
            // 写关注（分片环境下的配置）
            'w' => 'majority',
            'wtimeoutMS' => 15000,
            'journal' => true,
            
            // 读偏好（分片环境下可以使用primary）
            'readPreference' => 'primary',
        ],
        
        'driverOptions' => [
            'typeMap' => [
                'root' => 'array',
                'document' => 'array',
                'array' => 'array',
            ],
        ],
    ],
    
    // 地理空间配置
    'geospatial' => [
        // 地理空间索引配置
        'indexes' => [
            // 2dsphere索引（用于地球表面的地理空间查询）
            '2dsphere' => [
                'field' => 'location',
                'options' => [
                    'background' => true,
                    'sparse' => true,
                ],
            ],
            
            // 2d索引（用于平面地理空间查询）
            '2d' => [
                'field' => 'coordinates',
                'options' => [
                    'background' => true,
                    'min' => -180,
                    'max' => 180,
                ],
            ],
        ],
        
        // 地理空间查询配置
        'query_options' => [
            // 默认最大距离（米）
            'default_max_distance' => 10000,
            
            // 默认查询限制
            'default_limit' => 100,
            
            // 球面计算（地球半径，米）
            'earth_radius' => 6378137,
        ],
    ],
    
    // 聚合框架配置
    'aggregation' => [
        // 聚合管道配置
        'pipeline_options' => [
            // 允许磁盘使用（处理大数据集）
            'allowDiskUse' => true,
            
            // 游标批次大小
            'batchSize' => 1000,
            
            // 最大执行时间（毫秒）
            'maxTimeMS' => 300000,
        ],
        
        // 预定义聚合管道
        'pipelines' => [
            // 用户行为分析管道
            'user_behavior' => [
                ['$match' => ['user_id' => null]], // 占位符，实际使用时替换
                ['$group' => [
                    '_id' => '$event_type',
                    'count' => ['$sum' => 1],
                    'last_event' => ['$max' => '$timestamp']
                ]],
                ['$sort' => ['count' => -1]]
            ],
            
            // 实时仪表盘管道
            'realtime_dashboard' => [
                ['$match' => [
                    'timestamp' => ['$gte' => null] // 占位符，实际使用时替换
                ]],
                ['$group' => [
                    '_id' => [
                        'hour' => ['$hour' => '$timestamp'],
                        'event_type' => '$event_type'
                    ],
                    'count' => ['$sum' => 1]
                ]],
                ['$sort' => ['_id.hour' => 1]]
            ],
        ],
    ],
    
    // 分片配置
    'sharding' => [
        // 分片键配置
        'shard_keys' => [
            // IoT数据分片键
            'iot_data' => [
                'device_id' => 1,
                'timestamp' => 1
            ],
            
            // 用户数据分片键
            'user_data' => [
                'user_id' => 'hashed'
            ],
            
            // 产品数据分片键
            'product_data' => [
                'category_id' => 1,
                'created_at' => 1
            ],
        ],
        
        // 分片策略
        'strategies' => [
            // 基于哈希的分片
            'hash' => [
                'chunk_size' => 64, // MB
                'balancer' => true,
            ],
            
            // 基于范围的分片
            'range' => [
                'chunk_size' => 64, // MB
                'balancer' => true,
                'auto_split' => true,
            ],
        ],
    ],
    
    // 副本集配置
    'replica_set_config' => [
        // 副本集成员配置
        'members' => [
            [
                'host' => 'mongo1:27017',
                'priority' => 2,
                'votes' => 1,
            ],
            [
                'host' => 'mongo2:27017',
                'priority' => 1,
                'votes' => 1,
            ],
            [
                'host' => 'mongo3:27017',
                'priority' => 1,
                'votes' => 1,
            ],
        ],
        
        // 副本集设置
        'settings' => [
            // 选举超时（毫秒）
            'electionTimeoutMillis' => 10000,
            
            // 心跳间隔（毫秒）
            'heartbeatIntervalMillis' => 2000,
            
            // 心跳超时（毫秒）
            'heartbeatTimeoutSecs' => 10,
            
            // 追赶模式
            'catchUpTimeoutMillis' => 60000,
        ],
    ],
    
    // 集合配置
    'collections' => [
        // 产品目录集合
        'products' => [
            'name' => 'products',
            'options' => [
                'capped' => false,
            ],
            'indexes' => [
                ['key' => ['name' => 'text', 'description' => 'text']],
                ['key' => ['category_id' => 1]],
                ['key' => ['price' => 1]],
                ['key' => ['created_at' => -1]],
            ],
        ],
        
        // IoT数据集合
        'iot_data' => [
            'name' => 'iot_data',
            'options' => [
                'capped' => false,
            ],
            'indexes' => [
                ['key' => ['device_id' => 1, 'timestamp' => -1]],
                ['key' => ['timestamp' => -1]],
                ['key' => ['device_type' => 1]],
            ],
        ],
        
        // 位置数据集合
        'locations' => [
            'name' => 'locations',
            'options' => [
                'capped' => false,
            ],
            'indexes' => [
                ['key' => ['location' => '2dsphere']],
                ['key' => ['type' => 1]],
                ['key' => ['name' => 1]],
            ],
        ],
        
        // 分析数据集合
        'analytics' => [
            'name' => 'analytics',
            'options' => [
                'capped' => false,
            ],
            'indexes' => [
                ['key' => ['user_id' => 1, 'timestamp' => -1]],
                ['key' => ['event_type' => 1]],
                ['key' => ['timestamp' => -1]],
            ],
        ],
        
        // 全球数据集合
        'global_data' => [
            'name' => 'global_data',
            'options' => [
                'capped' => false,
            ],
            'indexes' => [
                ['key' => ['region' => 1]],
                ['key' => ['data_type' => 1]],
                ['key' => ['created_at' => -1]],
                ['key' => ['sync_status' => 1]],
            ],
        ],
    ],
    
    // 缓存配置
    'cache' => [
        // 查询结果缓存时间（秒）
        'query_cache_ttl' => 300,
        
        // 聚合结果缓存时间（秒）
        'aggregation_cache_ttl' => 600,
        
        // 地理空间查询缓存时间（秒）
        'geospatial_cache_ttl' => 180,
        
        // 缓存键前缀
        'cache_prefix' => 'mongodb:',
    ],
    
    // 日志配置
    'logging' => [
        // 是否启用查询日志
        'enable_query_log' => env('mongodb.enable_query_log', false),
        
        // 慢查询阈值（毫秒）
        'slow_query_threshold' => 1000,
        
        // 日志级别
        'log_level' => env('mongodb.log_level', 'info'),
    ],
    
    // 性能配置
    'performance' => [
        // 批量操作大小
        'batch_size' => 1000,
        
        // 并发连接数
        'max_concurrent_connections' => 50,
        
        // 查询超时（毫秒）
        'query_timeout' => 30000,
        
        // 聚合超时（毫秒）
        'aggregation_timeout' => 300000,
    ],
];