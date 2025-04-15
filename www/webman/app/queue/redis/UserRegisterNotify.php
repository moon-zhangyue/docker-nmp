<?php

namespace app\queue\redis;

use support\Log;
use Webman\RedisQueue\Consumer;

class UserRegisterNotify implements Consumer
{
    // 要消费的队列名
    public $queue = 'user-register-notify';

    // 连接名，对应 plugin/webman/redis-queue/redis.php 里的连接
    public string $connection = 'default';

    /**
     * 消费队列消息
     *
     * @param array $data 队列数据
     * @return void
     */
    public function consume($data): void
    {
        try {
            // 记录日志
            Log::info('处理用户注册通知', ['data' => $data]);

            // 这里可以实现发送欢迎邮件的逻辑
            $this->sendWelcomeEmail($data);

            // 这里可以实现其他注册后的业务逻辑
            // 例如：初始化用户配置、发送站内通知等

            Log::info('用户注册通知处理完成', ['user_id' => $data['user_id']]);
        } catch (\Throwable $e) {
            // 记录错误日志
            Log::error('处理用户注册通知失败', [
                'error' => $e->getMessage(),
                'data'  => $data
            ]);

            // 抛出异常，让队列系统进行重试
            throw $e;
        }
    }

    /**
     * 消费失败回调
     *
     * @param \Throwable $e 异常
     * @param array $package 包含队列数据的数组
     * @return void
     */
    public function onConsumeFailure(\Throwable $e, $package): void
    {
        Log::error('用户注册通知处理最终失败', [
            'error'   => $e->getMessage(),
            'package' => $package
        ]);
    }

    /**
     * 发送欢迎邮件
     *
     * @param array $data
     * @return void
     */
    protected function sendWelcomeEmail(array $data): void
    {
        // 模拟发送邮件，实际项目中可以使用邮件发送库
        Log::info('发送欢迎邮件', [
            'to'       => $data['email'],
            'subject'  => '欢迎注册我们的网站',
            'username' => $data['username']
        ]);

        // 实际发送邮件的代码可以在这里实现
        // 例如使用PHPMailer等库
    }
}