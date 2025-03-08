<?php

declare(strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use app\service\RedisQueue;
use think\facade\Log;

class QueueConsumer extends Command
{
    protected $queue;
    protected $redisQueue;

    protected function configure()
    {
        $this->setName('queue:consume')
            ->setDescription('Consume tasks from Redis queue')
            ->addArgument('queue', Argument::OPTIONAL, 'Queue name', RedisQueue::DEFAULT_QUEUE)
            ->addOption('timeout', null, Option::VALUE_OPTIONAL, 'Timeout in seconds', 0)
            ->addOption('memory-limit', null, Option::VALUE_OPTIONAL, 'Memory limit in MB', 128)
            ->addOption('max-jobs', null, Option::VALUE_OPTIONAL, 'Maximum number of jobs to process', 0);
    }

    protected function execute(Input $input, Output $output)
    {
        try {
            $this->queue = $input->getArgument('queue');
            $timeout = (int)$input->getOption('timeout');
            $memoryLimit = (int)$input->getOption('memory-limit') * 1024 * 1024; // 转换为字节
            $maxJobs = (int)$input->getOption('max-jobs');

            // 初始化Redis队列服务
            $this->redisQueue = new RedisQueue();

            $jobCount = 0;
            $errorCount = 0;
            $maxErrors = 10; // 最大连续错误次数

            $output->writeln(sprintf(
                "Starting queue consumer\nQueue: %s\nTimeout: %ds\nMemory limit: %dMB\nMax jobs: %d",
                $this->queue,
                $timeout,
                $memoryLimit / 1024 / 1024,
                $maxJobs
            ));

            while (true) {
                // 检查内存使用
                if (memory_get_usage(true) > $memoryLimit) {
                    $output->writeln('<error>Memory limit exceeded. Exiting...</error>');
                    break;
                }

                // 检查最大任务数
                if ($maxJobs > 0 && $jobCount >= $maxJobs) {
                    $output->writeln('<info>Maximum number of jobs processed. Exiting...</info>');
                    break;
                }

                try {
                    // 获取任务
                    $task = $this->redisQueue->pop($this->queue, $timeout);

                    if (!$task) {
                        // 重置错误计数，因为没有任务是正常的
                        $errorCount = 0;
                        continue;
                    }

                    $output->writeln(sprintf(
                        "\n<info>Processing task %s</info>",
                        $task['id']
                    ));

                    // 处理任务
                    $this->processTask($task, $output);

                    $jobCount++;
                    $errorCount = 0; // 重置错误计数

                    $output->writeln(sprintf(
                        "<info>Task %s completed. Processed jobs: %d</info>",
                        $task['id'],
                        $jobCount
                    ));
                } catch (\Exception $e) {
                    $errorCount++;

                    Log::error('Error processing task: ' . $e->getMessage(), [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'error_count' => $errorCount
                    ]);

                    $output->writeln(sprintf(
                        '<error>Error: %s (Consecutive errors: %d/%d)</error>',
                        $e->getMessage(),
                        $errorCount,
                        $maxErrors
                    ));

                    // 如果连续错误次数过多，退出循环
                    if ($errorCount >= $maxErrors) {
                        $output->writeln('<error>Too many consecutive errors. Exiting...</error>');
                        return 1;
                    }

                    // 错误后等待时间随错误次数增加
                    $waitTime = min($errorCount * 2, 30); // 最多等待30秒
                    $output->writeln(sprintf('<comment>Waiting %d seconds before nex attempt...</comment>', $waitTime));
                    sleep($waitTime);
                }
            }

            return 0;
        } catch (\Exception $e) {
            Log::error('Fatal consumer error: ' . $e->getMessage(), [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $output->writeln('<error>Fatal error: ' . $e->getMessage() . '</error>');
            return 1;
        }
    }

    /**
     * 处理任务
     */
    protected function processTask(array $task, Output $output)
    {
        try {
            $output->writeln("Task data: " . json_encode($task['data'], JSON_PRETTY_PRINT));

            // 这里实现具体的任务处理逻辑
            // 例如：发送邮件、处理数据等

            // 模拟处理时间
            sleep(1);

            Log::info(
                'Task processed successfully,task_id: ' .
                    'task_id' . $task['id'] . '-data:' . $task['data']
            );
        } catch (\Exception $e) {
            Log::error('Task processing failed: ' . $e->getMessage() . '-task_id:' . $task['id']);
            throw $e;
        }
    }
}
