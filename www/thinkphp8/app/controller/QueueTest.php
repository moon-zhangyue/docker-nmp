<?php
declare (strict_types = 1);

namespace app\controller;

use app\BaseController;
use think\facade\Queue;
use think\facade\Log;
use think\Response;

class QueueTest extends BaseController
{
    /**
     * 推送任务到队列
     */
    public function push()
    {
        $data = [
            'id' => uniqid(),
            'name' => 'test job',
            'data' => ['key' => 'value'],
            'created_at' => date('Y-m-d H:i:s')
        ];

        try {
            // 推送任务到队列
            $isPushed = Queue::push('app\job\TestJob', $data, 'default');
            
            if ($isPushed !== false) {
                return json([
                    'code' => 0,
                    'msg' => 'Job pushed successfully',
                    'data' => [
                        'job_id' => $isPushed,
                        'job_data' => $data
                    ]
                ]);
            } else {
                return json([
                    'code' => 1,
                    'msg' => 'Failed to push job to queue'
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to push job: ' . $e->getMessage(), [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return json([
                'code' => 1,
                'msg' => 'Error: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * 推送延迟任务
     */
    public function pushDelay()
    {
        $data = [
            'id' => uniqid(),
            'name' => 'delayed job',
            'data' => ['key' => 'value'],
            'created_at' => date('Y-m-d H:i:s')
        ];

        try {
            // 推送延迟任务，60秒后执行
            $isPushed = Queue::later(60, 'app\job\TestJob', $data, 'default');
            
            if ($isPushed !== false) {
                return json([
                    'code' => 0,
                    'msg' => 'Delayed job pushed successfully',
                    'data' => [
                        'job_id' => $isPushed,
                        'job_data' => $data,
                        'delay' => 60
                    ]
                ]);
            } else {
                return json([
                    'code' => 1,
                    'msg' => 'Failed to push delayed job to queue'
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to push delayed job: ' . $e->getMessage(), [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return json([
                'code' => 1,
                'msg' => 'Error: ' . $e->getMessage()
            ]);
        }
    }
} 