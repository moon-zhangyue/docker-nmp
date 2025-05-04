<?php

declare(strict_types=1);

namespace app\service\queue;

use think\console\Output;
use think\facade\Config;
use think\facade\Log;

/**
 * 消费者管理器
 * 
 * 负责创建和管理RabbitMQ消费者
 * 
 * @package app\service\queue
 */
class ConsumerManager
{
    /**
     * 控制台输出对象
     * 
     * @var Output
     */
    protected $output;
    
    /**
     * 消费者配置
     * 
     * @var array
     */
    protected $config;
    
    /**
     * 消费者实例
     * 
     * @var RabbitMQConsumer
     */
    protected $consumer;
    
    /**
     * 构造函数
     * 
     * @param Output $output 控制台输出对象
     */
    public function __construct(Output $output)
    {
        $this->output = $output;
    }
    
    /**
     * 初始化消费者
     * 
     * @param string $queueName 队列名称
     * @param string $connectionName 连接名称
     * @param int $timeout 超时时间（秒）
     * @param int $memoryLimit 内存限制（MB）
     * @return bool 初始化结果
     */
    public function initialize(string $queueName, string $connectionName, int $timeout, int $memoryLimit): bool
    {
        // 获取连接配置
        $connectionConfig = Config::get("queue.connections.{$connectionName}");
        if (empty($connectionConfig)) {
            $this->output->error("连接 '{$connectionName}' 不存在");
            return false;
        }
        
        // 设置内存限制
        $this->setMemoryLimit($memoryLimit);
        
        // 显示配置信息
        $this->displayConfig($queueName, $connectionName, $timeout, $memoryLimit);
        
        // 创建消费者配置
        $this->config = $this->createConsumerConfig($connectionConfig, $queueName);
        
        return true;
    }
    
    /**
     * 启动消费者
     * 
     * @param MessageProcessor $processor 消息处理器
     * @return bool 启动结果
     */
    public function start(MessageProcessor $processor): bool
    {
        try {
            // 创建消费者
            $this->consumer = new RabbitMQConsumer($this->config);
            
            // 设置信号处理
            $this->setupSignalHandlers();
            
            // 开始消费
            $this->consumer->consume(function ($body, $message) use ($processor) {
                return $processor->process($body, $message);
            }, false);
            
            return true;
        } catch (\Exception $e) {
            $this->output->error("消费者异常: " . $e->getMessage());
            Log::error('消费者异常', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
    
    /**
     * 停止消费者
     * 
     * @return void
     */
    public function stop(): void
    {
        if ($this->consumer) {
            $this->consumer->stop();
        }
    }
    
    /**
     * 设置内存限制
     * 
     * @param int $memoryLimit 内存限制（MB）
     * @return void
     */
    protected function setMemoryLimit(int $memoryLimit): void
    {
        $memoryLimitMb = $memoryLimit . 'M';
        ini_set('memory_limit', $memoryLimitMb);
    }
    
    /**
     * 显示配置信息
     * 
     * @param string $queueName 队列名称
     * @param string $connectionName 连接名称
     * @param int $timeout 超时时间（秒）
     * @param int $memoryLimit 内存限制（MB）
     * @return void
     */
    protected function displayConfig(string $queueName, string $connectionName, int $timeout, int $memoryLimit): void
    {
        $this->output->info("启动RabbitMQ消费者");
        $this->output->info("队列: {$queueName}");
        $this->output->info("连接: {$connectionName}");
        $this->output->info("内存限制: {$memoryLimit}M");
        
        if ($timeout > 0) {
            $this->output->info("超时时间: {$timeout}秒");
        } else {
            $this->output->info("超时时间: 无限制");
        }
    }
    
    /**
     * 创建消费者配置
     * 
     * @param array $connectionConfig 连接配置
     * @param string $queueName 队列名称
     * @return array 消费者配置
     */
    protected function createConsumerConfig(array $connectionConfig, string $queueName): array
    {
        return [
            'host'           => $connectionConfig['host'] ?? 'localhost',
            'port'           => $connectionConfig['port'] ?? 5672,
            'login'          => $connectionConfig['login'] ?? 'guest',
            'password'       => $connectionConfig['password'] ?? 'guest',
            'vhost'          => $connectionConfig['vhost'] ?? '/',
            'exchange'       => $connectionConfig['exchange'] ?? 'default',
            'exchange_type'  => $connectionConfig['exchange_type'] ?? 'direct',
            'queue'          => $queueName,
            'consumer_tag'   => 'consumer_' . uniqid(),
            'prefetch_count' => $connectionConfig['prefetch_count'] ?? 1,
        ];
    }
    
    /**
     * 设置信号处理器
     * 
     * @return void
     */
    protected function setupSignalHandlers(): void
    {
        if (!extension_loaded('pcntl')) {
            return;
        }
        
        pcntl_async_signals(true);
        
        // 处理终止信号
        pcntl_signal(SIGTERM, function () {
            $this->output->info('收到SIGTERM信号，正在停止消费者...');
            $this->stop();
        });
        
        // 处理中断信号
        pcntl_signal(SIGINT, function () {
            $this->output->info('收到SIGINT信号，正在停止消费者...');
            $this->stop();
        });
        
        // 处理退出信号
        pcntl_signal(SIGQUIT, function () {
            $this->output->info('收到SIGQUIT信号，正在停止消费者...');
            $this->stop();
        });
    }
}
