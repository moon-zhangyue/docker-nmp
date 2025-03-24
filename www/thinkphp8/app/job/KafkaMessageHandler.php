<?php
declare(strict_types=1);

namespace app\job;

use think\facade\Db;
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
            // 获取租户ID
            $tenantId = $data['tenant_id'] ?? 'default';
            
            // 记录任务开始处理 - 使用占位符格式
            Log::info('[KafkaMessage] 开始处理任务 {job_id}，租户: {tenant_id}，类型: {message_type}，创建时间: {created_at}', [
                'job_id' => $job->getJobId(),
                'tenant_id' => $tenantId,
                'message_type' => is_array($data['message'] ?? null) ? 'array' : gettype($data['message'] ?? null),
                'created_at' => $data['created_at'] ?? '-'
            ]);
            
            // 获取租户管理器实例
            $manager = TenantManager::getInstance();
            
            // 检查租户是否存在
            if (!$manager->tenantExists($tenantId)) {
                Log::error('[KafkaMessage] 租户 {tenant_id} 不存在，任务 {job_id} 将被删除', [
                    'tenant_id' => $tenantId,
                    'job_id' => $job->getJobId()
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
            Log::info('[KafkaMessage] 租户 {tenant_id} 的任务 {job_id} 处理完成，执行时间: {execution_time}', [
                'tenant_id' => $tenantId,
                'job_id' => $job->getJobId(),
                'execution_time' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            // 记录异常
            Log::error('[KafkaMessage] 处理异常: {error}，租户: {tenant_id}，任务: {job_id}，位置: {location}', [
                'error' => $e->getMessage(),
                'tenant_id' => $data['tenant_id'] ?? 'default',
                'job_id' => $job->getJobId(),
                'location' => $e->getFile() . ':' . $e->getLine()
            ]);
            
            // 如果有尝试次数，延迟重试
            $attempts = $job->attempts();
            if ($attempts < 3) {
                // 重试间隔成倍增加
                $delay = pow(2, $attempts);
                $job->release($delay);
                
                Log::info('[KafkaMessage] 任务 {job_id} 将延迟 {delay} 秒后第 {attempts} 次重试', [
                    'job_id' => $job->getJobId(),
                    'attempts' => $attempts,
                    'delay' => $delay
                ]);
            } else {
                // 尝试次数过多，删除任务
                $job->delete();
                
                Log::error('[KafkaMessage] 任务 {job_id} 重试 {attempts} 次仍失败，已删除', [
                    'job_id' => $job->getJobId(),
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
        // 记录消息处理 - 格式化简洁明了
        $metadataKeys = !empty($metadata) ? implode(', ', array_keys($metadata)) : '无';
        
        Log::info('[KafkaMessage] 处理租户 {tenant_id} 的 {type} 类型消息，元数据: {metadata}', [
            'tenant_id' => $tenantId,
            'type' => gettype($message),
            'metadata' => $metadataKeys
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
            Log::warning('[KafkaMessage] 租户 {tenant_id} 发送了未知类型 {type} 的消息', [
                'tenant_id' => $tenantId,
                'type' => gettype($message)
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
        $messagePreview = strlen($message) > 50 ? 
            substr($message, 0, 47) . '...' : 
            $message;
            
        Log::info('[KafkaMessage] 处理租户 {tenant_id} 的文本消息，长度: {length}，内容预览: {preview}', [
            'tenant_id' => $tenantId,
            'length' => strlen($message),
            'preview' => $messagePreview
        ]);
    }
    
    /**
     * 处理数组消息
     */
    protected function processArrayMessage(array $message, array $metadata, string $tenantId): void
    {
        $keys = array_slice(array_keys($message), 0, 5);
        $keyStr = !empty($keys) ? implode(', ', $keys) : '无';
        
        Log::info('[KafkaMessage] 处理租户 {tenant_id} 的数组消息，大小: {size}，键名: {keys}', [
            'tenant_id' => $tenantId,
            'size' => count($message),
            'keys' => $keyStr
        ]);
    }
    
    /**
     * 处理对象消息
     */
    protected function processObjectMessage(object $message, array $metadata, string $tenantId): void
    {
        Log::info('[KafkaMessage] 处理租户 {tenant_id} 的对象消息，类名: {class}', [
            'tenant_id' => $tenantId,
            'class' => get_class($message)
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
        
        Log::error('[KafkaMessage] 租户 {tenant_id} 的任务最终失败，消息类型: {message_type}，创建时间: {created_at}，失败时间: {time}', [
            'tenant_id' => $tenantId,
            'message_type' => is_array($data['message'] ?? null) ? 'array' : gettype($data['message'] ?? null),
            'created_at' => $data['created_at'] ?? '-',
            'time' => date('Y-m-d H:i:s')
        ]);
    }
} 