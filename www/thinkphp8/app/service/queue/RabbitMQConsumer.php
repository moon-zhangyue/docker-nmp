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
            'host'           => 'localhost',
            'port'           => 5672,
            'login'          => 'guest',
            'password'       => 'guest',
            'vhost'          => '/',
            'exchange'       => 'default',
            'exchange_type'  => 'direct',
            'queue'          => 'default',
            'prefetch_count' => 1,
            'consumer_tag'   => 'consumer_' . uniqid(),
        ];

        $this->options = array_merge($defaultOptions, $options);

        // 初始化连接
        $this->initConnection();
    }

    /**
     * 初始化RabbitMQ连接
     *
     * @return void
     * @throws \Exception 连接失败时抛出异常
     */
    protected function initConnection(): void
    {
        try {
            // 创建连接参数
            $connectionParams = [
                'host'     => $this->options['host'],
                'port'     => $this->options['port'],
                'user'     => $this->options['login'],
                'password' => $this->options['password'],
                'vhost'    => $this->options['vhost']
            ];

            // 添加可选的连接参数
            if (isset($this->options['heartbeat'])) {
                $connectionParams['heartbeat'] = $this->options['heartbeat'];
            }

            if (isset($this->options['connection_timeout'])) {
                $connectionParams['connection_timeout'] = $this->options['connection_timeout'];
            }

            if (isset($this->options['read_write_timeout'])) {
                $connectionParams['read_write_timeout'] = $this->options['read_write_timeout'];
            }

            // 添加SSL支持
            if (isset($this->options['ssl']) && $this->options['ssl']) {
                $connectionParams['ssl'] = true;

                if (isset($this->options['ssl_options'])) {
                    $connectionParams['ssl_options'] = $this->options['ssl_options'];
                }
            }

            // 创建连接
            $this->connection = new AMQPStreamConnection(
                $connectionParams['host'],
                $connectionParams['port'],
                $connectionParams['user'],
                $connectionParams['password'],
                $connectionParams['vhost'],
                $connectionParams['ssl'] ?? false,
                $connectionParams['ssl_options'] ?? [],
                $connectionParams['heartbeat'] ?? 60,
                $connectionParams['connection_timeout'] ?? 3.0,
                $connectionParams['read_write_timeout'] ?? 3.0
            );

            // 创建通道
            $this->channel = $this->connection->channel();

            // 声明交换机
            $this->declareExchange();

            // 声明队列
            $this->declareQueue();

            // 绑定队列到交换机
            $this->bindQueueToExchange();

            // 设置QOS
            $this->setQos();

            Log::info('RabbitMQ消费者连接初始化成功 - 队列: {queue}, 交换机: {exchange}', [
                'queue'    => $this->options['queue'],
                'exchange' => $this->options['exchange']
            ]);
        } catch (\Exception $e) {
            Log::error('RabbitMQ消费者连接初始化失败: 错误信息: {error}, 调用堆栈: {trace}, 配置信息: {options}', [
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
                'options' => array_diff_key($this->options, ['password' => '***']) // 记录配置但隐藏密码
            ]);

            // 清理资源
            $this->close();

            throw $e;
        }
    }

    /**
     * 声明交换机
     *
     * @return void
     */
    protected function declareExchange(): void
    {
        $passive    = $this->options['passive_exchange'] ?? false;
        $durable    = $this->options['durable_exchange'] ?? true;
        $autoDelete = $this->options['auto_delete_exchange'] ?? false;
        $internal   = $this->options['internal_exchange'] ?? false;
        $nowait     = $this->options['nowait_exchange'] ?? false;
        $arguments  = $this->options['exchange_arguments'] ?? [];

        $this->channel->exchange_declare(
            $this->options['exchange'],
            $this->options['exchange_type'],
            $passive,
            $durable,
            $autoDelete,
            $internal,
            $nowait,
            $arguments
        );
    }

    /**
     * 声明队列
     *
     * @return void
     */
    protected function declareQueue(): void
    {
        $passive    = $this->options['passive_queue'] ?? false;
        $durable    = $this->options['durable_queue'] ?? true;
        $exclusive  = $this->options['exclusive_queue'] ?? false;
        $autoDelete = $this->options['auto_delete_queue'] ?? false;
        $nowait     = $this->options['nowait_queue'] ?? false;
        $arguments  = $this->options['queue_arguments'] ?? [];

        // 添加死信队列支持
        if (isset($this->options['dead_letter_exchange'])) {
            $arguments['x-dead-letter-exchange'] = $this->options['dead_letter_exchange'];

            if (isset($this->options['dead_letter_routing_key'])) {
                $arguments['x-dead-letter-routing-key'] = $this->options['dead_letter_routing_key'];
            }
        }

        // 添加消息TTL支持
        if (isset($this->options['message_ttl'])) {
            $arguments['x-message-ttl'] = (int) $this->options['message_ttl'];
        }

        // 添加队列TTL支持
        if (isset($this->options['queue_ttl'])) {
            $arguments['x-expires'] = (int) $this->options['queue_ttl'];
        }

        // 添加队列最大长度支持
        if (isset($this->options['max_length'])) {
            $arguments['x-max-length'] = (int) $this->options['max_length'];
        }

        $this->channel->queue_declare(
            $this->options['queue'],
            $passive,
            $durable,
            $exclusive,
            $autoDelete,
            $nowait,
            $arguments
        );
    }

    /**
     * 绑定队列到交换机
     *
     * @return void
     */
    protected function bindQueueToExchange(): void
    {
        $routingKey = $this->options['routing_key'] ?? $this->options['queue'];
        $nowait     = $this->options['nowait_bind'] ?? false;
        $arguments  = $this->options['bind_arguments'] ?? [];

        $this->channel->queue_bind(
            $this->options['queue'],
            $this->options['exchange'],
            $routingKey,
            $nowait,
            $arguments
        );
    }

    /**
     * 设置QOS
     *
     * @return void
     */
    protected function setQos(): void
    {
        $prefetchSize  = $this->options['prefetch_size'] ?? 0;
        $prefetchCount = $this->options['prefetch_count'] ?? 1;
        $global        = $this->options['global_qos'] ?? false;

        $this->channel->basic_qos(
            $prefetchSize,
            $prefetchCount,
            $global
        );
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

                        Log::info('收到消息 - 队列: {queue}, 消息ID: {message_id}, 任务: {job}', [
                            'queue'      => $this->options['queue'],
                            'message_id' => Arr::get($body, 'id', '未知'),
                            'job'        => Arr::get($body, 'job', '未知')
                        ]);

                        // 调用回调函数处理消息
                        $result = call_user_func($callback, $body, $message);

                        // 如果不是自动确认，且回调返回true，则手动确认
                        if (!$noAck && $result === true) {
                            $message->ack();

                            Log::info('消息处理成功，已确认 - 消息ID: {message_id}', [
                                'message_id' => Arr::get($body, 'id', '未知')
                            ]);
                        }
                    } catch (\Exception $e) {
                        Log::error('消息处理异常: {error}', [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);

                        // 如果不是自动确认，则拒绝消息并重新入队
                        if (!$noAck) {
                            $message->reject(true);

                            Log::info('消息处理失败，已拒绝并重新入队 - 消息ID: {message_id}', [
                                'message_id' => Arr::get($body, 'id', '未知')
                            ]);
                        }
                    }
                }
            );

            Log::info('开始消费消息 - 队列: {queue}, 消费者标签: {consumer_tag}', [
                'queue'        => $this->options['queue'],
                'consumer_tag' => $this->options['consumer_tag']
            ]);

            // 持续等待消息
            while ($this->running && $this->channel->is_consuming()) {
                $this->channel->wait();
            }
        } catch (\Exception $e) {
            Log::error('消费消息异常: {error},{trace}', [
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
                Log::info('已停止消费 - 消费者标签: {consumer_tag}', [
                    'consumer_tag' => $this->options['consumer_tag']
                ]);
            }
        } catch (\Exception $e) {
            Log::error('停止消费异常: {error}', [
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
            Log::error('关闭RabbitMQ连接异常: {error}', [
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
