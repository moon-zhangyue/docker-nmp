<?php
declare(strict_types=1);

namespace app\examples\rabbitmq;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use think\facade\Log;

/**
 * RabbitMQ路由模式示例
 * 
 * 路由模式允许生产者通过路由键（routing key）将消息发送到特定的队列。
 * 消费者可以选择性地接收自己感兴趣的消息。
 * 这种模式使用direct类型的交换机，根据路由键将消息路由到对应的队列。
 */
class RoutingMode
{
    /**
     * 交换机名称
     */
    protected $exchangeName = 'direct_logs';
    
    /**
     * 交换机类型
     */
    protected $exchangeType = 'direct';
    
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
     * 生产者：发布带路由键的消息
     *
     * @param string $message 消息内容
     * @param string $routingKey 路由键
     * @return bool
     */
    public function publish(string $message, string $routingKey): bool
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
                $this->exchangeType, // 交换机类型为direct（直接路由）
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
            
            // 发布消息到交换机，指定路由键
            $channel->basic_publish($msg, $this->exchangeName, $routingKey);
            
            Log::info('路由模式 - 消息已发布: {message}, 路由键: {routing_key}', [
                'message'     => $message,
                'routing_key' => $routingKey
            ]);
            
            // 关闭通道和连接
            $channel->close();
            $connection->close();
            
            return true;
        } catch (\Exception $e) {
            Log::error('路由模式 - 发布消息失败: {error}, 路由键: {routing_key}', [
                'error'       => $e->getMessage(),
                'routing_key' => $routingKey,
                'trace'       => $e->getTraceAsString()
            ]);
            
            return false;
        }
    }
    
    /**
     * 消费者：订阅特定路由键的消息
     *
     * @param string $consumerName 消费者名称
     * @param array $routingKeys 要订阅的路由键数组
     * @param callable $callback 回调函数，用于处理消息
     * @return void
     */
    public function subscribe(string $consumerName, array $routingKeys, callable $callback): void
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
                $this->exchangeType, // 交换机类型为direct（直接路由）
                false,              // passive
                true,               // durable（持久化）
                false               // auto delete
            );
            
            // 创建临时队列（队列名称由RabbitMQ自动生成）
            list($queueName, ,) = $channel->queue_declare(
                "",    // 队列名称为空，由RabbitMQ自动生成
                false, // passive
                false, // durable（非持久化）
                true,  // exclusive（排他性队列，仅限此连接使用）
                true   // auto delete（自动删除）
            );
            
            // 绑定队列到交换机，指定多个路由键
            foreach ($routingKeys as $routingKey) {
                $channel->queue_bind($queueName, $this->exchangeName, $routingKey);
            }
            
            Log::info('路由模式 - 消费者 {consumer} 已连接，队列: {queue}, 订阅路由键: {routing_keys}', [
                'consumer'     => $consumerName,
                'queue'        => $queueName,
                'routing_keys' => implode(', ', $routingKeys)
            ]);
            
            // 消费消息
            $channel->basic_consume(
                $queueName,          // 队列名称
                '',                  // consumer tag
                false,               // no local
                false,               // no ack（设为false，需要手动确认）
                false,               // exclusive
                false,               // no wait
                function (AMQPMessage $message) use ($callback, $consumerName) {
                    // 获取路由键
                    $routingKey = $message->getRoutingKey();
                    
                    // 调用回调函数处理消息
                    $result = call_user_func($callback, $message->body, $routingKey, $consumerName);
                    
                    // 确认消息已处理
                    $message->ack();
                    
                    Log::info('路由模式 - 消费者 {consumer} 处理消息: {message}, 路由键: {routing_key}, 结果: {result}', [
                        'consumer'    => $consumerName,
                        'message'     => $message->body,
                        'routing_key' => $routingKey,
                        'result'      => $result ? 'success' : 'failed'
                    ]);
                }
            );
            
            Log::info('路由模式 - 消费者 {consumer} 等待消息...', ['consumer' => $consumerName]);
            
            // 持续等待消息，直到连接关闭
            while ($channel->is_consuming()) {
                $channel->wait();
            }
            
            // 关闭通道和连接
            $channel->close();
            $connection->close();
        } catch (\Exception $e) {
            Log::error('路由模式 - 消费者 {consumer} 处理消息失败: {error}', [
                'consumer' => $consumerName,
                'error'    => $e->getMessage(),
                'trace'    => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * 批量发布不同级别的日志消息示例
     *
     * @return array 发布结果
     */
    public function publishLogs(): array
    {
        $results = [];
        
        // 定义日志级别和对应的消息
        $logs = [
            'info'    => "普通信息日志 - " . date('Y-m-d H:i:s'),
            'warning' => "警告日志 - " . date('Y-m-d H:i:s'),
            'error'   => "错误日志 - " . date('Y-m-d H:i:s'),
            'critical' => "严重错误日志 - " . date('Y-m-d H:i:s')
        ];
        
        // 发布不同级别的日志
        foreach ($logs as $level => $message) {
            $results[] = [
                'level'   => $level,
                'message' => $message,
                'success' => $this->publish($message, $level)
            ];
        }
        
        return $results;
    }
    
    /**
     * 使用示例
     */
    public function example(): void
    {
        // 发布日志消息示例
        $this->publishLogs();
        
        // 消费者示例
        // 注意：在实际应用中，通常会在不同的进程或服务器上启动多个消费者
        // 以下代码仅作为示例，实际使用时需要在不同进程中运行
        
        // 消费者1 - 只处理错误和严重错误日志
        // $this->subscribe('error-handler', ['error', 'critical'], function ($message, $routingKey, $consumerName) {
        //     echo "[$consumerName] 接收到[$routingKey]级别消息: $message\n";
        //     file_put_contents('./logs/errors.log', "[$routingKey] $message\n", FILE_APPEND);
        //     return true;
        // });
        
        // 消费者2 - 处理所有日志
        // $this->subscribe('all-logs-handler', ['info', 'warning', 'error', 'critical'], function ($message, $routingKey, $consumerName) {
        //     echo "[$consumerName] 接收到[$routingKey]级别消息: $message\n";
        //     file_put_contents('./logs/all.log', "[$routingKey] $message\n", FILE_APPEND);
        //     return true;
        // });
        
        // 消费者3 - 只处理警告日志
        // $this->subscribe('warning-handler', ['warning'], function ($message, $routingKey, $consumerName) {
        //     echo "[$consumerName] 接收到[$routingKey]级别消息: $message\n";
        //     file_put_contents('./logs/warnings.log', "[$routingKey] $message\n", FILE_APPEND);
        //     return true;
        // });
    }
} 