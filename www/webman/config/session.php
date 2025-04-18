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

use Webman\Session\FileSessionHandler;
use Webman\Session\RedisSessionHandler;
use Webman\Session\RedisClusterSessionHandler;

return [

    'type'                  => 'redis', // or redis or redis_cluster
    // 'handler'               => FileSessionHandler::class,
    'handler'               => RedisSessionHandler::class,
    'config'                => [
        'file'          => [
            'save_path' => runtime_path() . '/sessions',
        ],
        'redis'         => [
            'host'     => 'redis',
            'port'     => 6379,
            'auth'     => '',
            'timeout'  => 2,
            'database' => 1,
            'prefix'   => 'redis_session_',
        ],
        'redis_cluster' => [
            'host'    => ['redis:7000', 'redis:7001', 'redis:7001'],
            'timeout' => 2,
            'auth'    => '',
            'prefix'  => 'redis_session_',
        ]
    ],

    'session_name'          => 'PHPSID',

    'auto_update_timestamp' => false,

    'lifetime'              => 7 * 24 * 60 * 60,

    'cookie_lifetime'       => 365 * 24 * 60 * 60,

    'cookie_path'           => '/',

    'domain'                => '',

    'http_only'             => true,

    'secure'                => false,

    'same_site'             => '',

    'gc_probability'        => [1, 1000],

];
