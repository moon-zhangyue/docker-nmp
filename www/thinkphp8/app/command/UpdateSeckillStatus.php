<?php
declare(strict_types=1);

namespace app\command;

use app\model\SeckillActivity;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\facade\Cache;
use think\facade\Log;

/**
 * 更新秒杀活动状态命令
 */
class UpdateSeckillStatus extends Command
{
    /**
     * 配置指令
     */
    protected function configure()
    {
        $this->setName('seckill:update-status')
            ->setDescription('更新所有秒杀活动的状态')
            ->addOption('force', 'f', Option::VALUE_NONE, '强制更新所有活动状态，忽略时间限制');
    }

    /**
     * 执行指令
     *
     * @param Input $input 输入对象
     * @param Output $output 输出对象
     * @return int 返回状态码
     */
    protected function execute(Input $input, Output $output): int
    {
        $output->writeln('开始更新秒杀活动状态...');

        try {
            $force = $input->getOption('force');
            $count = $this->updateAllActivitiesStatus($force, $output);

            $output->writeln("成功更新 {$count} 个秒杀活动的状态");
            return 0;
        } catch (\Exception $e) {
            $output->error('更新秒杀活动状态失败: ' . $e->getMessage());
            Log::error('更新秒杀活动状态失败: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * 更新所有活动状态
     *
     * @param bool $force 是否强制更新所有活动
     * @param Output $output 输出对象
     * @return int 更新的活动数量
     */
    protected function updateAllActivitiesStatus(bool $force, Output $output): int
    {
        $query = SeckillActivity::where('status', '<>', SeckillActivity::STATUS_CANCELED);

        // 如果不是强制更新，只更新未来24小时内开始或结束的活动
        if (!$force) {
            $now = time();
            $tomorrow = $now + 86400; // 24小时

            $query->where(function ($q) use ($now, $tomorrow) {
                $q->whereOr([
                    ['start_time', '<=', $tomorrow], // 24小时内开始的活动
                    ['end_time', '>=', $now, 'and', 'end_time', '<=', $tomorrow], // 24小时内结束的活动
                    ['start_time', '<=', $now, 'and', 'end_time', '>=', $now] // 当前正在进行的活动
                ]);
            });
        }

        $activities = $query->select();
        $count = 0;

        foreach ($activities as $activity) {
            $oldStatus = $activity->status;
            $activity->updateStatus();

            if ($oldStatus != $activity->status) {
                $statusText = $activity->getStatusText();
                $output->writeln("活动 [{$activity->id}] {$activity->title} 状态已更新为: {$statusText}");
                $count++;

                // 记录状态变更日志
                Log::info("秒杀活动 [{$activity->id}] {$activity->title} 状态从 {$oldStatus} 更新为 {$activity->status}");

                // 如果活动刚刚开始，可以在这里添加额外的处理逻辑
                if ($oldStatus == SeckillActivity::STATUS_NOT_STARTED && $activity->status == SeckillActivity::STATUS_IN_PROGRESS) {
                    $this->handleActivityStarted($activity, $output);
                }

                // 如果活动刚刚结束，可以在这里添加额外的处理逻辑
                if ($oldStatus == SeckillActivity::STATUS_IN_PROGRESS && $activity->status == SeckillActivity::STATUS_ENDED) {
                    $this->handleActivityEnded($activity, $output);
                }
            }
        }

        return $count;
    }

    /**
     * 处理活动开始事件
     *
     * @param SeckillActivity $activity 活动对象
     * @param Output $output 输出对象
     */
    protected function handleActivityStarted(SeckillActivity $activity, Output $output): void
    {
        $output->writeln("活动 [{$activity->id}] {$activity->title} 已开始，执行相关操作...");

        // 获取活动商品
        $goods = $activity->goods()->select();

        foreach ($goods as $item) {
            // 更新Redis中的活动状态
            $seckillKey = "seckill:goods:{$item['sku_id']}";
            Cache::store('redis')->hSet($seckillKey, 'status', SeckillActivity::STATUS_IN_PROGRESS);

            $output->writeln("  - 商品 [{$item['id']}] SKU:{$item['sku_id']} Redis状态已更新");
        }

        // 可以在这里添加其他活动开始时需要执行的操作
        // 例如：发送活动开始通知、更新首页推荐等
    }

    /**
     * 处理活动结束事件
     *
     * @param SeckillActivity $activity 活动对象
     * @param Output $output 输出对象
     */
    protected function handleActivityEnded(SeckillActivity $activity, Output $output): void
    {
        $output->writeln("活动 [{$activity->id}] {$activity->title} 已结束，执行相关操作...");

        // 获取活动商品
        $goods = $activity->goods()->select();

        foreach ($goods as $item) {
            // 更新Redis中的活动状态
            $seckillKey = "seckill:goods:{$item['sku_id']}";
            Cache::store('redis')->hSet($seckillKey, 'status', SeckillActivity::STATUS_ENDED);

            $output->writeln("  - 商品 [{$item['id']}] SKU:{$item['sku_id']} Redis状态已更新");
        }

        // 可以在这里添加其他活动结束时需要执行的操作
        // 例如：取消未支付的订单、恢复商品库存等
    }
}
