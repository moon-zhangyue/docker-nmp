<?php

declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use think\facade\Queue;
use think\facade\Log;
use think\Request;
use think\queue\tenant\TenantManager;

class KafkaQueue extends BaseController
{
    /**
     * 使用特定租户发送Kafka消息
     * 
     * @param Request $request
     * @return \think\Response
     */
    public function push(Request $request)
    {
        try {
            // 获取请求参数
            $tenantId = $request->param('tenant_id', 'default');
            $message = $request->param('message', '');
            $topic = $request->param('topic', 'default');
            $delay = (int)$request->param('delay', 0);
            
            if (empty($message)) {
                return json([
                    'code' => 1,
                    'msg' => '消息内容不能为空'
                ]);
            }
            
            // 记录请求参数
            Log::info('[Kafka] 接收到推送请求，参数: {params}', [
                'params' => json_encode([
                    'tenant_id' => $tenantId,
                    'message' => (is_string($message) && strlen($message) > 100) ? substr($message, 0, 100) . '...' : $message,
                    'topic' => $topic,
                    'delay' => $delay
                ], JSON_UNESCAPED_UNICODE)
            ]);
            
            // 获取租户管理器实例
            $manager = TenantManager::getInstance();
            
            // 检查租户是否存在，不存在则创建
            if (!$manager->tenantExists($tenantId)) {
                Log::info('[Kafka] 租户 {tenant_id} 不存在，准备创建', ['tenant_id' => $tenantId]);
                
                // 创建新租户配置
                $config = [
                    'redis' => [
                        'host' => 'redis',
                        'port' => 6379,
                        'password' => '',
                        'select' => 0,
                    ],
                    'kafka' => [
                        'brokers' => ['kafka:9092'], // 使用Kafka容器名称而不是ID
                        'group_id' => 'think-queue-' . $tenantId,
                        'topics' => [$topic],
                        'auto.create.topics.enable' => 'true', // 允许自动创建主题
                    ]
                ];
                
                // 创建租户
                $result = $manager->createTenant($tenantId, $config);
                
                if (!$result) {
                    Log::error('[Kafka] 创建租户 {tenant_id} 失败', ['tenant_id' => $tenantId]);
                    return json([
                        'code' => 1,
                        'msg' => '创建租户失败'
                    ]);
                }
                
                Log::info('[Kafka] 租户 {tenant_id} 创建成功', ['tenant_id' => $tenantId]);
            }
            
            // 设置当前租户
            $manager->setCurrentTenant($tenantId);
            
            // 获取租户特定的队列名称
            $queueName = $manager->getTenantSpecificTopic($tenantId, $topic);
            Log::info('[Kafka] 获取到租户特定队列名称: {queue}', ['queue' => $queueName]);
            
            // 构建要发送的消息数据
            $jobData = [
                'tenant_id' => $tenantId,
                'message' => $message,
                'created_at' => date('Y-m-d H:i:s'),
                'metadata' => $request->param('metadata', [])
            ];
            
            // 记录发送消息
            $delayInfo = $delay > 0 ? "{$delay}秒" : '立即';
            $messageType = is_array($message) ? 'array' : gettype($message);
            
            Log::info('[Kafka] 发送 {type} 类型消息到租户 {tenant_id} 的 {topic} 主题，队列: {queue}，延迟: {delay}', [
                'tenant_id' => $tenantId,
                'topic' => $topic,
                'queue' => $queueName,
                'delay' => $delayInfo,
                'type' => $messageType
            ]);
            
            // 检查队列驱动
            $queueDriver = config('queue.default');
            Log::info('[Kafka] 当前队列驱动: {driver}', ['driver' => $queueDriver]);
            
            // 推送消息到队列
            $isPushed = false;
            if ($delay > 0) {
                // 延迟消息
                Log::info('[Kafka] 尝试推送延迟消息，延迟时间: {delay}秒', ['delay' => $delay]);
                $isPushed = Queue::later($delay, 'app\job\KafkaMessageHandler', $jobData, $queueName);
            } else {
                // 立即发送
                Log::info('[Kafka] 尝试立即推送消息');
                $isPushed = Queue::push('app\job\KafkaMessageHandler', $jobData, $queueName);
            }
            
            if ($isPushed !== false) {
                Log::info('[Kafka] 租户 {tenant_id} 的消息发送成功，任务ID: {job_id}', [
                    'tenant_id' => $tenantId, 
                    'job_id' => $isPushed
                ]);
                
                return json([
                    'code' => 0,
                    'msg' => '消息已成功发送到队列',
                    'data' => [
                        'tenant_id' => $tenantId,
                        'queue_name' => $queueName,
                        'job_id' => $isPushed,
                        'delay' => $delay
                    ]
                ]);
            } else {
                Log::error('[Kafka] 租户 {tenant_id} 的消息发送失败，队列: {queue}', [
                    'tenant_id' => $tenantId, 
                    'queue' => $queueName
                ]);
                
                return json([
                    'code' => 1,
                    'msg' => '消息发送失败'
                ]);
            }
        } catch (\Exception $e) {
            Log::error('[Kafka] 发送异常: {error}，租户: {tenant_id}，位置: {location}，错误码: {code}，堆栈: {trace}', [
                'error' => $e->getMessage(),
                'tenant_id' => $request->param('tenant_id', 'default'),
                'location' => $e->getFile() . ':' . $e->getLine(),
                'code' => $e->getCode(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return json([
                'code' => 1,
                'msg' => '发生错误: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * 批量发送Kafka消息
     * 
     * @param Request $request
     * @return \think\Response
     */
    public function batch_push(Request $request)
    {
        try {
            // 获取请求参数
            $tenantId = $request->param('tenant_id', 'default');
            $messages = $request->param('messages', []);
            $topic = $request->param('topic', 'default');
            
            if (empty($messages) || !is_array($messages)) {
                return json([
                    'code' => 1,
                    'msg' => '消息列表不能为空，且必须是数组'
                ]);
            }
            
            // 获取租户管理器实例
            $manager = TenantManager::getInstance();
            
            // 检查租户是否存在
            if (!$manager->tenantExists($tenantId)) {
                return json([
                    'code' => 1,
                    'msg' => '租户不存在，请先创建租户'
                ]);
            }
            
            // 设置当前租户
            $manager->setCurrentTenant($tenantId);
            
            // 获取租户特定的队列名称
            $queueName = $manager->getTenantSpecificTopic($tenantId, $topic);
            
            // 处理结果
            $results = [];
            $success = 0;
            $failed = 0;
            
            // 记录批量发送开始
            Log::info('[Kafka] 开始批量发送消息到租户 {tenant_id} 的 {topic} 主题，总数: {count}', [
                'tenant_id' => $tenantId,
                'topic' => $topic,
                'count' => count($messages)
            ]);
            
            // 批量推送消息
            foreach ($messages as $key => $message) {
                // 构建单条消息数据
                $jobData = [
                    'tenant_id' => $tenantId,
                    'message' => $message,
                    'created_at' => date('Y-m-d H:i:s'),
                    'metadata' => [
                        'batch_id' => uniqid('batch_'),
                        'index' => $key
                    ]
                ];
                
                // 推送消息到队列
                $isPushed = Queue::push('app\job\KafkaMessageHandler', $jobData, $queueName);
                
                if ($isPushed !== false) {
                    $success++;
                    $results[] = [
                        'index' => $key,
                        'status' => 'success',
                        'job_id' => $isPushed
                    ];
                } else {
                    $failed++;
                    $results[] = [
                        'index' => $key,
                        'status' => 'failed'
                    ];
                }
            }
            
            // 记录发送结果
            Log::info('[Kafka] 租户 {tenant_id} 的批量发送完成，总数: {total}，成功: {success}，失败: {failed}', [
                'tenant_id' => $tenantId,
                'topic' => $topic,
                'total' => count($messages),
                'success' => $success,
                'failed' => $failed
            ]);
            
            return json([
                'code' => 0,
                'msg' => "批量发送完成，成功：{$success}，失败：{$failed}",
                'data' => [
                    'tenant_id' => $tenantId,
                    'queue_name' => $queueName,
                    'total' => count($messages),
                    'success' => $success,
                    'failed' => $failed
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('[Kafka] 批量发送异常: {error}，租户: {tenant_id}，位置: {location}，错误码: {code}', [
                'error' => $e->getMessage(),
                'tenant_id' => $request->param('tenant_id', 'default'),
                'location' => $e->getFile() . ':' . $e->getLine(),
                'code' => $e->getCode()
            ]);
            
            return json([
                'code' => 1,
                'msg' => '发生错误: ' . $e->getMessage()
            ]);
        }
    }
}
