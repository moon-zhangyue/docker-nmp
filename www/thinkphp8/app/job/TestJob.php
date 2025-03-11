<?php

declare(strict_types=1);

namespace app\job;

use think\queue\Job;
use think\facade\Log;

class TestJob
{
    public function fire(Job $job, $data)
    {
        try {
            // 记录任务开始
            Log::info('Processing job', ['data' => $data]);

            // 执行具体的任务逻辑
            $this->handleJob($data);

            // 任务执行成功，删除任务
            $job->delete();

            // 记录任务完成
            Log::info('Job completed successfully', ['data' => $data]);
        } catch (\Exception $e) {
            // 记录错误
            Log::error('Job failed', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);

            // 重试次数未超过最大重试次数，重试任务
            if ($job->attempts() < 3) {
                // 延迟10秒后重试
                $job->release(10);
            } else {
                // 超过重试次数，标记失败
                $job->fail();
            }
        }
    }

    protected function handleJob($data)
    {
        // 这里实现具体的任务处理逻辑
        // 例如：处理数据、发送邮件等

        // 模拟任务处理
        sleep(1);

        if (isset($data['throw_error']) && $data['throw_error']) {
            throw new \Exception('Job processing failed');
        }
    }
}
