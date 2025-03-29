<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\RedPacketService;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\Di\Annotation\Inject;

/**
 * @Command
 */
#[Command]
class HandleExpiredRedPacketsCommand extends HyperfCommand
{
    /**
     * 执行的命令行
     *
     * @var string
     */
    protected ?string $name = 'redpacket:handle-expired';

    /**
     * 命令描述
     *
     * @var string
     */
    protected string $description = '处理过期红包';

    /**
     * @Inject
     * @var RedPacketService
     */
    protected $redPacketService;

    public function configure()
    {
        parent::configure();
        $this->setDescription('处理过期红包，将未领取的金额退回给发红包用户');
    }

    /**
     * 执行处理过期红包
     */
    public function handle()
    {
        $this->line('开始处理过期红包...');

        $result = $this->redPacketService->handleExpiredRedPackets();

        $this->info("处理完成！共处理 {$result['count']} 个过期红包，退回金额 {$result['amount']} 元");
    }
}