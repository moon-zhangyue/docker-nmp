<?php
declare(strict_types=1);

namespace app\examples\rabbitmq;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use think\facade\Log;

/**
 * RabbitMQ简单模式示例
 * 
 * 简单模式是最基础的消息模式，一个生产者对应一个消费者
 */
class SimpleMode
{
    /**
     * 队列名称
     */
    protected $queueName = 'simple_queue';

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
     * 生产者：发送消息
     *
     * @param string $message 消息内容
     * @return bool
     */
    public function send(string $message): bool
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

            // 声明队列
            $channel->queue_declare(
                $this->queueName, // 队列名称
                false,           // passive
                true,            // durable（持久化）
                false,           // exclusive
                false            // auto delete
            );

            // 创建消息
            $msg = new AMQPMessage(
                $message,
                [
                    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT, // 消息持久化
                    'content_type'  => 'text/plain',
                ]
            );

            // 发布消息到队列
            $channel->basic_publish($msg, '', $this->queueName);

            Log::info('简单模式 - 消息已发送: {message}', ['message' => $message]);

            // 关闭通道和连接
            $channel->close();
            $connection->close();

            return true;
        } catch (\Exception $e) {
            Log::error('简单模式 - 发送消息失败: {error}', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return false;
        }
    }

    /**
     * 消费者：接收消息
     *
     * @param callable $callback 回调函数，用于处理消息
     * @return void
     */
    public function receive(callable $callback): void
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

            // 声明队列
            $channel->queue_declare(
                $this->queueName, // 队列名称
                false,           // passive
                true,            // durable（持久化）
                false,           // exclusive
                false            // auto delete
            );

            // 设置每次只接收一条消息
            $channel->basic_qos(0, 1, null);

            Log::info('简单模式 - 等待接收消息...');

            // 消费消息
            $channel->basic_consume(
                $this->queueName,        // 队列名称
                '',                      // consumer tag
                false,                   // no local
                false,                   // no ack
                false,                   // exclusive
                false,                   // no wait
                function (AMQPMessage $message) use ($callback) {
                    // 调用回调函数处理消息
                    $result = call_user_func($callback, $message->getBody());

                    // 确认消息已处理
                    $message->ack();

                    Log::info('简单模式 - 消息已处理: {message}, 结果: {result}', [
                        'message' => $message->getBody(),
                        'result'  => $result ? 'success' : 'failed'
                    ]);
                }
            );

            // 持续等待消息，直到连接关闭
            while ($channel->is_consuming()) {
                $channel->wait();
            }

            // 关闭通道和连接
            $channel->close();
            $connection->close();
        } catch (\Exception $e) {
            Log::error('简单模式 - 接收消息失败: {error}', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * 使用示例
     */
    public function example(): void
    {
        // 发送消息示例
        $this->send('Hello RabbitMQ Simple Mode! ' . date('Y-m-d H:i:s'));

        // 接收消息示例
        // 注意：在实际应用中，消费者通常在单独的进程中运行
        // $this->receive(function ($message) {
        //     echo "收到消息: $message\n";
        //     return true;
        // });
    }
}