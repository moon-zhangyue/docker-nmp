<?php

declare(strict_types=1);

namespace app\command;

use app\service\queue\RabbitMQConsumer as RabbitMQConsumerService;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\facade\Config;
use think\facade\Log;
use think\queue\Job;

/**
 * RabbitMQ消费者命令
 *
 * 该命令用于启动RabbitMQ消费者，消费队列中的消息
 *
 * @package app\command
 */
class RabbitMQConsumer extends Command
{
    /**
     * 输出对象
     *
     * @var Output
     */
    protected $output;
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
        // 保存输出对象到类属性，以便在闭包中使用
        $this->output = $output;
        // 获取选项
        $queueName      = $input->getOption('queue');
        $connectionName = $input->getOption('connection');
        $timeout        = (int) $input->getOption('timeout');
        $memoryLimit    = (int) $input->getOption('memory');

        // 获取连接配置
        $config = Config::get("queue.connections.{$connectionName}");
        if (empty($config)) {
            $output->error("连接 '{$connectionName}' 不存在");
            return 1;
        }

        // 设置内存限制
        $memoryLimitMb = $memoryLimit . 'M';
        ini_set('memory_limit', $memoryLimitMb);

        $output->info("启动RabbitMQ消费者");
        $output->info("队列: {$queueName}");
        $output->info("连接: {$connectionName}");
        $output->info("内存限制: {$memoryLimitMb}");
        if ($timeout > 0) {
            $output->info("超时时间: {$timeout}秒");
        } else {
            $output->info("超时时间: 无限制");
        }

        // 创建消费者配置
        $consumerConfig = [
            'host'           => $config['host'] ?? 'localhost',
            'port'           => $config['port'] ?? 5672,
            'login'          => $config['login'] ?? 'myuser',
            'password'       => $config['password'] ?? 'mypass',
            'vhost'          => $config['vhost'] ?? '/',
            'exchange'       => $config['exchange'] ?? 'default',
            'exchange_type'  => $config['exchange_type'] ?? 'direct',
            'queue'          => $queueName,
            'consumer_tag'   => 'consumer_' . uniqid(),
            'prefetch_count' => $config['prefetch_count'] ?? 1,
        ];

        try {
            // 创建消费者
            $consumer = new RabbitMQConsumerService($consumerConfig);

            // 设置信号处理
            $this->setupSignalHandlers($consumer);

            // 开始消费

            // 消费消息
            $consumer->consume(function ($body, $message) {
                // 使用类属性$this->output
                $this->output->writeln('');
                $this->output->info('收到消息: ' . json_encode($body, JSON_UNESCAPED_UNICODE));

                try {
                    // 处理消息
                    $job  = $body['job'] ?? null;
                    $data = $body['data'] ?? [];

                    if (empty($job)) {
                        $this->output->error('消息中缺少job字段');
                        return false;
                    }

                    $this->output->info("处理任务: {$job}");

                    // 创建任务处理类实例
                    $instance = app()->make($job);

                    // 模拟Job对象，继承自think\queue\Job
                    $mockJob = new class($body, $message) extends Job {
                        protected $payload;
                        protected $message;
                        protected $app;

                        public function __construct($payload, $message)
                        {
                            $this->payload = $payload;
                            $this->message = $message;
                            $this->app = app();
                        }

                        public function getJobId()
                        {
                            return $this->payload['id'] ?? '';
                        }

                        public function attempts()
                        {
                            return $this->payload['attempts'] ?? 1;
                        }

                        public function getRawBody()
                        {
                            return json_encode($this->payload);
                        }

                        public function delete()
                        {
                            // 消息会在回调返回true时自动确认
                        }

                        public function release($delay = 0)
                        {
                            // 消息会在回调返回false时自动拒绝并重新入队
                            // $delay 参数在此处不使用，但保留以符合接口要求
                        }
                    };

                    // 调用任务处理方法
                    $instance->fire($mockJob, $data);

                    $this->output->info("任务处理完成");
                    return true;
                } catch (\Exception $e) {
                    $this->output->error("处理消息异常: " . $e->getMessage());
                    Log::error('处理消息异常', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    return false;
                }
            }, false);

            return 0;
        } catch (\Exception $e) {
            $output->error("消费者异常: " . $e->getMessage());
            Log::error('消费者异常', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    /**
     * 设置信号处理器
     *
     * @param RabbitMQConsumerService $consumer 消费者实例
     * @return void
     */
    protected function setupSignalHandlers(RabbitMQConsumerService $consumer)
    {
        if (extension_loaded('pcntl')) {
            pcntl_async_signals(true);

            // 处理终止信号
            pcntl_signal(SIGTERM, function () use ($consumer) {
                $this->output->info('收到SIGTERM信号，正在停止消费者...');
                $consumer->stop();
            });

            // 处理中断信号
            pcntl_signal(SIGINT, function () use ($consumer) {
                $this->output->info('收到SIGINT信号，正在停止消费者...');
                $consumer->stop();
            });

            // 处理退出信号
            pcntl_signal(SIGQUIT, function () use ($consumer) {
                $this->output->info('收到SIGQUIT信号，正在停止消费者...');
                $consumer->stop();
            });
        }
    }
}
