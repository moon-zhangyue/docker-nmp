<?php
/**
 * Custom configuration for XHGui.
 */

return [
    // MongoDB 配置
    'mongodb' => [
        'hostname' => 'xhgui-mongo', // 使用容器名称
        'port' => 27017,
        'database' => 'xhgui',
        'options' => [],
        'driverOptions' => [],
    ],
    
    // 设置路径前缀
    'path.prefix' => '',
    
    // 时区设置
    'timezone' => 'Asia/Shanghai',
]; 