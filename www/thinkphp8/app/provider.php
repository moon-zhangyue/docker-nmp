<?php
use app\ExceptionHandle;
use app\Request;
use app\provider\ElasticsearchLogServiceProvider;

// 容器Provider定义文件
return [
    'think\Request'          => Request::class,
    'think\exception\Handle' => ExceptionHandle::class,
];
