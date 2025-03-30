<?php

declare(strict_types=1);

return [
    // 限流配置
    'create' => ['capacity' => 5, 'seconds' => 60],  // 每分钟最多创建5个红包
    'grab'   => ['capacity' => 20, 'seconds' => 60],   // 每分钟最多抢20个红包
];