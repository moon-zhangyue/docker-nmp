<?php

declare(strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\facade\Cache;

class QueueMetrics extends Command
{
    protected function configure()
    {
        $this->setName('queue:metrics')
            ->addOption('reset', null, Option::VALUE_NONE, '重置所有监控指标')
            ->setDescription('显示队列监控指标');
    }

    protected function execute(Input $input, Output $output)
    {
        // 检查是否需要重置指标
        if ($input->getOption('reset')) {
            $this->resetMetrics($output);
            return;
        }

        // 获取指标数据
        $metrics = $this->getMetrics();

        // 如果没有数据，显示提示信息
        if (empty($metrics)) {
            $output->writeln('<comment>暂无队列监控数据</comment>');
            return;
        }

        // 显示指标数据
        $this->displayMetrics($metrics, $output);
    }

    protected function getMetrics(): array
    {
        // 从缓存中获取指标数据
        return Cache::get('queue_metrics', []);
    }

    protected function resetMetrics(Output $output)
    {
        // 清除缓存中的指标数据
        Cache::delete('queue_metrics');
        $output->writeln('<info>队列监控指标已重置</info>');
    }

    protected function displayMetrics(array $metrics, Output $output)
    {
        $output->writeln("\n<info>队列监控指标：</info>");

        foreach ($metrics as $queue => $data) {
            $output->writeln("\n<comment>队列：{$queue}</comment>");
            $output->writeln("成功处理：{$data['success']} 个任务");
            $output->writeln("失败处理：{$data['failed']} 个任务");

            $successRate = $data['success'] + $data['failed'] > 0
                ? round(($data['success'] / ($data['success'] + $data['failed'])) * 100, 2)
                : 0;

            $output->writeln("成功率：{$successRate}%");

            if (isset($data['last_processed_at'])) {
                $lastProcessed = date('Y-m-d H:i:s', $data['last_processed_at']);
                $output->writeln("最后处理时间：{$lastProcessed}");
            }
        }
    }
}
