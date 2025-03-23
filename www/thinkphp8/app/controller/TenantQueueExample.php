<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use think\facade\Queue;
use think\facade\Log;
use think\Request;
use think\queue\tenant\TenantManager;

class TenantQueueExample extends BaseController
{
    /**
     * 初始化租户并创建队列任务
     */
    public function createTenantAndQueue(Request $request)
    {
        try {
            // 获取请求中的租户ID和任务数据
            $tenantId = $request->param('tenant_id', 'default');
            $jobData = $request->param('job_data', []);
            
            if (empty($jobData)) {
                return json([
                    'code' => 1,
                    'msg' => '任务数据不能为空'
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
                        'brokers' => ['kafka:9092'],
                        'group_id' => 'think-queue-' . $tenantId,
                        'topics' => ['default'],
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
            $queueName = $manager->getTenantSpecificTopic($tenantId, 'default');
            
            // 将任务数据添加到租户信息
            $jobData['tenant_id'] = $tenantId;
            $jobData['created_at'] = date('Y-m-d H:i:s');
            
            // 推送任务到队列
            $isPushed = Queue::push('app\job\TenantAwareJob', $jobData, $queueName);
            
            if ($isPushed !== false) {
                return json([
                    'code' => 0,
                    'msg' => '任务已添加到队列',
                    'data' => [
                        'tenant_id' => $tenantId,
                        'queue_name' => $queueName,
                        'job_id' => $isPushed,
                        'job_data' => $jobData
                    ]
                ]);
            } else {
                return json([
                    'code' => 1,
                    'msg' => '添加任务到队列失败'
                ]);
            }
        } catch (\Exception $e) {
            Log::error('创建租户队列任务异常: ' . $e->getMessage(), [
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
     * 使用已存在的租户创建延迟队列任务
     */
    public function createDelayedTask(Request $request)
    {
        try {
            // 获取请求中的租户ID和任务数据
            $tenantId = $request->param('tenant_id', 'default');
            $jobData = $request->param('job_data', []);
            $delay = (int)$request->param('delay', 60); // 默认延迟60秒
            
            if (empty($jobData)) {
                return json([
                    'code' => 1,
                    'msg' => '任务数据不能为空'
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
            $queueName = $manager->getTenantSpecificTopic($tenantId, 'default');
            
            // 将任务数据添加到租户信息
            $jobData['tenant_id'] = $tenantId;
            $jobData['created_at'] = date('Y-m-d H:i:s');
            
            // 推送延迟任务到队列
            $isPushed = Queue::later($delay, 'app\job\TenantAwareJob', $jobData, $queueName);
            
            if ($isPushed !== false) {
                return json([
                    'code' => 0,
                    'msg' => '延迟任务已添加到队列',
                    'data' => [
                        'tenant_id' => $tenantId,
                        'queue_name' => $queueName,
                        'job_id' => $isPushed,
                        'job_data' => $jobData,
                        'delay' => $delay
                    ]
                ]);
            } else {
                return json([
                    'code' => 1,
                    'msg' => '添加延迟任务到队列失败'
                ]);
            }
        } catch (\Exception $e) {
            Log::error('创建租户延迟队列任务异常: ' . $e->getMessage(), [
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
     * 获取租户配置
     */
    public function get_tenant_config(Request $request)
    {
        try {
            $tenantId = $request->param('tenant_id', 'default');
            
            // 获取租户管理器实例
            $manager = TenantManager::getInstance();
            
            // 检查租户是否存在
            if (!$manager->tenantExists($tenantId)) {
                return json([
                    'code' => 1,
                    'msg' => '租户不存在'
                ]);
            }
            
            // 获取租户配置
            $config = $manager->getTenantConfig($tenantId);
            
            return json([
                'code' => 0,
                'msg' => '获取租户配置成功',
                'data' => [
                    'tenant_id' => $tenantId,
                    'config' => $config
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('获取租户配置异常: ' . $e->getMessage(), [
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
     * 列出所有租户
     */
    public function listTenants()
    {
        try {
            // 获取租户管理器实例
            $manager = TenantManager::getInstance();
            
            // 获取所有租户
            $tenants = $manager->getAllTenants();
            
            return json([
                'code' => 0,
                'msg' => '获取租户列表成功',
                'data' => [
                    'tenants' => $tenants
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('获取租户列表异常: ' . $e->getMessage(), [
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
     * 创建租户并创建队列任务 (下划线命名法版本)
     */
    public function create_tenant_and_queue(Request $request)
    {
        return $this->createTenantAndQueue($request);
    }
    
    /**
     * 创建延迟队列任务 (下划线命名法版本)
     */
    public function create_delayed_task(Request $request)
    {
        return $this->createDelayedTask($request);
    }
    
    /**
     * 列出所有租户 (下划线命名法版本)
     */
    public function list_tenants()
    {
        try {
            // 获取租户管理器实例
            $manager = TenantManager::getInstance();
            
            // 获取所有租户
            $tenants = $manager->getAllTenants();
            
            return json([
                'code' => 0,
                'msg' => '获取租户列表成功',
                'data' => [
                    'tenants' => $tenants
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('获取租户列表异常: ' . $e->getMessage(), [
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