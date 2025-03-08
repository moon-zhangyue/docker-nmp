<?php
// +----------------------------------------------------------------------
// | 控制台配置
// +----------------------------------------------------------------------
return [
    // 指令定义
    'commands' => [
        'consume:registration' => 'app\command\ConsumeRegistration',
        'kafka:monitor' => 'app\command\KafkaMonitor',
        'queue:consume' => 'app\command\QueueConsumer',
        'queue:monitor' => 'app\command\QueueMonitor',
        'queue:failed' => 'app\command\QueueFailed',
    ],
];
