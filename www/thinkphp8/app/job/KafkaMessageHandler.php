<?php
declare(strict_types=1);

namespace app\job;

use think\facade\Log;
use think\queue\Job;
use think\queue\tenant\TenantManager;

class KafkaMessageHandler
{
    /**
     * 处理Kafka消息
     *
     * @param Job $job 队列任务对象
     * @param array $data 任务数据
     */
    public function fire(Job $job, $data): void
    {
        try {
            // 记录任务开始处理
            Log::info('KafkaMessageHandler开始处理消息', ['job_id' => $job->getId(), 'data' => $data]);
            
            // 获取租户ID
            $tenantId = $data['tenant_id'] ?? 'default';
            
            // 获取租户管理器实例
            $manager = TenantManager::getInstance();
            
            // 检查租户是否存在
            if (!$manager->tenantExists($tenantId)) {
                Log::error('租户不存在，无法处理消息', [
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
            
            // 处理消息
            $message = $data['message'] ?? '';
            $metadata = $data['metadata'] ?? [];
            
            // 根据消息内容执行不同的业务逻辑
            $this->processMessage($message, $metadata, $tenantId, $tenantConfig);
            
            // 标记任务为已完成
            $job->delete();
            
            // 记录任务完成
            Log::info('KafkaMessageHandler处理完成', [
                'tenant_id' => $tenantId,
                'job_id' => $job->getId(),
                'execution_time' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            // 记录异常
            Log::error('KafkaMessageHandler处理异常', [
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
                
                Log::info('Kafka消息将延迟重试', [
                    'job_id' => $job->getId(),
                    'attempts' => $attempts,
                    'delay' => $delay
                ]);
            } else {
                // 尝试次数过多，删除任务
                $job->delete();
                
                Log::error('Kafka消息重试次数过多，已删除', [
                    'job_id' => $job->getId(),
                    'attempts' => $attempts
                ]);
            }
        }
    }
    
    /**
     * 处理消息内容
     * 
     * @param mixed $message 消息内容
     * @param array $metadata 元数据
     * @param string $tenantId 租户ID
     * @param array $tenantConfig 租户配置
     */
    protected function processMessage($message, array $metadata, string $tenantId, array $tenantConfig): void
    {
        // 记录消息处理
        Log::info('处理Kafka消息', [
            'tenant_id' => $tenantId,
            'message_type' => gettype($message),
            'metadata' => $metadata
        ]);
        
        // 根据消息类型处理
        if (is_string($message)) {
            // 字符串消息
            $this->processTextMessage($message, $metadata, $tenantId);
        } elseif (is_array($message)) {
            // 数组消息
            $this->processArrayMessage($message, $metadata, $tenantId);
        } elseif (is_object($message)) {
            // 对象消息
            $this->processObjectMessage($message, $metadata, $tenantId);
        } else {
            // 其他类型
            Log::warning('未知消息类型', [
                'tenant_id' => $tenantId,
                'message_type' => gettype($message)
            ]);
        }
        
        // 模拟处理时间
        sleep(1);
    }
    
    /**
     * 处理文本消息
     */
    protected function processTextMessage(string $message, array $metadata, string $tenantId): void
    {
        Log::info('处理文本消息', [
            'tenant_id' => $tenantId,
            'message_length' => strlen($message),
            'first_chars' => substr($message, 0, 50)
        ]);
    }
    
    /**
     * 处理数组消息
     */
    protected function processArrayMessage(array $message, array $metadata, string $tenantId): void
    {
        Log::info('处理数组消息', [
            'tenant_id' => $tenantId,
            'array_size' => count($message),
            'keys' => array_keys($message)
        ]);
    }
    
    /**
     * 处理对象消息
     */
    protected function processObjectMessage(object $message, array $metadata, string $tenantId): void
    {
        Log::info('处理对象消息', [
            'tenant_id' => $tenantId,
            'object_class' => get_class($message)
        ]);
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
        
        Log::error('KafkaMessageHandler任务最终失败', [
            'tenant_id' => $tenantId,
            'data' => $data,
            'failure_time' => date('Y-m-d H:i:s')
        ]);
    }
} 