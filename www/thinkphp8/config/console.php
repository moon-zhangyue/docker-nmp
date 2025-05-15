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
        'queue:metrics' => 'app\command\QueueMetrics',
        'queue:test' => 'app\command\TestQueueMetrics',
        'es-log-view' => 'app\command\ElasticsearchLogViewer',
        'es:manager' => 'app\command\ElasticsearchManager',
        'rabbitmq:consume' => 'app\command\RabbitMQConsumer',
        'app\command\RabbitMQConsumers',
        'seckill:task' => 'app\command\SeckillUpdateStatus',
        'seckill:update-status' => 'app\command\UpdateSeckillStatus',//更新秒杀状态
    ],
];
