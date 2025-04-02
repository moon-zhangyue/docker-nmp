<?php

// 启用严格类型模式，确保代码中的类型声明是强制的
declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
// 设置PHP配置，开启显示所有错误
ini_set('display_errors', 'on');
// 设置PHP配置，开启显示启动错误
ini_set('display_startup_errors', 'on');

// 设置错误报告级别为显示所有错误
error_reporting(E_ALL);
// 设置默认时区为亚洲/上海
date_default_timezone_set('Asia/Shanghai');

// 启用Swoole的协程功能
Swoole\Runtime::enableCoroutine(true);

// 如果常量BASE_PATH未定义，则定义它为当前目录的上一级目录
!defined('BASE_PATH') && define('BASE_PATH', dirname(__DIR__, 1));

// 引入自动加载文件，通常位于vendor/autoload.php
require BASE_PATH . '/vendor/autoload.php';

// 如果常量SWOOLE_HOOK_FLAGS未定义，则定义它为Hyperf\Engine\DefaultOption中的hookFlags
!defined('SWOOLE_HOOK_FLAGS') && define('SWOOLE_HOOK_FLAGS', Hyperf\Engine\DefaultOption::hookFlags());

// 初始化Hyperf的类加载器
Hyperf\Di\ClassLoader::init();

// 引入容器配置文件，通常位于config/container.php，并赋值给$container变量
$container = require BASE_PATH . '/config/container.php';

// 从容器中获取Hyperf\Contract\ApplicationInterface的实现类实例
$container->get(Hyperf\Contract\ApplicationInterface::class);
