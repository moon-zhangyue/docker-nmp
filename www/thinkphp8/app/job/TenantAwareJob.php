<?php
declare(strict_types=1);

namespace app\job;

use think\facade\Log;
use think\queue\Job;
use think\queue\tenant\TenantManager;

class TenantAwareJob
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
            Log::info('TenantAwareJob开始处理', ['job_id' => $job->getId(), 'data' => $data]);
            
            // 获取租户ID
            $tenantId = $data['tenant_id'] ?? 'default';
            
            // 获取租户管理器实例
            $manager = TenantManager::getInstance();
            
            // 检查租户是否存在
            if (!$manager->tenantExists($tenantId)) {
                Log::error('租户不存在，无法执行任务', [
                    'tenant_id' => $tenantId,
                    'job_id' => $job->getId()
                ]);
                
                // 删除任务
                $job->delete();
                return;
            }
            
            // 设置当前租户
            $manager->setCurrentTenant($tenantId);
            
            // 获取租户配置
            $tenantConfig = $manager->getTenantConfig($tenantId);
            
            // 执行租户特定的业务逻辑
            $this->processTenantTask($tenantId, $data, $tenantConfig);
            
            // 标记任务为已完成
            $job->delete();
            
            // 记录任务完成
            Log::info('TenantAwareJob处理完成', [
                'tenant_id' => $tenantId,
                'job_id' => $job->getId(),
                'execution_time' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            // 记录异常
            Log::error('TenantAwareJob处理异常', [
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
     * 处理租户特定的任务
     * 
     * @param string $tenantId     租户ID
     * @param array $data          任务数据
     * @param array $tenantConfig  租户配置
     */
    protected function processTenantTask(string $tenantId, array $data, array $tenantConfig): void
    {
        // 记录租户任务处理开始
        Log::info('开始处理租户任务', [
            'tenant_id' => $tenantId,
            'task_type' => $data['task_type'] ?? 'default',
            'tenant_config' => $tenantConfig
        ]);
        
        // 根据任务类型执行不同的处理逻辑
        $taskType = $data['task_type'] ?? 'default';
        switch ($taskType) {
            case 'process_data':
                $this->processData($tenantId, $data, $tenantConfig);
                break;
                
            case 'send_notification':
                $this->sendNotification($tenantId, $data, $tenantConfig);
                break;
                
            case 'generate_report':
                $this->generateReport($tenantId, $data, $tenantConfig);
                break;
                
            default:
                // 默认处理逻辑
                $this->defaultProcess($tenantId, $data, $tenantConfig);
                break;
        }
        
        // 记录租户任务处理完成
        Log::info('租户任务处理完成', [
            'tenant_id' => $tenantId,
            'task_type' => $taskType,
            'execution_time' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * 处理数据任务
     */
    protected function processData(string $tenantId, array $data, array $tenantConfig): void
    {
        // 模拟数据处理
        Log::info('处理租户数据', [
            'tenant_id' => $tenantId,
            'data_size' => count($data),
            'processing_time' => date('Y-m-d H:i:s')
        ]);
        
        // 睡眠一小段时间模拟处理
        sleep(1);
    }
    
    /**
     * 发送通知任务
     */
    protected function sendNotification(string $tenantId, array $data, array $tenantConfig): void
    {
        // 模拟发送通知
        $recipients = $data['recipients'] ?? [];
        $message = $data['message'] ?? '默认通知消息';
        
        Log::info('发送租户通知', [
            'tenant_id' => $tenantId,
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
    protected function generateReport(string $tenantId, array $data, array $tenantConfig): void
    {
        // 模拟生成报告
        $reportType = $data['report_type'] ?? 'default';
        $period = $data['period'] ?? 'monthly';
        
        Log::info('生成租户报告', [
            'tenant_id' => $tenantId,
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
    protected function defaultProcess(string $tenantId, array $data, array $tenantConfig): void
    {
        // 默认任务处理逻辑
        Log::info('执行默认租户任务处理', [
            'tenant_id' => $tenantId,
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
        $tenantId = $data['tenant_id'] ?? 'default';
        
        Log::error('TenantAwareJob任务最终失败', [
            'tenant_id' => $tenantId,
            'data' => $data,
            'failure_time' => date('Y-m-d H:i:s')
        ]);
    }
} 