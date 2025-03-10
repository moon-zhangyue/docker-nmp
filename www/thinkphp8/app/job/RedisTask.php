<?php

namespace app\job;

use think\queue\Job;
use think\facade\Log;

class RedisTask
{
    /**
     * 任务执行
     */
    public function fire(Job $job, $data)
    {
        try {
            Log::info('Redis 任务执行: {data}', ['data' => $data]);

            // 模拟任务处理 或者发送邮件逻辑等
            if (rand(1, 10) > 7) { // 30% 概率失败
                throw new \Exception("Redis 任务失败");
            }

            $job->delete(); // 成功后删除任务

            // 也可以重新发布这个任务
            // $job->release($delay); //$delay为延迟时间
        } catch (\Exception $e) {
            Log::error('Redis 任务失败: {message}', ['message' => $e->getMessage()]);

            if ($job->attempts() > 3) {
                $job->hasFailed($e); // 超过3次失败则标记失败
            } else {
                $job->release(10); // 10秒后重试
            }
        }
    }
}
