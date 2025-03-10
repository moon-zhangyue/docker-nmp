<?php

namespace app\job;

use think\queue\Job;
use think\facade\Log;

class KafkaTask
{
    public function fire(Job $job, $data)
    {
        try {
            // 处理具体的任务，避免重复的JSON编码操作
            // 只在调试模式下记录完整数据
            if (config('app.debug')) {
                Log::info('Processing Kafka task with data: {data}', ['data' => $data]);
            } else {
                Log::info('Processing Kafka task:{job_id}', ['job_id' => $job->getJobId()]);
            }

            // 任务处理完成，删除任务
            $job->delete();
        } catch (\Exception $e) {
            // 记录错误日志，包含更多上下文信息
            Log::error('Kafka task error: {error} {context}', [
                'error' => $e->getMessage(),
                'context' => [
                    'job_id' => $job->getJobId(),
                    'attempts' => $job->attempts(),
                    'trace' => $e->getTraceAsString()
                ]
            ]);

            // 重试次数超过3次则删除任务
            if ($job->attempts() > 3) {
                $job->delete();
            } else {
                // 重试任务，使用指数退避策略
                $delay = pow(2, $job->attempts());
                $job->release($delay);
            }
        }
    }
}
