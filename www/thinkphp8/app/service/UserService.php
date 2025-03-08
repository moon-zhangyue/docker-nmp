<?php
namespace app\service;

use app\model\User;
use think\facade\Log;

class UserService
{
    private $kafkaService;

    public function __construct()
    {
        $this->kafkaService = new KafkaService();
    }

    /**
     * 处理用户注册
     */
    public function register(array $data): bool
    {
        try {
            // 验证数据
            $this->validateRegistrationData($data);
            
            // 密码加密
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
            
            // 发送到Kafka消息队列
            $this->kafkaService->sendUserRegistrationMessage($data);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Registration failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 验证注册数据
     */
    private function validateRegistrationData(array $data): void
    {
        if (empty($data['username']) || empty($data['email']) || empty($data['password'])) {
            throw new \Exception('Username, email and password are required');
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \Exception('Invalid email format');
        }

        // 检查用户名是否已存在
        if (User::where('username', $data['username'])->find()) {
            throw new \Exception('Username already exists');
        }

        // 检查邮箱是否已存在
        if (User::where('email', $data['email'])->find()) {
            throw new \Exception('Email already exists');
        }
    }

    /**
     * 处理用户注册消息（消费者）
     */
    public function processRegistration(array $userData): void
    {
        try {
            // 创建用户记录
            $user = new User;
            $user->save($userData);

            // 这里可以添加其他注册后的处理逻辑
            // 例如：发送欢迎邮件、初始化用户配置等
            
            Log::info('User registered successfully', ['user_id' => $user->id]);
        } catch (\Exception $e) {
            Log::error('Failed to process registration: ' . $e->getMessage());
            throw $e;
        }
    }
} 