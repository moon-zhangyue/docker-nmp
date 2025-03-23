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
            
            // 获取租户管理器实例
            $manager = TenantManager::getInstance();
            
            // 检查租户是否存在，不存在则创建
            if (!$manager->tenantExists($tenantId)) {
                // 创建新租户配置
                $config = [
                    'redis' => [
                        'host' => 'redis',
                        'port' => 6379,
                        'password' => '',
                        'select' => 0,
                    ],
                    'kafka' => [
                        'brokers' => ['376e79a3a3f9:9092'], // 使用实际Kafka容器ID
                        'group_id' => 'think-queue-' . $tenantId,
                        'topics' => [$topic],
                    ]
                ];
                
                // 创建租户
                $result = $manager->createTenant($tenantId, $config);
                
                if (!$result) {
                    return json([
                        'code' => 1,
                        'msg' => '创建租户失败'
                    ]);
                }
                
                Log::info('租户创建成功', ['tenant_id' => $tenantId]);
            }
            
            // 设置当前租户
            $manager->setCurrentTenant($tenantId);
            
            // 获取租户特定的队列名称
            $queueName = $manager->getTenantSpecificTopic($tenantId, $topic);
            
            // 构建要发送的消息数据
            $jobData = [
                'tenant_id' => $tenantId,
                'message' => $message,
                'created_at' => date('Y-m-d H:i:s'),
                'metadata' => $request->param('metadata', [])
            ];
            
            // 记录发送消息
            Log::info('开始发送Kafka消息', [
                'tenant_id' => $tenantId,
                'topic' => $topic,
                'queue_name' => $queueName,
                'data' => $jobData
            ]);
            
            // 推送消息到队列
            $isPushed = false;
            if ($delay > 0) {
                // 延迟消息
                $isPushed = Queue::later($delay, 'app\job\KafkaMessageHandler', $jobData, $queueName);
            } else {
                // 立即发送
                $isPushed = Queue::push('app\job\KafkaMessageHandler', $jobData, $queueName);
            }
            
            if ($isPushed !== false) {
                return json([
                    'code' => 0,
                    'msg' => '消息已成功发送到队列',
                    'data' => [
                        'tenant_id' => $tenantId,
                        'queue_name' => $queueName,
                        'job_id' => $isPushed,
                        'delay' => $delay,
                        'message_data' => $jobData
                    ]
                ]);
            } else {
                return json([
                    'code' => 1,
                    'msg' => '消息发送失败'
                ]);
            }
        } catch (\Exception $e) {
            Log::error('发送Kafka消息异常: ' . $e->getMessage(), [
                'error' => $e->getMessage(),
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
            Log::info('批量发送Kafka消息完成', [
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
                    'failed' => $failed,
                    'results' => $results
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('批量发送Kafka消息异常: ' . $e->getMessage(), [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return json([
                'code' => 1,
                'msg' => '发生错误: ' . $e->getMessage()
            ]);
        }
    }
}
