<?php

declare(strict_types=1);

// 加载Composer自动加载文件
require __DIR__ . '/../../../../vendor/autoload.php';

// 初始化应用
$app = new \think\App();
$app->initialize();

// 注册容器
\think\Container::setInstance($app);

// 注册门面类
\think\Facade::bind([
    'think\\facade\\Cache' => 'think\\Cache',
]);
