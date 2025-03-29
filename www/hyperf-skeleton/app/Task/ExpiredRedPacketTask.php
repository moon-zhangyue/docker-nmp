<?php

declare(strict_types=1);

namespace App\Task;

use App\Log;
use App\Service\RedPacketService;
use Hyperf\Crontab\Annotation\Crontab;
use Hyperf\Di\Annotation\Inject;

#[Crontab(name: "ExpiredRedPacket", rule: "* * * * *", callback: "execute", memo: "每分钟执行一次")]
class ExpiredRedPacketTask
{
    #[Inject]
    protected RedPacketService $redPacketService;

    public function execute()
    {
        // 处理过期红包
        $result = $this->redPacketService->handleExpiredRedPackets();

        // 记录处理结果
        $message = sprintf(
            "处理完成！共处理 %d 个过期红包，退回金额 %s 元",
            $result['count'],
            $result['amount']
        );

        // 这里可以添加日志记录
        Log::info($message);

    }
}