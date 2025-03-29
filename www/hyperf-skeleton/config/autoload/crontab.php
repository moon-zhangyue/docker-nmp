<?php

declare(strict_types=1);

use Hyperf\Crontab\Crontab;

return [
    // 是否开启定时任务
    'enable'  => true,

    // 通过配置文件定义的定时任务
    'crontab' => [
        // 可以添加更多定时任务
        // Callback类型定时任务（默认）
        // (new Crontab())->setName('ExpiredRedPacket')->setRule('* * * * *')->setCallback([App\Task\ExpiredRedPacketTask::class, 'execute'])->setMemo('处理过期红包的定时任务'),
        // 这里使用了注解方式定义定时任务，所以这里可以为空
    ],
];