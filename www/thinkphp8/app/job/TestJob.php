<?php

declare(strict_types=1);

namespace app\job;

use think\facade\Log;
use think\queue\Job;

class TestJob
{
    /**
     * 任务处理
     * @param Job $job    任务对象
     * @param array $data 任务数据
     */
    public function fire(Job $job, $data): void
    {
        try {
            // 记录任务开始处理
            Log::info('TestJob开始处理', ['job_id' => $job->getId(), 'data' => $data]);
            
            // 根据任务类型执行不同的处理逻辑
            $taskType = $data['task_type'] ?? 'default';
            switch ($taskType) {
                case 'process_data':
                    $this->processData($data);
                    break;
                    
                case 'send_notification':
                    $this->sendNotification($data);
                    break;
                    
                case 'generate_report':
                    $this->generateReport($data);
                    break;
                    
                default:
                    // 默认处理逻辑
                    $this->defaultProcess($data);
                    break;
            }
            
            // 标记任务为已完成
            $job->delete();
            
            // 记录任务完成
            Log::info('TestJob处理完成', [
                'job_id' => $job->getId(),
                'execution_time' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            // 记录异常
            Log::error('TestJob处理异常', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'job_id' => $job->getId(),
                'data' => $data
            ]);
            
            // 如果有尝试次数，延迟重试
            $attempts = $job->attempts();
            if ($attempts < 3) {
                // 重试间隔成倍增加
                $delay = pow(2, $attempts);
                $job->release($delay);
                
                Log::info('任务将延迟重试', [
                    'job_id' => $job->getId(),
                    'attempts' => $attempts,
                    'delay' => $delay
                ]);
            } else {
                // 尝试次数过多，删除任务
                $job->delete();
                
                Log::error('任务重试次数过多，已删除', [
                    'job_id' => $job->getId(),
                    'attempts' => $attempts
                ]);
            }
        }
    }
    
    /**
     * 处理数据任务
     */
    protected function processData(array $data): void
    {
        // 模拟数据处理
        Log::info('处理数据', [
            'data_size' => count($data),
            'processing_time' => date('Y-m-d H:i:s')
        ]);
        
        // 睡眠一小段时间模拟处理
        sleep(1);
    }
    
    /**
     * 发送通知任务
     */
    protected function sendNotification(array $data): void
    {
        // 模拟发送通知
        $recipients = $data['recipients'] ?? [];
        $message = $data['message'] ?? '默认通知消息';
        $type = $data['type'] ?? 'email';
        
        Log::info('发送通知', [
            'type' => $type,
            'recipients_count' => count($recipients),
            'message_length' => strlen($message),
            'send_time' => date('Y-m-d H:i:s')
        ]);
        
        // 睡眠一小段时间模拟处理
        sleep(1);
    }
    
    /**
     * 生成报告任务
     */
    protected function generateReport(array $data): void
    {
        // 模拟生成报告
        $reportType = $data['report_type'] ?? 'default';
        $period = $data['period'] ?? 'monthly';
        
        Log::info('生成报告', [
            'report_type' => $reportType,
            'period' => $period,
            'generation_time' => date('Y-m-d H:i:s')
        ]);
        
        // 睡眠一小段时间模拟处理
        sleep(2);
    }
    
    /**
     * 默认处理任务
     */
    protected function defaultProcess(array $data): void
    {
        // 默认任务处理逻辑
        Log::info('执行默认任务处理', [
            'data' => $data,
            'execution_time' => date('Y-m-d H:i:s')
        ]);
        
        // 睡眠一小段时间模拟处理
        sleep(1);
    }
    
    /**
     * 任务失败处理
     * 
     * @param array $data 任务数据
     */
    public function failed($data): void
    {
        // 记录任务失败
        Log::error('TestJob任务最终失败', [
            'data' => $data,
            'failure_time' => date('Y-m-d H:i:s')
        ]);
    }
}
