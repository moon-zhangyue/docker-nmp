<?php
declare(strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Queue;
use think\facade\Log;

class KafkaQueueConsumer extends Command
{
    protected function configure()
    {
        $this->setName('queue:kafka:work')
            ->setDescription('Process Kafka queue jobs')
            ->addArgument('queue', Argument::OPTIONAL, 'The queue to listen on')
            ->addOption('memory', null, Option::VALUE_OPTIONAL, 'The memory limit in megabytes', 128)
            ->addOption('timeout', null, Option::VALUE_OPTIONAL, 'The number of seconds a child process can run', 60)
            ->addOption('tries', null, Option::VALUE_OPTIONAL, 'Number of times to attempt a job before logging it failed', 0);
    }

    protected function execute(Input $input, Output $output)
    {
        $queue = $input->getArgument('queue') ?: config('queue.connections.kafka.queue');
        $memory = $input->getOption('memory');
        $timeout = $input->getOption('timeout');
        $tries = $input->getOption('tries');

        $output->writeln(sprintf(
            "Starting Kafka queue worker\nQueue: %s\nMemory: %dMB\nTimeout: %ds\nMax tries: %d",
            $queue,
            $memory,
            $timeout,
            $tries
        ));

        $connection = Queue::connection('kafka');
        $lastRestart = time();

        while (true) {
            try {
                // 检查内存使用
                if (memory_get_usage(true) / 1024 / 1024 > $memory) {
                    $output->writeln('<error>Memory limit exceeded. Exiting...</error>');
                    break;
                }

                // 获取下一个任务
                $job = $connection->pop($queue);

                if ($job) {
                    $output->writeln(sprintf(
                        "\n<info>Processing job %s</info>",
                        $job->getJobId()
                    ));

                    // 处理任务
                    $job->fire();

                    $output->writeln(sprintf(
                        "<info>Job %s completed successfully</info>",
                        $job->getJobId()
                    ));
                } else {
                    // 没有任务时等待
                    sleep(1);
                }
            } catch (\Exception $e) {
                Log::error('Kafka queue error: {error} {trace} {job_id}', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'job_id' => $job->getJobId() ?? 'unknown'
                ]);

                $output->writeln(sprintf(
                    '<error>Error: %s</error>',
                    $e->getMessage()
                ));

                // 出错后短暂等待，使用更短的时间避免长时间阻塞
                usleep(500000); // 500毫秒
            }
        }

        return 0;
    }
}