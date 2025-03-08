<?php

namespace app\job;

use think\queue\Job;
use think\facade\Log;

class KafkaTask
{
    public function fire(Job $job, $data)
    {
        try {
            // 处理具体的任务
            Log::info('Processing Kafka task with data: ' . json_encode($data));

            // 任务处理完成，删除任务
            $job->delete();
        } catch (\Exception $e) {
            // 记录错误日志
            Log::error('Kafka task error: ' . $e->getMessage());

            // 重试次数超过3次则删除任务
            if ($job->attempts() > 3) {
                $job->delete();
            } else {
                // 重试任务
                $job->release(3);
            }
        }
    }
}
