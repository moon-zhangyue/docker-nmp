<?php

declare(strict_types=1);

namespace app\command;

use app\service\queue\ConsumerManager;
use app\service\queue\MessageProcessor;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

/**
 * RabbitMQ消费者命令
 *
 * 该命令用于启动RabbitMQ消费者，消费队列中的消息
 *
 * @package app\command
 */
class RabbitMQConsumer extends Command
{
    // 不需要类属性
    /**
     * 配置命令
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('rabbitmq:consume')
            ->addOption('queue', 'Q', Option::VALUE_OPTIONAL, '队列名称', 'default')
            ->addOption('connection', 'c', Option::VALUE_OPTIONAL, '连接名称', 'rabbitmq')
            ->addOption('timeout', 't', Option::VALUE_OPTIONAL, '超时时间（秒）', 0)
            ->addOption('memory', 'm', Option::VALUE_OPTIONAL, '内存限制（MB）', 128)
            ->setDescription('启动RabbitMQ消费者');
    }

    /**
     * 执行命令
     *
     * @param Input $input 输入对象
     * @param Output $output 输出对象
     * @return int
     */
    protected function execute(Input $input, Output $output)
    {
        // 获取选项
        $queueName      = $input->getOption('queue');
        $connectionName = $input->getOption('connection');
        $timeout        = (int) $input->getOption('timeout');
        $memoryLimit    = (int) $input->getOption('memory');

        // 创建消费者管理器
        $manager = new ConsumerManager($output);

        // 初始化消费者
        if (!$manager->initialize($queueName, $connectionName, $timeout, $memoryLimit)) {
            return 1;
        }

        // 创建消息处理器
        $processor = new MessageProcessor($output);

        // 启动消费者
        if ($manager->start($processor)) {
            return 0;
        }

        return 1;
    }
}
