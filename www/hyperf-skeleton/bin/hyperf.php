#!/usr/bin/env php
<?php
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
// 设置PHP配置，开启错误显示
ini_set('display_errors', 'on');
// 设置PHP配置，开启启动错误显示
ini_set('display_startup_errors', 'on');
// 设置PHP配置，内存限制为1G
ini_set('memory_limit', '2G');

// 设置错误报告级别，报告所有错误
error_reporting(E_ALL);

// 定义BASE_PATH常量，指向当前脚本的上两级目录
!defined('BASE_PATH') && define('BASE_PATH', dirname(__DIR__, 1));

// 启用Swoole的协程功能
Swoole\Runtime::enableCoroutine(true);

// 引入自动加载文件
require BASE_PATH . '/vendor/autoload.php';

// 定义SWOOLE_HOOK_FLAGS常量，用于设置Swoole的钩子标志
!defined('SWOOLE_HOOK_FLAGS') && define('SWOOLE_HOOK_FLAGS', Hyperf\Engine\DefaultOption::hookFlags());

// Self-called anonymous function that creates its own scope and keep the global namespace clean.
(function () {
    Hyperf\Di\ClassLoader::init();
    /** @var Psr\Container\ContainerInterface $container */
    $container = require BASE_PATH . '/config/container.php';

    $application = $container->get(Hyperf\Contract\ApplicationInterface::class);
    $application->run();
})();
