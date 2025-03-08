<?php
declare (strict_types = 1);

namespace app\controller;

use app\BaseController;
use app\service\RedisQueue;
use think\facade\Log;
use think\Request;

class Queue extends BaseController
{
    protected $queue;

    public function __construct()
    {
        // parent::__construct();
        $this->queue = new RedisQueue();
    }

    /**
     * 添加任务到队列
     */
    public function push(Request $request)
    {
        try {
            $data = $request->post();
            
            if (empty($data)) {
                return json([
                    'code' => 1,
                    'msg' => 'Task data is required',
                ]);
            }

            // 可以指定不同的队列名称
            $queueName = $request->post('queue', RedisQueue::DEFAULT_QUEUE);
            
            // 添加任务到队列
            $result = $this->queue->push($data, $queueName);
            
            if ($result) {
                return json([
                    'code' => 0,
                    'msg' => 'Task added to queue successfully',
                    'data' => [
                        'queue' => $queueName,
                        'task_data' => $data
                    ]
                ]);
            } else {
                Log::error('Queue push failed with no exception', [
                    'queue' => $queueName,
                    'data' => $data
                ]);
                return json([
                    'code' => 1,
                    'msg' => 'Failed to add task to queue',
                    'debug' => [
                        'queue' => $queueName,
                        'data' => $data
                    ]
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to push task: ' . $e->getMessage(), [
                'queue' => $queueName ?? RedisQueue::DEFAULT_QUEUE,
                'data' => $data ?? [],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return json([
                'code' => 1,
                'msg' => 'Failed to add task to queue: ' . $e->getMessage(),
                'debug' => [
                    'error' => $e->getMessage(),
                    'queue' => $queueName ?? RedisQueue::DEFAULT_QUEUE,
                ]
            ]);
        }
    }

    /**
     * 获取队列状态
     */
    public function status(Request $request)
    {
        try {
            $queueName = $request->param('queue', RedisQueue::DEFAULT_QUEUE);
            
            $length = $this->queue->length($queueName);
            $tasks = $this->queue->peek($queueName, 0, 9); // 获取前10个任务
            
            return json([
                'code' => 0,
                'data' => [
                    'queue' => $queueName,
                    'length' => $length,
                    'recent_tasks' => $tasks,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get queue status: ' . $e->getMessage());
            return json([
                'code' => 1,
                'msg' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 清空队列
     */
    public function clear(Request $request)
    {
        try {
            $queueName = $request->param('queue', RedisQueue::DEFAULT_QUEUE);
            
            if ($this->queue->clear($queueName)) {
                return json([
                    'code' => 0,
                    'msg' => 'Queue cleared successfully',
                ]);
            } else {
                return json([
                    'code' => 1,
                    'msg' => 'Failed to clear queue',
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to clear queue: ' . $e->getMessage());
            return json([
                'code' => 1,
                'msg' => $e->getMessage(),
            ]);
        }
    }
} 