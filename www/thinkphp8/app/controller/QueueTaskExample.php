<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use think\facade\Queue;
use think\facade\Log;
use think\Request;

class QueueTaskExample extends BaseController
{
    /**
     * 创建常规队列任务（非租户感知）
     */
    public function createTask(Request $request)
    {
        try {
            // 获取请求中的任务数据
            $jobData = $request->param('job_data', []);
            $queueName = $request->param('queue', 'default');
            
            if (empty($jobData)) {
                return json([
                    'code' => 1,
                    'msg' => '任务数据不能为空'
                ]);
            }
            
            // 添加任务创建时间
            $jobData['created_at'] = date('Y-m-d H:i:s');
            
            // 推送任务到队列
            $isPushed = Queue::push('app\job\TestJob', $jobData, $queueName);
            
            if ($isPushed !== false) {
                return json([
                    'code' => 0,
                    'msg' => '任务已添加到队列',
                    'data' => [
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
            Log::error('创建队列任务异常: ' . $e->getMessage(), [
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
     * 创建延迟队列任务
     */
    public function createDelayedTask(Request $request)
    {
        try {
            // 获取请求中的任务数据
            $jobData = $request->param('job_data', []);
            $queueName = $request->param('queue', 'default');
            $delay = (int)$request->param('delay', 60); // 默认延迟60秒
            
            if (empty($jobData)) {
                return json([
                    'code' => 1,
                    'msg' => '任务数据不能为空'
                ]);
            }
            
            // 添加任务创建时间
            $jobData['created_at'] = date('Y-m-d H:i:s');
            
            // 推送延迟任务到队列
            $isPushed = Queue::later($delay, 'app\job\TestJob', $jobData, $queueName);
            
            if ($isPushed !== false) {
                return json([
                    'code' => 0,
                    'msg' => '延迟任务已添加到队列',
                    'data' => [
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
            Log::error('创建延迟队列任务异常: ' . $e->getMessage(), [
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
     * 创建定期执行的队列任务
     */
    public function createCronTask(Request $request)
    {
        try {
            // 获取请求中的任务数据
            $jobData = $request->param('job_data', []);
            $queueName = $request->param('queue', 'default');
            $cronExpression = $request->param('cron', '*/5 * * * *'); // 默认每5分钟执行一次
            
            if (empty($jobData)) {
                return json([
                    'code' => 1,
                    'msg' => '任务数据不能为空'
                ]);
            }
            
            // 添加任务创建时间和cron表达式
            $jobData['created_at'] = date('Y-m-d H:i:s');
            $jobData['cron_expression'] = $cronExpression;
            
            // 记录创建定期任务
            Log::info('创建定期任务', [
                'cron' => $cronExpression,
                'data' => $jobData
            ]);
            
            return json([
                'code' => 0,
                'msg' => '定期任务已创建（需要在计划任务中配置）',
                'data' => [
                    'cron_expression' => $cronExpression,
                    'job_data' => $jobData,
                    'setup_instructions' => '请在服务器crontab中配置：' . $cronExpression . ' php think queue:work --queue=' . $queueName
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('创建定期队列任务异常: ' . $e->getMessage(), [
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
     * 创建消息通知任务
     */
    public function createNotificationTask(Request $request)
    {
        try {
            // 获取请求中的通知数据
            $recipients = $request->param('recipients', []);
            $message = $request->param('message', '');
            $notificationType = $request->param('type', 'email');
            $queueName = $request->param('queue', 'notifications');
            
            if (empty($recipients)) {
                return json([
                    'code' => 1,
                    'msg' => '接收者不能为空'
                ]);
            }
            
            if (empty($message)) {
                return json([
                    'code' => 1,
                    'msg' => '消息内容不能为空'
                ]);
            }
            
            // 构建任务数据
            $jobData = [
                'recipients' => $recipients,
                'message' => $message,
                'type' => $notificationType,
                'created_at' => date('Y-m-d H:i:s'),
                'task_type' => 'send_notification'
            ];
            
            // 推送任务到队列
            $isPushed = Queue::push('app\job\TestJob', $jobData, $queueName);
            
            if ($isPushed !== false) {
                return json([
                    'code' => 0,
                    'msg' => '通知任务已添加到队列',
                    'data' => [
                        'queue_name' => $queueName,
                        'job_id' => $isPushed,
                        'notification_data' => $jobData
                    ]
                ]);
            } else {
                return json([
                    'code' => 1,
                    'msg' => '添加通知任务到队列失败'
                ]);
            }
        } catch (\Exception $e) {
            Log::error('创建通知队列任务异常: ' . $e->getMessage(), [
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