<?php

declare(strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use app\service\RedisQueue;

class QueueFailed extends Command
{
    protected function configure()
    {
        $this->setName('queue:failed')
            ->setDescription('Manage failed queue jobs')
            ->addArgument('action', Argument::REQUIRED, 'Action to perform (list/retry/clear)')
            ->addArgument('queue', Argument::OPTIONAL, 'Queue name', RedisQueue::DEFAULT_QUEUE)
            ->addOption('limit', null, Option::VALUE_OPTIONAL, 'Number of failed jobs to show', 10);
    }

    protected function execute(Input $input, Output $output)
    {
        $action = $input->getArgument('action');
        $queue = $input->getArgument('queue');
        $limit = (int)$input->getOption('limit');

        $redisQueue = new RedisQueue();

        switch ($action) {
            case 'list':
                $this->listFailedJobs($redisQueue, $queue, $limit, $output);
                break;

            case 'retry':
                $this->retryFailedJobs($redisQueue, $queue, $output);
                break;

            case 'clear':
                $this->clearFailedJobs($redisQueue, $queue, $output);
                break;

            default:
                $output->writeln("<error>Invalid action. Use 'list', 'retry' or 'clear'</error>");
                return 1;
        }

        return 0;
    }

    protected function listFailedJobs(RedisQueue $queue, string $queueName, int $limit, Output $output)
    {
        $failedJobs = $queue->getFailedTasks($queueName, 0, $limit - 1);

        if (empty($failedJobs)) {
            $output->writeln("<info>No failed jobs found.</info>");
            return;
        }

        $output->writeln("\n<info>Failed Jobs:</info>");
        foreach ($failedJobs as $job) {
            $output->writeln(sprintf(
                "\nID: %s\nFailed at: %s\nAttempts: %d\nError: %s\nData: %s\n%s",
                $job['id'],
                $job['failed_at'],
                $job['attempts'],
                $job['error'],
                json_encode($job['data'], JSON_PRETTY_PRINT),
                str_repeat('-', 50)
            ));
        }

        $output->writeln(sprintf("\nShowing %d of %d failed jobs", count($failedJobs), $queue->getFailedTasksCount($queueName)));
    }

    protected function retryFailedJobs(RedisQueue $queue, string $queueName, Output $output)
    {
        $count = $queue->retryAllFailed($queueName);

        if ($count > 0) {
            $output->writeln(sprintf("<info>Successfully queued %d failed jobs for retry.</info>", $count));
        } else {
            $output->writeln("<comment>No failed jobs to retry.</comment>");
        }
    }

    protected function clearFailedJobs(RedisQueue $queue, string $queueName, Output $output)
    {
        if ($queue->clearFailedTasks($queueName)) {
            $output->writeln("<info>Successfully cleared all failed jobs.</info>");
        } else {
            $output->writeln("<error>Failed to clear failed jobs.</error>");
        }
    }
}
