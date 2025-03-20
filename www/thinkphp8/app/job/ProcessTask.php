<?php

declare(strict_types=1);

namespace app\job;


use think\facade\Log;
use think\queue\Job;
use think\facade\Db;

class ProcessTask
{
    public function fire(Job $job, $data)
    {
        // 模拟前三次执行失败
        $attempts = $job->attempts();
        Log::info('Current attempt: ' . $attempts);

        // 模拟任务失败，抛出异常
        // if ($attempts <= 3) {
        //     Log::info('模拟任务执行失败，当前尝试次数: ' . json_encode($attempts, JSON_UNESCAPED_UNICODE));

        //     $job->release($attempts);
        // } else {
        try {
            Log::info('Processing task: ' . json_encode($data, JSON_UNESCAPED_UNICODE));

            // 在这里处理你的任务逻辑
            $result = Db::name('user')->save($data);

            if ($result == true) {
                // 任务执行成功后删除任务
                $job->delete();

                Log::info('Task processed successfully: ' . json_encode($data, JSON_UNESCAPED_UNICODE));
            } else {
                Log::info('Task processed failed: 注册用户失败！');
            }
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
        // }
    }
}
