<?php
declare(strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;

/**
 * 任务调度器
 */
class SeckillUpdateStatus extends Command
{
    /**
     * 配置指令
     */
    protected function configure()
    {
        $this->setName('task:run')
            ->setDescription('运行计划任务');
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
        $output->writeln('开始执行计划任务...');

        // 获取当前时间
        $now    = time();
        $minute = intval(date('i', $now));
        $hour   = intval(date('H', $now));
        $day    = intval(date('d', $now));
        $month  = intval(date('m', $now));
        $week   = intval(date('w', $now));

        // 每分钟执行的任务
        $this->runMinuteTasks($output);

        // 每5分钟执行的任务
        if ($minute % 5 === 0) {
            $this->runFiveMinuteTasks($output);
        }

        // 每小时执行的任务
        if ($minute === 0) {
            $this->runHourlyTasks($output);
        }

        // 每天执行的任务
        if ($hour === 0 && $minute === 0) {
            $this->runDailyTasks($output);
        }

        $output->writeln('计划任务执行完毕');
        return 0;
    }

    /**
     * 每分钟执行的任务
     *
     * @param Output $output 输出对象
     */
    protected function runMinuteTasks(Output $output): void
    {
        $output->writeln('执行每分钟任务...');

        // 这里可以添加每分钟需要执行的任务
        $this->runCommand('seckill:update-status', $output);
    }

    /**
     * 每5分钟执行的任务
     *
     * @param Output $output 输出对象
     */
    protected function runFiveMinuteTasks(Output $output): void
    {
        $output->writeln('执行每5分钟任务...');

        // 更新秒杀活动状态
        // $this->runCommand('seckill:update-status', [], $output);
    }

    /**
     * 每小时执行的任务
     *
     * @param Output $output 输出对象
     */
    protected function runHourlyTasks(Output $output): void
    {
        $output->writeln('执行每小时任务...');

        // 这里可以添加每小时需要执行的任务
    }

    /**
     * 每天执行的任务
     *
     * @param Output $output 输出对象
     */
    protected function runDailyTasks(Output $output): void
    {
        $output->writeln('执行每天任务...');

        // 强制更新所有秒杀活动状态
        $this->runCommand('seckill:update-status', $output, ['--force' => true]);
    }

    /**
     * 运行命令
     *
     * @param string $command 命令名称
     * @param Output $output 输出对象
     * @param array $parameters 参数
     * @return int 返回状态码
     */
    protected function runCommand(string $command, Output $output, array $parameters = []): int
    {
        try {
            // 使用控制台调用其他命令
            $this->getConsole()->call($command, $parameters);
            return 0;
        } catch (\Exception $e) {
            $output->error('执行命令 ' . $command . ' 失败: ' . $e->getMessage());
            return 1;
        }
    }
}

