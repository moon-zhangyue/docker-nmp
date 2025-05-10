<?php
declare(strict_types=1);

namespace app\examples\rabbitmq;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use think\facade\Log;

/**
 * RabbitMQ发布/订阅模式示例
 * 
 * 发布/订阅模式下，一个生产者发送的消息会被多个消费者接收。
 * 每个消费者都会收到完全相同的消息副本。
 * 这种模式使用交换机（exchange）将消息广播到所有绑定的队列中。
 */
class PublishSubscribeMode
{
    /**
     * 交换机名称
     */
    protected $exchangeName = 'logs_fanout';

    /**
     * 交换机类型
     */
    protected $exchangeType = 'fanout';

    /**
     * 连接配置
     */
    protected $config = [];

    /**
     * 构造函数
     */
    public function __construct()
    {
        // 从配置文件中获取RabbitMQ连接信息
        $this->config = [
            'host'     => config('queue.connections.rabbitmq.host', 'rabbitmq'),
            'port'     => config('queue.connections.rabbitmq.port', 5672),
            'user'     => config('queue.connections.rabbitmq.login', 'myuser'),
            'password' => config('queue.connections.rabbitmq.password', 'mypass'),
            'vhost'    => config('queue.connections.rabbitmq.vhost', '/'),
        ];
    }

    /**
     * 生产者：发布消息
     *
     * @param string $message 消息内容
     * @return bool
     */
    public function publish(string $message): bool
    {
        try {
            // 创建连接
            $connection = new AMQPStreamConnection(
                $this->config['host'],
                $this->config['port'],
                $this->config['user'],
                $this->config['password'],
                $this->config['vhost']
            );

            // 创建通道
            $channel = $connection->channel();

            // 声明交换机
            $channel->exchange_declare(
                $this->exchangeName, // 交换机名称
                $this->exchangeType, // 交换机类型为fanout（广播）
                false,              // passive
                true,               // durable（持久化）
                false               // auto delete
            );

            // 创建消息
            $msg = new AMQPMessage(
                $message,
                [
                    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT, // 消息持久化
                    'content_type'  => 'text/plain',
                    'timestamp'     => time()
                ]
            );

            // 发布消息到交换机，不指定路由键（fanout交换机会忽略路由键）
            $channel->basic_publish($msg, $this->exchangeName, '');

            Log::info('发布/订阅模式 - 消息已发布: {message}', ['message' => $message]);

            // 关闭通道和连接
            $channel->close();
            $connection->close();

            return true;
        } catch (\Exception $e) {
            Log::error('发布/订阅模式 - 发布消息失败: {error}', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return false;
        }
    }

    /**
     * 消费者：订阅消息
     *
     * @param string $subscriberName 订阅者名称
     * @param callable $callback 回调函数，用于处理消息
     * @return void
     */
    public function subscribe(string $subscriberName, callable $callback): void
    {
        try {
            // 创建连接
            $connection = new AMQPStreamConnection(
                $this->config['host'],
                $this->config['port'],
                $this->config['user'],
                $this->config['password'],
                $this->config['vhost']
            );

            // 创建通道
            $channel = $connection->channel();

            // 声明交换机
            $channel->exchange_declare(
                $this->exchangeName, // 交换机名称
                $this->exchangeType, // 交换机类型为fanout（广播）
                false,              // passive
                true,               // durable（持久化）
                false               // auto delete
            );

            // 创建临时队列（队列名称由RabbitMQ自动生成）
            // 当消费者断开连接时，队列会自动删除
            list($queueName, , ) = $channel->queue_declare(
                "",    // 队列名称为空，由RabbitMQ自动生成
                false, // passive
                false, // durable（非持久化）
                true,  // exclusive（排他性队列，仅限此连接使用）
                true   // auto delete（自动删除）
            );

            // 绑定队列到交换机
            $channel->queue_bind($queueName, $this->exchangeName, '');

            Log::info('发布/订阅模式 - 订阅者 {subscriber} 已连接，队列: {queue}', [
                'subscriber' => $subscriberName,
                'queue'      => $queueName
            ]);

            // 消费消息
            $channel->basic_consume(
                $queueName,          // 队列名称
                '',                  // consumer tag
                false,               // no local
                false,               // no ack（设为false，需要手动确认）
                false,               // exclusive
                false,               // no wait
                function (AMQPMessage $message) use ($callback, $subscriberName) {
                    // 调用回调函数处理消息
                    $result = call_user_func($callback, $message->getBody(), $subscriberName);

                    // 确认消息已处理
                    $message->ack();

                    Log::info('发布/订阅模式 - 订阅者 {subscriber} 处理消息: {message}, 结果: {result}', [
                        'subscriber' => $subscriberName,
                        'message'    => $message->body,
                        'result'     => $result ? 'success' : 'failed'
                    ]);
                }
            );

            Log::info('发布/订阅模式 - 订阅者 {subscriber} 等待消息...', ['subscriber' => $subscriberName]);

            // 持续等待消息，直到连接关闭
            while ($channel->is_consuming()) {
                $channel->wait();
            }

            // 关闭通道和连接
            $channel->close();
            $connection->close();
        } catch (\Exception $e) {
            Log::error('发布/订阅模式 - 订阅者 {subscriber} 处理消息失败: {error}', [
                'subscriber' => $subscriberName,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * 批量发布消息示例
     *
     * @param int $count 消息数量
     * @return array 发布结果
     */
    public function batchPublish(int $count = 5): array
    {
        $results = [];

        for ($i = 1; $i <= $count; $i++) {
            $message = "Broadcast message #{$i} - " . date('Y-m-d H:i:s');

            $results[] = [
                'message_id' => $i,
                'message'    => $message,
                'success'    => $this->publish($message)
            ];
        }

        return $results;
    }

    /**
     * 使用示例
     */
    public function example(): void
    {
        // 发布消息示例
        $this->batchPublish(3);

        // 订阅者示例
        // 注意：在实际应用中，通常会在不同的进程或服务器上启动多个订阅者
        // 以下代码仅作为示例，实际使用时需要在不同进程中运行

        // 订阅者1 - 记录所有日志
        $this->subscribe('logger', function ($message, $subscriberName) {
            echo "[$subscriberName] 接收到消息: $message\n";
            file_put_contents('./logs/all_logs.log', "[$subscriberName] $message\n", FILE_APPEND);
            return true;
        });

        // 订阅者2 - 记录重要日志
        $this->subscribe('important-logger', function ($message, $subscriberName) {
            echo "[$subscriberName] 接收到消息: $message\n";
            if (strpos($message, 'important') !== false) {
                file_put_contents('./logs/important_logs.log', "[$subscriberName] $message\n", FILE_APPEND);
            }
            return true;
        });
    }
}