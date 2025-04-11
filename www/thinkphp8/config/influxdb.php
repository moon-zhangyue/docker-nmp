<?php

declare(strict_types=1);

return [
    // InfluxDB连接配置
    'connection'       => [
        'url'       => env('INFLUXDB_URL', 'http://influxdb:8086'),
        'token'     => env('INFLUXDB_TOKEN', 'CrekyGe5H_Q3rj2_I587ngnhk3J-5vi7BVWcS6GK4l68ZBeuiyRCIF4YK-dF4m13VxORsrbN-L0SPsPbuprUrw=='),
        'bucket'    => env('INFLUXDB_BUCKET', 'mybucket'),
        'org'       => env('INFLUXDB_ORG', 'myorg'),
        'precision' => 'ns', // 精度选项: ns, us, ms, s
    ],

    // 是否启用InfluxDB
    'enabled'          => env('INFLUXDB_ENABLED', 'false'),

    // 数据保留策略（天）
    'retention_days'   => env('INFLUXDB_RETENTION_DAYS', '30'),

    // 批量写入配置
    'batch'            => [
        'enabled'  => true,
        'size'     => 1000,           // 每批次最大点数
        'interval' => 5000,       // 自动刷新间隔（毫秒）
    ],

    // 适合存储到InfluxDB的数据类型筛选条件
    'storage_criteria' => [
        // 时序数据特征筛选
        'time_series'      => true,     // 是否为时间序列数据（必须包含时间戳）
        'write_frequency'  => 'high', // 写入频率: high, medium, low
        'update_frequency' => 'low', // 更新频率: high, medium, low
        'query_pattern'    => 'time_range', // 查询模式: time_range, aggregation, latest, all

        // 数据类型筛选
        'data_types'       => [
            'metrics'               => true,      // 系统和应用监控指标
            'logs'                  => true,         // 应用日志（结构化）
            'events'                => true,       // 事件数据
            'sensor_data'           => true,  // 传感器数据
            'device_telemetry'      => true, // 设备遥测数据
            'user_activity'         => false,   // 用户活动数据（通常需要关联查询）
            'business_transactions' => false, // 业务交易数据（通常需要ACID保证）
        ],

        // 具体应用场景筛选
        'use_cases'        => [
            // 系统监控场景
            'system_monitoring'      => [
                'cpu_usage'       => true,        // CPU使用率
                'memory_usage'    => true,     // 内存使用率
                'disk_usage'      => true,       // 磁盘使用率
                'network_traffic' => true,  // 网络流量
            ],

            // 应用监控场景
            'application_monitoring' => [
                'response_time' => true,     // 响应时间
                'error_rate'    => true,        // 错误率
                'request_rate'  => true,      // 请求率
                'active_users'  => true,      // 活跃用户数
            ],

            // 队列监控场景
            'queue_monitoring'       => [
                'queue_length'    => true,      // 队列长度
                'processing_time' => true,   // 处理时间
                'success_rate'    => true,      // 成功率
                'error_rate'      => true,        // 错误率
                'throughput'      => true,        // 吞吐量
            ],

            // 停车场系统特定场景
            'parking_system'         => [
                'gate_events'       => true,        // 闸机事件（进出场）
                'occupancy_rate'    => true,    // 车位占用率
                'peak_hours'        => true,        // 高峰时段分析
                'parking_duration'  => true,  // 停车时长统计
                'device_status'     => true,    // 设备状态监控
                'plate_recognition' => true, // 车牌识别性能
                'traffic_flow'      => true,     // 车流量分析
                'gate_operations'   => true,  // 闸机操作日志
                'revenue_metrics'   => false,  // 收入指标（应存储在关系型数据库）
                'user_accounts'     => false,    // 用户账户（应存储在关系型数据库）
                'payment_records'   => false,  // 支付记录（应存储在关系型数据库）
                'monthly_passes'    => false,   // 月卡信息（应存储在关系型数据库）
            ],
        ],
    ],

    // 自动清理配置
    'auto_purge'       => [
        'enabled'        => true,
        'check_interval' => 86400, // 检查间隔（秒）
    ],
];