<?php

declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use think\facade\Queue;
use think\facade\Log;
use think\Request;

class KafkaQueue extends BaseController
{
    /**
     * 添加任务到 Kafka 队列
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

            // 使用 think-queue 的 Kafka 驱动
            $jobHandlerClass = 'app\\job\\ProcessTask';
            $jobQueueName = config('queue.connections.kafka.queue'); // Kafka 队列名称
            $jobData = $data;

            // 添加任务到队列
            $isPushed = Queue::push($jobHandlerClass, $jobData, $jobQueueName);

            if ($isPushed !== false) {
                return json([
                    'code' => 0,
                    'msg' => 'Task added to Kafka queue successfully',
                    'data' => [
                        'queue' => $jobQueueName,
                        'task_data' => $jobData
                    ]
                ]);
            } else {
                Log::error('Kafka queue push failed:queue=>{queue} data => {data}', [
                    'queue' => $jobQueueName,
                    'data' => $jobData
                ]);
                return json([
                    'code' => 1,
                    'msg' => 'Failed to add task to Kafka queue',
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to push task to Kafka: ' . $e->getMessage() . '-trace:' . $e->getTraceAsString());

            return json([
                'code' => 1,
                'msg' => 'Failed to add task to Kafka queue: ' . $e->getMessage(),
            ]);
        }
    }
}
