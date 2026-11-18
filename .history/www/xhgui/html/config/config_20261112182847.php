<?php
/**
 * Custom configuration for XHGui.
 */

return [
    // MongoDB 配置（docker-compose 中的 mongodb 服务，--auth 模式）
    'mongodb' => [
        'hostname' => 'mongodb', // 使用容器名称
        'port' => 27017,
        'database' => 'xhgui',
        'options' => [
            'username' => 'root',
            'password' => '123456',
            'authSource' => 'admin',
        ],
        'driverOptions' => [],
    ],

    // 设置路径前缀
    'path.prefix' => '',

    // 时区设置
    'timezone' => 'Asia/Shanghai',
];