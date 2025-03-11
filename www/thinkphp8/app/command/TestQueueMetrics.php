<?php

declare(strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\facade\Queue;
use think\facade\Log;

class TestQueueMetrics extends Command
{
    protected function configure()
    {
        $this->setName('queue:test')
            ->addOption('queue', null, Option::VALUE_OPTIONAL, '队列名称', 'default')
            ->addOption('count', null, Option::VALUE_OPTIONAL, '测试任务数量', 5)
            ->setDescription('测试队列并生成监控数据');
    }

    protected function execute(Input $input, Output $output)
    {
        $queue = $input->getOption('queue');
        $count = (int)$input->getOption('count');

        $output->writeln("<info>开始推送 {$count} 个测试任务到队列 '{$queue}'...</info>");

        // 推送测试任务到队列
        for ($i = 0; $i < $count; $i++) {
            $data = [
                'id' => uniqid('test_'),
                'name' => "测试任务 #{$i}",
                'created_at' => date('Y-m-d H:i:s'),
            ];

            // 推送到队列
            $result = Queue::push('app\\job\\TestJob', $data, $queue);
            
            if ($result) {
                $output->writeln("<info>任务 #{$i} 已推送到队列</info>");
            } else {
                $output->writeln("<error>任务 #{$i} 推送失败</error>");
            }
        }

        $output->writeln("\n<comment>测试任务已推送完成，请确保队列处理器正在运行</comment>");
        $output->writeln("运行以下命令启动队列处理器：");
        $output->writeln("php think queue:work --queue={$queue}\n");
        $output->writeln("处理完成后，运行以下命令查看监控数据：");
        $output->writeln("php think queue:metrics\n");
    }
}