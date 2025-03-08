<?php

declare(strict_types=1);

namespace app\job;


use think\facade\Log;
use think\queue\Job;

class ProcessTask
{
    public function fire(Job $job, $data)
    {
        try {
            Log::info('Processing task: ' . json_encode($data, JSON_UNESCAPED_UNICODE));

            // 在这里处理你的任务逻辑
            // ...

            // 任务执行成功后删除任务
            $job->delete();

            Log::info('Task processed successfully: ' . json_encode($data, JSON_UNESCAPED_UNICODE));
        } catch (\Exception $e) {
            Log::error(
                'Task processing failed:' .
                    'error:' . $e->getMessage() .
                    '-trace:' . $e->getTraceAsString()
            );

            // 如果任务执行失败，记录错误日志并重试
            if ($job->attempts() > 3) {
                $job->delete();
            } else {
                $job->release(3);
            }
        }
    }
}
