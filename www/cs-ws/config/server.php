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
    // 使用 ext-event（libevent/epoll）事件循环；webman 启动时会写入 Worker::$eventLoopClass。
    // 依赖 php85 镜像已安装 event 扩展，扩展未加载时启动会明确报错，避免静默降级到 select。
    'event_loop'       => \Workerman\Events\Event::class,    // 指定使用 ext-event 事件循环类，提高性能
    'stop_timeout'     => 2,                                  // 服务停止超时时间，单位秒
    'pid_file'         => runtime_path() . '/webman.pid',    // 进程ID文件路径，用于记录主进程ID
    'status_file'      => runtime_path() . '/webman.status', // 状态文件路径，用于记录服务状态信息
    'stdout_file'      => runtime_path() . '/logs/stdout.log', // 标准输出日志文件路径
    'log_file'         => runtime_path() . '/logs/workerman.log', // Workerman日志文件路径
    'max_package_size' => 10 * 1024 * 1024                   // 最大数据包大小限制，单位字节，默认为10MB
];
