<?php
declare (strict_types = 1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use app\service\RedisQueue;

class QueueMonitor extends Command
{
    protected function configure()
    {
        $this->setName('queue:monitor')
             ->setDescription('Monitor Redis queue status')
             ->addArgument('action', Argument::REQUIRED, 'Action to perform (status/peek/clear)')
             ->addArgument('queue', Argument::OPTIONAL, 'Queue name', RedisQueue::DEFAULT_QUEUE)
             ->addOption('limit', null, Option::VALUE_OPTIONAL, 'Number of tasks to peek', 10);
    }

    protected function execute(Input $input, Output $output)
    {
        $action = $input->getArgument('action');
        $queue = $input->getArgument('queue');
        $limit = (int)$input->getOption('limit');

        $redisQueue = new RedisQueue();

        switch ($action) {
            case 'status':
                $this->showStatus($redisQueue, $queue, $output);
                break;
            
            case 'peek':
                $this->peekTasks($redisQueue, $queue, $limit, $output);
                break;
            
            case 'clear':
                $this->clearQueue($redisQueue, $queue, $output);
                break;
            
            default:
                $output->writeln("<error>Invalid action. Use 'status', 'peek' or 'clear'</error>");
                return 1;
        }

        return 0;
    }

    /**
     * 显示队列状态
     */
    protected function showStatus(RedisQueue $queue, string $queueName, Output $output)
    {
        $length = $queue->length($queueName);
        
        $output->writeln("\n<info>Queue Status:</info>");
        $output->writeln(sprintf("Queue: %s", $queueName));
        $output->writeln(sprintf("Length: %d", $length));
    }

    /**
     * 查看队列中的任务
     */
    protected function peekTasks(RedisQueue $queue, string $queueName, int $limit, Output $output)
    {
        $tasks = $queue->peek($queueName, 0, $limit - 1);
        
        $output->writeln(sprintf("\n<info>Peeking %d tasks from queue '%s':</info>", $limit, $queueName));
        
        if (empty($tasks)) {
            $output->writeln("<comment>No tasks in queue.</comment>");
            return;
        }

        foreach ($tasks as $index => $task) {
            $output->writeln(sprintf(
                "\n<info>Task #%d:</info>",
                $index + 1
            ));
            $output->writeln("ID: " . $task['id']);
            $output->writeln("Created: " . $task['created_at']);
            $output->writeln("Data: " . json_encode($task['data'], JSON_PRETTY_PRINT));
            $output->writeln(str_repeat('-', 50));
        }
    }

    /**
     * 清空队列
     */
    protected function clearQueue(RedisQueue $queue, string $queueName, Output $output)
    {
        $length = $queue->length($queueName);
        
        if ($length === 0) {
            $output->writeln("<comment>Queue is already empty.</comment>");
            return;
        }

        if ($queue->clear($queueName)) {
            $output->writeln(sprintf(
                "<info>Successfully cleared %d tasks from queue '%s'</info>",
                $length,
                $queueName
            ));
        } else {
            $output->writeln("<error>Failed to clear queue.</error>");
        }
    }
} 