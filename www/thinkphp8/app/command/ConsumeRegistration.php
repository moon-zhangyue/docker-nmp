<?php

declare(strict_types=1); // 启用严格类型模式，确保代码中的类型声明是强制的

namespace app\command; // 定义当前类所在的命名空间

use think\console\Command; // 引入think框架的Command类
use think\console\Input; // 引入think框架的Input类
use think\console\Output; // 引入think框架的Output类
use app\service\UserService; // 引入应用服务层的UserService类
use app\service\KafkaService; // 引入应用服务层的KafkaService类
use think\facade\Log; // 引入think框架的Log门面

class ConsumeRegistration extends Command // 定义ConsumeRegistration类，继承自Command类
{
    protected function configure() // 配置命令
    {
        $this->setName('consume:registration') // 设置命令名称
            ->setDescription('Consume user registration messages from Kafka'); // 设置命令描述
    }

    protected function execute(Input $input, Output $output) // 执行命令
    {
        $output->writeln('Starting user registration consumer...'); // 输出开始信息

        try {
            $kafkaService = new KafkaService(); // 创建KafkaService实例
            $userService = new UserService(); // 创建UserService实例

            $output->writeln('Connected to Kafka broker: ' . env('KAFKA_BROKERS', 'localhost:9092')); // 输出Kafka broker连接信息
            $output->writeln('Using consumer group: ' . env('KAFKA_GROUP_ID', 'user-registration-group')); // 输出使用的消费者组信息

            $kafkaService->consumeUserRegistrationMessages(function ($userData) use ($userService, $output) { // 消费用户注册消息
                try {
                    $output->writeln('Processing registration for user: ' . $userData['username']); // 输出正在处理的信息
                    $userService->processRegistration($userData); // 调用UserService处理注册
                    $output->writeln('Registration processed successfully'); // 输出处理成功信息
                } catch (\Exception $e) { // 捕获异常
                    $output->writeln('Error processing registration: ' . $e->getMessage()); // 输出错误信息
                    Log::error('Consumer error: {message}', ['message' => $e->getMessage()]); // 记录错误日志
                }
            });
        } catch (\Exception $e) { // 捕获异常
            $output->writeln('Consumer error: ' . $e->getMessage()); // 输出错误信息
            Log::error('Consumer error: {message}', ['message' => $e->getMessage()]); // 记录错误日志
            return 1; // 返回错误码
        }
    }
}
