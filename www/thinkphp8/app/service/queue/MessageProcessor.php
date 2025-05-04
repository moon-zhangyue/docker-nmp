<?php

declare(strict_types=1);

namespace app\service\queue;

use think\console\Output;
use think\facade\Log;
use app\service\queue\RabbitMQJob;

/**
 * 消息处理器
 * 
 * 负责处理RabbitMQ消息并执行相应的任务
 * 
 * @package app\service\queue
 */
class MessageProcessor
{
    /**
     * 控制台输出对象
     * 
     * @var Output
     */
    protected $output;
    
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
     * 处理消息
     * 
     * @param array $body 消息体
     * @param mixed $message 原始消息对象
     * @return bool 处理结果，true表示成功，false表示失败
     */
    public function process(array $body, $message): bool
    {
        $this->output->writeln('');
        $this->output->info('收到消息: ' . json_encode($body, JSON_UNESCAPED_UNICODE));
        
        try {
            // 验证消息格式
            if (!$this->validateMessage($body)) {
                return false;
            }
            
            // 提取任务信息
            $jobClass = $body['job'];
            $data = $body['data'] ?? [];
            
            $this->output->info("处理任务: {$jobClass}");
            
            // 创建任务处理类实例
            $jobInstance = $this->createJobInstance($jobClass);
            
            // 创建任务对象
            $job = new RabbitMQJob($body, $message);
            
            // 执行任务
            $jobInstance->fire($job, $data);
            
            $this->output->info("任务处理完成");
            return true;
        } catch (\Exception $e) {
            $this->handleException($e);
            return false;
        }
    }
    
    /**
     * 验证消息格式
     * 
     * @param array $body 消息体
     * @return bool 验证结果
     */
    protected function validateMessage(array $body): bool
    {
        if (empty($body['job'])) {
            $this->output->error('消息中缺少job字段');
            return false;
        }
        
        return true;
    }
    
    /**
     * 创建任务处理类实例
     * 
     * @param string $jobClass 任务类名
     * @return object 任务处理类实例
     */
    protected function createJobInstance(string $jobClass)
    {
        return app()->make($jobClass);
    }
    
    /**
     * 处理异常
     * 
     * @param \Exception $e 异常对象
     * @return void
     */
    protected function handleException(\Exception $e): void
    {
        $this->output->error("处理消息异常: " . $e->getMessage());
        Log::error('处理消息异常', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
}
