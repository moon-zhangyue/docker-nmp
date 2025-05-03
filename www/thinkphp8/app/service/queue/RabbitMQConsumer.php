<?php
declare(strict_types=1);

namespace app\service\queue;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use think\facade\Log;
use think\helper\Arr;

/**
 * RabbitMQ消费者服务
 * 
 * 该类封装了RabbitMQ消息队列的消费者功能，提供了消费消息的方法
 * 
 * @package app\service\queue
 */
class RabbitMQConsumer
{
    /**
     * RabbitMQ连接实例
     *
     * @var AMQPStreamConnection
     */
    protected $connection;

    /**
     * RabbitMQ通道实例
     *
     * @var AMQPChannel
     */
    protected $channel;

    /**
     * 配置选项
     *
     * @var array
     */
    protected $options = [];

    /**
     * 是否正在运行
     *
     * @var bool
     */
    protected $running = false;

    /**
     * 构造函数
     *
     * @param array $options 配置选项
     */
    public function __construct(array $options = [])
    {
        // 默认配置
        $defaultOptions = [
            'host' => 'localhost',
            'port' => 5672,
            'login' => 'guest',
            'password' => 'guest',
            'vhost' => '/',
            'exchange' => 'default',
            'exchange_type' => 'direct',
            'queue' => 'default',
            'prefetch_count' => 1,
            'consumer_tag' => 'consumer_' . uniqid(),
        ];
        
        $this->options = array_merge($defaultOptions, $options);
        
        // 初始化连接
        $this->initConnection();
    }

    /**
     * 初始化RabbitMQ连接
     *
     * @return void
     */
    protected function initConnection()
    {
        try {
            // 创建连接
            $this->connection = new AMQPStreamConnection(
                $this->options['host'],
                $this->options['port'],
                $this->options['login'],
                $this->options['password'],
                $this->options['vhost']
            );
            
            // 创建通道
            $this->channel = $this->connection->channel();
            
            // 声明交换机
            $this->channel->exchange_declare(
                $this->options['exchange'],
                $this->options['exchange_type'],
                false, // passive
                true,  // durable
                false  // auto_delete
            );
            
            // 声明队列
            $this->channel->queue_declare(
                $this->options['queue'],
                false, // passive
                true,  // durable
                false, // exclusive
                false  // auto_delete
            );
            
            // 绑定队列到交换机
            $this->channel->queue_bind(
                $this->options['queue'],
                $this->options['exchange'],
                $this->options['queue']
            );
            
            // 设置QOS
            $this->channel->basic_qos(
                0,                              // prefetch_size
                $this->options['prefetch_count'], // prefetch_count
                false                           // global
            );
            
            Log::info('RabbitMQ消费者连接初始化成功', [
                'queue' => $this->options['queue'],
                'exchange' => $this->options['exchange']
            ]);
        } catch (\Exception $e) {
            Log::error('RabbitMQ消费者连接初始化失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    /**
     * 消费消息
     *
     * @param callable $callback 回调函数，接收消息体和消息对象
     * @param bool $noAck 是否自动确认
     * @return void
     */
    public function consume(callable $callback, bool $noAck = false)
    {
        $this->running = true;
        
        try {
            // 设置消息处理回调
            $this->channel->basic_consume(
                $this->options['queue'],        // queue
                $this->options['consumer_tag'], // consumer_tag
                false,                          // no_local
                $noAck,                         // no_ack
                false,                          // exclusive
                false,                          // nowait
                function (AMQPMessage $message) use ($callback, $noAck) {
                    try {
                        // 解析消息体
                        $body = json_decode($message->getBody(), true);
                        
                        Log::info('收到消息', [
                            'queue' => $this->options['queue'],
                            'message_id' => Arr::get($body, 'id'),
                            'job' => Arr::get($body, 'job')
                        ]);
                        
                        // 调用回调函数处理消息
                        $result = call_user_func($callback, $body, $message);
                        
                        // 如果不是自动确认，且回调返回true，则手动确认
                        if (!$noAck && $result === true) {
                            $message->ack();
                            
                            Log::info('消息处理成功，已确认', [
                                'message_id' => Arr::get($body, 'id')
                            ]);
                        }
                    } catch (\Exception $e) {
                        Log::error('消息处理异常', [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                        
                        // 如果不是自动确认，则拒绝消息并重新入队
                        if (!$noAck) {
                            $message->reject(true);
                            
                            Log::info('消息处理失败，已拒绝并重新入队', [
                                'message_id' => Arr::get($body, 'id', 'unknown')
                            ]);
                        }
                    }
                }
            );
            
            Log::info('开始消费消息', [
                'queue' => $this->options['queue'],
                'consumer_tag' => $this->options['consumer_tag']
            ]);
            
            // 持续等待消息
            while ($this->running && $this->channel->is_consuming()) {
                $this->channel->wait();
            }
        } catch (\Exception $e) {
            Log::error('消费消息异常', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->stop();
            throw $e;
        }
    }

    /**
     * 停止消费
     *
     * @return void
     */
    public function stop()
    {
        $this->running = false;
        
        try {
            if ($this->channel && $this->channel->is_open()) {
                $this->channel->basic_cancel($this->options['consumer_tag']);
                Log::info('已停止消费', [
                    'consumer_tag' => $this->options['consumer_tag']
                ]);
            }
        } catch (\Exception $e) {
            Log::error('停止消费异常', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 关闭连接
     *
     * @return void
     */
    public function close()
    {
        try {
            if ($this->channel && $this->channel->is_open()) {
                $this->channel->close();
            }
            
            if ($this->connection && $this->connection->isConnected()) {
                $this->connection->close();
            }
            
            Log::info('RabbitMQ连接已关闭');
        } catch (\Exception $e) {
            Log::error('关闭RabbitMQ连接异常', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 析构函数，确保连接关闭
     */
    public function __destruct()
    {
        $this->close();
    }
}
