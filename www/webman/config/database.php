<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

return [
    // 默认数据库
    'default'     => 'mysql',

    // 各种数据库配置
    'connections' => [
        'mysql' => [
            'driver'      => 'mysql',
            //            'host'        => '127.0.0.1',
            'host'        => 'mysql5',
            'port'        => 3306,
            'database'    => 'webman_admin',
            'username'    => 'root',
            'password'    => '123456',
            'unix_socket' => '',
            'charset'     => 'utf8mb4',
            'collation'   => 'utf8mb4_general_ci',
            'prefix'      => 'wa_',
            'strict'      => true,
            'engine'      => null,
            'options'     => [
                \PDO::ATTR_TIMEOUT => 3
            ],
            // 'pool'        => [ // 连接池配置，仅支持swoole/swow驱动
            //     'max_connections'    => 5, // 最大连接数
            //     'min_connections'    => 1, // 最小连接数
            //     'wait_timeout'       => 3,    // 从连接池获取连接等待的最大时间，超时后会抛出异常
            //     'idle_timeout'       => 60,   // 连接池中连接最大空闲时间，超时后会关闭回收，直到连接数为min_connections
            //     'heartbeat_interval' => 50, // 连接池心跳检测时间，单位秒，建议小于60秒
            // ],
        ],
    ],
];
