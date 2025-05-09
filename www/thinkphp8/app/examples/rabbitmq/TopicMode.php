<?php
declare(strict_types=1);

namespace app\examples\rabbitmq;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use think\facade\Log;

/**
 * RabbitMQ主题模式示例
 * 
 * 主题模式是路由模式的扩展，允许使用通配符进行更灵活的路由匹配。
 * 路由键必须是由点分隔的单词列表，如"stock.usd.nyse"。
 * 支持两种通配符：
 * - * 表示匹配一个单词
 * - # 表示匹配零个或多个单词
 */
class TopicMode
{
    /**
     * 交换机名称
     */
    protected $exchangeName = 'topic_logs';
    
    /**
     * 交换机类型
     */
    protected $exchangeType = 'topic';
    
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
     * 生产者：发布带主题路由键的消息
     *
     * @param string $message 消息内容
     * @param string $routingKey 主题路由键，如"app.error.critical"
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
                $this->exchangeType, // 交换机类型为topic（主题）
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
            
            // 发布消息到交换机，指定主题路由键
            $channel->basic_publish($msg, $this->exchangeName, $routingKey);
            
            Log::info('主题模式 - 消息已发布: {message}, 主题路由键: {routing_key}', [
                'message'     => $message,
                'routing_key' => $routingKey
            ]);
            
            // 关闭通道和连接
            $channel->close();
            $connection->close();
            
            return true;
        } catch (\Exception $e) {
            Log::error('主题模式 - 发布消息失败: {error}, 主题路由键: {routing_key}', [
                'error'       => $e->getMessage(),
                'routing_key' => $routingKey,
                'trace'       => $e->getTraceAsString()
            ]);
            
            return false;
        }
    }
    
    /**
     * 消费者：订阅特定主题模式的消息
     *
     * @param string $consumerName 消费者名称
     * @param array $topicPatterns 要订阅的主题模式数组，如["app.*.critical", "kernel.#"]
     * @param callable $callback 回调函数，用于处理消息
     * @return void
     */
    public function subscribe(string $consumerName, array $topicPatterns, callable $callback): void
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
                $this->exchangeType, // 交换机类型为topic（主题）
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
            
            // 绑定队列到交换机，指定多个主题模式
            foreach ($topicPatterns as $pattern) {
                $channel->queue_bind($queueName, $this->exchangeName, $pattern);
            }
            
            Log::info('主题模式 - 消费者 {consumer} 已连接，队列: {queue}, 订阅主题模式: {patterns}', [
                'consumer' => $consumerName,
                'queue'    => $queueName,
                'patterns' => implode(', ', $topicPatterns)
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
                    
                    Log::info('主题模式 - 消费者 {consumer} 处理消息: {message}, 主题路由键: {routing_key}, 结果: {result}', [
                        'consumer'    => $consumerName,
                        'message'     => $message->body,
                        'routing_key' => $routingKey,
                        'result'      => $result ? 'success' : 'failed'
                    ]);
                }
            );
            
            Log::info('主题模式 - 消费者 {consumer} 等待消息...', ['consumer' => $consumerName]);
            
            // 持续等待消息，直到连接关闭
            while ($channel->is_consuming()) {
                $channel->wait();
            }
            
            // 关闭通道和连接
            $channel->close();
            $connection->close();
        } catch (\Exception $e) {
            Log::error('主题模式 - 消费者 {consumer} 处理消息失败: {error}', [
                'consumer' => $consumerName,
                'error'    => $e->getMessage(),
                'trace'    => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * 批量发布不同主题的消息示例
     *
     * @return array 发布结果
     */
    public function publishTopicMessages(): array
    {
        $results = [];
        
        // 定义不同主题和对应的消息
        $topics = [
            'app.error.critical' => "应用严重错误 - " . date('Y-m-d H:i:s'),
            'app.error.warning'  => "应用警告错误 - " . date('Y-m-d H:i:s'),
            'app.info.startup'   => "应用启动信息 - " . date('Y-m-d H:i:s'),
            'kernel.error.fatal' => "内核致命错误 - " . date('Y-m-d H:i:s'),
            'kernel.info.stats'  => "内核统计信息 - " . date('Y-m-d H:i:s'),
            'system.status.ok'   => "系统状态正常 - " . date('Y-m-d H:i:s'),
            'database.mysql.error' => "MySQL数据库错误 - " . date('Y-m-d H:i:s'),
            'database.redis.warning' => "Redis警告信息 - " . date('Y-m-d H:i:s')
        ];
        
        // 发布不同主题的消息
        foreach ($topics as $topic => $message) {
            $results[] = [
                'topic'   => $topic,
                'message' => $message,
                'success' => $this->publish($message, $topic)
            ];
        }
        
        return $results;
    }
    
    /**
     * 使用示例
     */
    public function example(): void
    {
        // 发布主题消息示例
        $this->publishTopicMessages();
        
        // 消费者示例
        // 注意：在实际应用中，通常会在不同的进程或服务器上启动多个消费者
        // 以下代码仅作为示例，实际使用时需要在不同进程中运行
        
        // 消费者1 - 处理所有错误消息（使用#通配符）
        // $this->subscribe('error-handler', ['*.error.#'], function ($message, $routingKey, $consumerName) {
        //     echo "[$consumerName] 接收到[$routingKey]消息: $message\n";
        //     file_put_contents('./logs/all_errors.log', "[$routingKey] $message\n", FILE_APPEND);
        //     return true;
        // });
        
        // 消费者2 - 只处理应用相关消息
        // $this->subscribe('app-monitor', ['app.#'], function ($message, $routingKey, $consumerName) {
        //     echo "[$consumerName] 接收到[$routingKey]消息: $message\n";
        //     file_put_contents('./logs/app_logs.log', "[$routingKey] $message\n", FILE_APPEND);
        //     return true;
        // });
        
        // 消费者3 - 处理所有严重错误（使用*通配符）
        // $this->subscribe('critical-monitor', ['*.*.critical'], function ($message, $routingKey, $consumerName) {
        //     echo "[$consumerName] 接收到[$routingKey]消息: $message\n";
        //     file_put_contents('./logs/critical.log', "[$routingKey] $message\n", FILE_APPEND);
        //     return true;
        // });
        
        // 消费者4 - 处理所有数据库相关消息
        // $this->subscribe('db-monitor', ['database.#'], function ($message, $routingKey, $consumerName) {
        //     echo "[$consumerName] 接收到[$routingKey]消息: $message\n";
        //     file_put_contents('./logs/database.log', "[$routingKey] $message\n", FILE_APPEND);
        //     return true;
        // });
    }
} 