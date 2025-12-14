<?php

namespace app\listener;

use app\event\UserRegistered;
use support\Log;
use Webman\Event\EventInterface;

/**
 * 发送欢迎邮件监听器
 * 
 * 监听用户注册事件，发送欢迎邮件
 */
class SendWelcomeEmail implements EventInterface
{
    /**
     * 监听的事件
     * 
     * @return string
     */
    public function onEvent(): string
    {
        return UserRegistered::NAME;
    }
    
    /**
     * 处理事件
     * 
     * @param object $event
     * @return void
     */
    public function handle(object $event): void
    {
        // 记录日志
        Log::info('发送欢迎邮件', [
            'user_id' => $event->user->id,
            'username' => $event->user->username,
            'email' => $event->user->email
        ]);
        
        // 这里可以实现实际的邮件发送逻辑
        // 例如使用PHPMailer或其他邮件库
        echo "发送欢迎邮件给用户: {$event->user->email}\n";
    }
}