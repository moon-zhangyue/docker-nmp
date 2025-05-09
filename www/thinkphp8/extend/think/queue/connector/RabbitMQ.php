<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2023 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------

namespace think\queue\connector;

use Exception;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use think\Container;
use think\facade\Log;
use think\queue\Connector;
use think\queue\InteractsWithTime;
use think\queue\job\RabbitMQ as RabbitMQJob;

/**
 * RabbitMQ队列连接器
 *
 * 该类实现了ThinkPHP队列连接器接口，用于连接RabbitMQ消息队列服务
 *
 * @package think\queue\connector
 */
class RabbitMQ extends Connector
{
    use InteractsWithTime;

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
     * 容器实例
     *
     * @var \think\Container
     */
    protected $container;

    /**
     * 交换机名称
     *
     * @var string
     */
    protected $exchange;

    /**
     * 交换机类型
     *
     * @var string
     */
    protected $exchangeType;

    /**
     * 默认队列名称
     *
     * @var string
     */
    protected $default;

    /**
     * 配置选项
     *
     * @var array
     */
    protected $options = [];

    /**
     * 连接名称
     *
     * @var string
     */
    protected $connectionName;

    /**
     * 是否启用发布者确认模式
     *
     * @var bool
     */
    protected $publisherConfirms = false;

    /**
     * 发布者确认回调函数
     *
     * @var array
     */
    protected $confirmCallbacks = [];

    /**
     * 延迟队列交换机名称
     *
     * @var string
     */
    protected $delayedExchange;

    /**
     * 构造函数
     *
     * @param array $options 配置选项
     */
    public function __construct(array $options = [])
    {
        $this->options           = $options;
        $this->default           = $options['queue'] ?? 'default';
        $this->exchange          = $options['exchange'] ?? 'default';
        $this->exchangeType      = $options['exchange_type'] ?? 'direct';
        $this->delayedExchange   = $options['delayed_exchange'] ?? 'delayed';
        $this->connectionName    = $options['connection_name'] ?? 'rabbitmq';
        $this->publisherConfirms = $options['publisher_confirms'] ?? false;

        // 获取容器实例
        $this->container = Container::getInstance();

        // 初始化连接
        $this->initConnection();
    }

    /**
     * 创建RabbitMQ连接器实例
     *
     * @param array $config 配置信息
     * @return RabbitMQ
     * @throws Exception
     */
    public static function __make($config)
    {
        if (!extension_loaded('sockets')) {
            throw new Exception('sockets扩展未安装');
        }

        if (!class_exists('PhpAmqpLib\Connection\AMQPStreamConnection')) {
            throw new Exception('php-amqplib库未安装，请使用composer require php-amqplib/php-amqplib');
        }

        return new self($config);
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
                $this->options['host'] ?? 'localhost',
                $this->options['port'] ?? 5672,
                $this->options['login'] ?? 'guest',
                $this->options['password'] ?? 'guest',
                $this->options['vhost'] ?? '/'
            );

            // 创建通道
            $this->channel = $this->connection->channel();

            // 声明交换机
            $this->channel->exchange_declare(
                $this->exchange,           // 交换机名称，默认为"default"
                $this->exchangeType,       // 交换机类型，默认为"direct"
                $this->options['passive'] ?? false,  // 是否以被动模式声明
                $this->options['durable'] ?? true,   // 是否持久化
                $this->options['auto_delete'] ?? false  // 是否自动删除
            );

            // 声明延迟交换机
            $this->channel->exchange_declare(
                $this->delayedExchange,    // 延迟交换机名称，默认为"delayed"
                $this->exchangeType,       // 使用与主交换机相同的类型
                $this->options['passive'] ?? false,  // 相同的被动模式设置
                $this->options['durable'] ?? true,   // 相同的持久化设置
                $this->options['auto_delete'] ?? false  // 相同的自动删除设置
            );

            // 声明默认队列
            $this->declareQueue($this->default);

            // 设置QOS
            $prefetchSize  = $this->options['prefetch_size'] ?? 0;// $prefetchSize：预取窗口大小，限制消费者一次性能接收的消息大小总和
            $prefetchCount = $this->options['prefetch_count'] ?? 1;// $prefetchCount：预取数量，限制消费者一次性能接收但未确认的消息数量
            $global        = $this->options['global_qos'] ?? false;// $global：是否全局应用此设置，true表示对所有消费者生效，false表示仅对当前消费者生效

            // 设置消息的预取质量，以控制消费者接收消息的数量和频率
            $this->channel->basic_qos($prefetchSize, $prefetchCount, $global);

            // 如果启用了发布者确认模式，则设置通道为确认模式
            if ($this->publisherConfirms) {
                $this->channel->confirm_select();

                // 注册确认回调
                $this->channel->set_ack_handler(function ($deliveryTag, $multiple) {
                    $this->handleAck($deliveryTag, $multiple);
                });

                // 注册拒绝回调
                $this->channel->set_nack_handler(function ($deliveryTag, $multiple, $requeue) {
                    $this->handleNack($deliveryTag, $multiple, $requeue);
                });

                Log::info('RabbitMQ发布者确认模式已启用');
            }

            Log::info('RabbitMQ连接初始化成功');
        } catch (Exception $e) {
            Log::error('RabbitMQ连接初始化失败: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 声明队列
     *
     * @param string $queue 队列名称
     * @return void
     */
    protected function declareQueue($queue)
    {
        // 声明队列，确保消息可以被存储和处理
        $this->channel->queue_declare(
            $queue, // 队列名称
            $this->options['passive'] ?? false, // 是否以被动模式声明队列，false表示主动声明
            $this->options['durable'] ?? true, // 是否持久化队列，true表示是
            $this->options['exclusive'] ?? false, // 是否排他性队列，false表示不是
            $this->options['auto_delete'] ?? false, // 是否在消费完成后自动删除队列，false表示不自动删除
            false, // 是否自动声明队列，此处固定为false
            new AMQPTable($this->options['queue_arguments'] ?? []) // 队列的额外参数，通过AMQPTable实例传递
        );

        // 绑定队列到交换机
        $this->channel->queue_bind($queue, $this->exchange, $queue);

        // 声明延迟队列
        $delayedQueue = $queue . '_delayed';
        $arguments    = new AMQPTable([
            'x-dead-letter-exchange'    => $this->exchange,
            'x-dead-letter-routing-key' => $queue,
        ]);

        $this->channel->queue_declare(
            $delayedQueue,
            $this->options['passive'] ?? false,
            $this->options['durable'] ?? true,
            $this->options['exclusive'] ?? false,
            $this->options['auto_delete'] ?? false,
            false,
            $arguments
        );

        // 绑定延迟队列到延迟交换机
        $this->channel->queue_bind($delayedQueue, $this->delayedExchange, $delayedQueue);

        Log::info('队列声明成功: ' . $queue);
    }

    /**
     * 获取队列长度
     *
     * @param string|null $queue 队列名称
     * @return int
     */
    public function size($queue = null)
    {
        $queue = $this->getQueue($queue);

        try {
            // 获取队列信息
            $queueInfo = $this->channel->queue_declare(
                $queue,
                true, // passive
                $this->options['durable'] ?? true,
                $this->options['exclusive'] ?? false,
                $this->options['auto_delete'] ?? false
            );

            // 返回队列中的消息数量
            return $queueInfo[1] ?? 0;
        } catch (Exception $e) {
            Log::error('获取队列长度失败: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * 推送任务到队列
     *
     * @param mixed $job 任务
     * @param mixed $data 数据
     * @param string|null $queue 队列名称
     * @return mixed
     */
    public function push($job, $data = '', $queue = null)
    {
        return $this->pushRaw($this->createPayload($job, $data), $queue);
    }

    /**
     * 推送任务到队列（带选项）
     *
     * @param mixed $job 任务
     * @param mixed $data 数据
     * @param string|null $queue 队列名称
     * @param array $options 选项
     * @return mixed
     */
    public function pushWithOptions($job, $data = '', $queue = null, array $options = [])
    {
        return $this->pushRaw($this->createPayload($job, $data), $queue, $options);
    }

    /**
     * 推送原始数据到队列
     *
     * @param string $payload 原始数据
     * @param string|null $queue 队列名称
     * @param array $options 选项
     * @return mixed
     */
    public function pushRaw($payload, $queue = null, array $options = [])
    {
        try {
            $queue = $this->getQueue($queue);

            // 确保队列存在
            $this->declareQueueIfNotExists($queue);

            // 创建消息
            $message = new AMQPMessage(
                $payload,
                [
                    'delivery_mode'    => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                    'content_type'     => 'application/json',
                    'content_encoding' => 'utf-8',
                ] + ($options['properties'] ?? [])
            );

            // 解析payload获取任务ID
            $payloadArray = json_decode($payload, true);
            $jobId        = $payloadArray['id'] ?? null;

            // 如果启用了发布者确认模式
            if ($this->publisherConfirms) {
                // 获取下一个投递标签
                $deliveryTag = $this->channel->getNextPublishSeqNo();

                // 注册确认回调
                $this->registerConfirmCallback(
                    $deliveryTag,
                    // 确认回调
                    function ($tag) use ($jobId, $queue) {
                        Log::info('消息发布已确认 - 队列: {queue}, 任务ID: {job_id}, 标签: {tag}', [
                            'queue'  => $queue,
                            'job_id' => $jobId,
                            'tag'    => $tag
                        ]);
                    },
                    // 拒绝回调
                    function ($tag, $requeue) use ($jobId, $queue) {
                        Log::warning('消息发布已拒绝 - 队列: {queue}, 任务ID: {job_id}, 标签: {tag}, 重新入队: {requeue}', [
                            'queue'   => $queue,
                            'job_id'  => $jobId,
                            'tag'     => $tag,
                            'requeue' => $requeue ? 'true' : 'false'
                        ]);
                    }
                );
            }

            // 发布消息
            $this->channel->basic_publish($message, $this->exchange, $queue);

            // 如果启用了发布者确认模式且配置了等待确认
            if ($this->publisherConfirms && ($options['wait_for_confirm'] ?? false)) {
                $timeout   = $options['confirm_timeout'] ?? 5.0; // 默认等待5秒
                $confirmed = $this->waitForConfirms($timeout);

                if (!$confirmed) {
                    Log::warning('消息发布确认超时 - 队列: {queue}, 任务ID: {job_id}', [
                        'queue'  => $queue,
                        'job_id' => $jobId
                    ]);
                }
            }

            // 返回任务ID
            return $jobId;
        } catch (Exception $e) {
            Log::error('推送消息到队列失败: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 延迟推送任务到队列
     *
     * @param int $delay 延迟时间（秒）
     * @param mixed $job 任务
     * @param mixed $data 数据
     * @param string|null $queue 队列名称
     * @return mixed
     */
    public function later($delay, $job, $data = '', $queue = null)
    {
        return $this->laterWithOptions($delay, $job, $data, $queue);
    }

    /**
     * 延迟推送任务到队列（带选项）
     *
     * @param int $delay 延迟时间（秒）
     * @param mixed $job 任务
     * @param mixed $data 数据
     * @param string|null $queue 队列名称
     * @param array $options 选项
     * @return mixed
     */
    public function laterWithOptions($delay, $job, $data = '', $queue = null, array $options = [])
    {
        try {
            $queue        = $this->getQueue($queue);
            $delayedQueue = $queue . '_delayed';

            // 确保延迟队列存在
            $this->declareQueueIfNotExists($queue);

            // 创建payload
            $payload = $this->createPayload($job, $data);

            // 解析payload
            $payloadArray = json_decode($payload, true);

            // 添加可执行时间
            $payloadArray['available_at'] = $this->availableAt($delay);

            // 重新编码payload
            $payload = json_encode($payloadArray);

            // 创建消息
            $message = new AMQPMessage(
                $payload,
                [
                    'delivery_mode'    => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                    'content_type'     => 'application/json',
                    'content_encoding' => 'utf-8',
                    'expiration'       => $delay * 1000, // 转换为毫秒
                ]
            );

            // 获取任务ID
            $jobId = $payloadArray['id'] ?? null;

            // 如果启用了发布者确认模式
            if ($this->publisherConfirms) {
                // 获取下一个投递标签
                $deliveryTag = $this->channel->getNextPublishSeqNo();

                // 注册确认回调
                $this->registerConfirmCallback(
                    $deliveryTag,
                    // 确认回调
                    function ($tag) use ($jobId, $delayedQueue, $delay) {
                        Log::info('延迟消息发布已确认 - 队列: {queue}, 任务ID: {job_id}, 延迟: {delay}秒, 标签: {tag}', [
                            'queue'  => $delayedQueue,
                            'job_id' => $jobId,
                            'delay'  => $delay,
                            'tag'    => $tag
                        ]);
                    },
                    // 拒绝回调
                    function ($tag, $requeue) use ($jobId, $delayedQueue, $delay) {
                        Log::warning('延迟消息发布已拒绝 - 队列: {queue}, 任务ID: {job_id}, 延迟: {delay}秒, 标签: {tag}, 重新入队: {requeue}', [
                            'queue'   => $delayedQueue,
                            'job_id'  => $jobId,
                            'delay'   => $delay,
                            'tag'     => $tag,
                            'requeue' => $requeue ? 'true' : 'false'
                        ]);
                    }
                );
            }

            // 发布消息到延迟队列
            $this->channel->basic_publish($message, $this->delayedExchange, $delayedQueue);

            // 如果启用了发布者确认模式且配置了等待确认
            if ($this->publisherConfirms && ($options['wait_for_confirm'] ?? false)) {
                $timeout   = $options['confirm_timeout'] ?? 5.0; // 默认等待5秒
                $confirmed = $this->waitForConfirms($timeout);

                if (!$confirmed) {
                    Log::warning('延迟消息发布确认超时 - 队列: {queue}, 任务ID: {job_id}, 延迟: {delay}秒', [
                        'queue'  => $delayedQueue,
                        'job_id' => $jobId,
                        'delay'  => $delay
                    ]);
                }
            }

            Log::info('延迟消息已推送到队列: ' . $delayedQueue . ', 延迟: ' . $delay . '秒');

            // 返回任务ID
            return $jobId;
        } catch (Exception $e) {
            Log::error('延迟推送消息到队列失败: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 从队列中获取下一个任务
     *
     * @param string|null $queue 队列名称
     * @return RabbitMQJob|null
     */
    public function pop($queue = null)
    {
        $queue = $this->getQueue($queue);

        try {
            // 确保队列存在
            $this->declareQueueIfNotExists($queue);

            // 获取消息
            $message = $this->channel->basic_get($queue, false);

            if ($message instanceof AMQPMessage) {
                return new RabbitMQJob(
                    $this->container,
                    $this,
                    $this->channel,
                    $message,
                    $this->connectionName,
                    $queue
                );
            }
        } catch (Exception $e) {
            Log::error('从队列获取消息失败: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * 确认消息已处理
     *
     * @param string $queue 队列名称
     * @param RabbitMQJob $job 任务
     * @return void
     */
    public function ack($queue, $job)
    {
        $this->channel->basic_ack($job->getMessage()->getDeliveryTag());
    }

    /**
     * 将消息重新放回队列
     *
     * @param string $queue 队列名称
     * @param RabbitMQJob $job 任务
     * @param int $delay 延迟时间（秒）
     * @return void
     */
    public function release($queue, $job, $delay = 0)
    {
        // 拒绝当前消息
        $this->channel->basic_reject($job->getMessage()->getDeliveryTag(), false);

        // 如果需要延迟，则重新发布到延迟队列
        if ($delay > 0) {
            $payload                  = $job->getRawBody();
            $payloadArray             = json_decode($payload, true);
            $payloadArray['attempts'] = ($payloadArray['attempts'] ?? 0) + 1;
            $payload                  = json_encode($payloadArray);

            $delayedQueue = $queue . '_delayed';

            $message = new AMQPMessage(
                $payload,
                [
                    'delivery_mode'    => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                    'content_type'     => 'application/json',
                    'content_encoding' => 'utf-8',
                    'expiration'       => $delay * 1000, // 转换为毫秒
                ]
            );

            // 获取任务ID
            $jobId = $payloadArray['id'] ?? null;

            // 如果启用了发布者确认模式
            if ($this->publisherConfirms) {
                // 获取下一个投递标签
                $deliveryTag = $this->channel->getNextPublishSeqNo();

                // 注册确认回调
                $this->registerConfirmCallback(
                    $deliveryTag,
                    // 确认回调
                    function ($tag) use ($jobId, $delayedQueue, $delay) {
                        Log::info('重新发布消息已确认 - 队列: {queue}, 任务ID: {job_id}, 延迟: {delay}秒, 标签: {tag}', [
                            'queue'  => $delayedQueue,
                            'job_id' => $jobId,
                            'delay'  => $delay,
                            'tag'    => $tag
                        ]);
                    },
                    // 拒绝回调
                    function ($tag, $requeue) use ($jobId, $delayedQueue, $delay) {
                        Log::warning('重新发布消息已拒绝 - 队列: {queue}, 任务ID: {job_id}, 延迟: {delay}秒, 标签: {tag}, 重新入队: {requeue}', [
                            'queue'   => $delayedQueue,
                            'job_id'  => $jobId,
                            'delay'   => $delay,
                            'tag'     => $tag,
                            'requeue' => $requeue ? 'true' : 'false'
                        ]);
                    }
                );
            }

            $this->channel->basic_publish($message, $this->delayedExchange, $delayedQueue);

            // 如果启用了发布者确认模式，等待确认
            if ($this->publisherConfirms) {
                $timeout   = 5.0; // 默认等待5秒
                $confirmed = $this->waitForConfirms($timeout);

                if (!$confirmed) {
                    Log::warning('重新发布消息确认超时 - 队列: {queue}, 任务ID: {job_id}, 延迟: {delay}秒', [
                        'queue'  => $delayedQueue,
                        'job_id' => $jobId,
                        'delay'  => $delay
                    ]);
                }
            }

            Log::info('消息已重新发布到延迟队列: ' . $delayedQueue . ', 延迟: ' . $delay . '秒');
        }
    }

    /**
     * 确保队列存在
     *
     * @param string $queue 队列名称
     * @return void
     */
    protected function declareQueueIfNotExists($queue)
    {
        try {
            // 被动检查队列是否存在
            $this->channel->queue_declare($queue, true);
        } catch (Exception $e) {
            // 队列不存在，声明队列
            $this->declareQueue($queue);
        }
    }

    /**
     * 获取队列名称
     *
     * @param string|null $queue 队列名称
     * @return string
     */
    protected function getQueue($queue)
    {
        return $queue ?: $this->default;
    }

    /**
     * 创建任务数据数组
     *
     * @param mixed $job 任务
     * @param mixed $data 数据
     * @return array
     */
    protected function createPayloadArray($job, $data = '')
    {
        return array_merge(parent::createPayloadArray($job, $data), [
            'id'       => $this->getRandomId(),
            'attempts' => 0,
        ]);
    }

    /**
     * 生成随机ID
     *
     * @return string
     */
    protected function getRandomId()
    {
        return md5(uniqid('', true));
    }

    /**
     * 处理消息确认回调
     *
     * @param int $deliveryTag 投递标签
     * @param bool $multiple 是否批量确认
     * @return void
     */
    protected function handleAck($deliveryTag, $multiple)
    {
        if ($multiple) {
            // 批量确认所有小于等于当前deliveryTag的消息
            foreach ($this->confirmCallbacks as $tag => $callback) {
                if ($tag <= $deliveryTag) {
                    // 调用成功回调
                    if (is_callable($callback['ack'])) {
                        call_user_func($callback['ack'], $tag);
                    }

                    // 记录日志
                    Log::info('RabbitMQ消息已确认 - 标签: {tag}', [
                        'tag' => $tag
                    ]);

                    // 移除已确认的回调
                    unset($this->confirmCallbacks[$tag]);
                }
            }
        } elseif (isset($this->confirmCallbacks[$deliveryTag])) {
            // 单个确认
            if (is_callable($this->confirmCallbacks[$deliveryTag]['ack'])) {
                call_user_func($this->confirmCallbacks[$deliveryTag]['ack'], $deliveryTag);
            }

            // 记录日志
            Log::info('RabbitMQ消息已确认 - 标签: {tag}', [
                'tag' => $deliveryTag
            ]);

            // 移除已确认的回调
            unset($this->confirmCallbacks[$deliveryTag]);
        }
    }

    /**
     * 处理消息拒绝回调
     *
     * @param int $deliveryTag 投递标签
     * @param bool $multiple 是否批量拒绝
     * @param bool $requeue 是否重新入队
     * @return void
     */
    protected function handleNack($deliveryTag, $multiple, $requeue)
    {
        if ($multiple) {
            // 批量处理所有小于等于当前deliveryTag的消息
            foreach ($this->confirmCallbacks as $tag => $callback) {
                if ($tag <= $deliveryTag) {
                    // 调用失败回调
                    if (is_callable($callback['nack'])) {
                        call_user_func($callback['nack'], $tag, $requeue);
                    }

                    // 记录日志
                    Log::warning('RabbitMQ消息已拒绝 - 标签: {tag}, 重新入队: {requeue}', [
                        'tag'     => $tag,
                        'requeue' => $requeue ? 'true' : 'false'
                    ]);

                    // 移除已拒绝的回调
                    unset($this->confirmCallbacks[$tag]);
                }
            }
        } elseif (isset($this->confirmCallbacks[$deliveryTag])) {
            // 单个拒绝
            if (is_callable($this->confirmCallbacks[$deliveryTag]['nack'])) {
                call_user_func($this->confirmCallbacks[$deliveryTag]['nack'], $deliveryTag, $requeue);
            }

            // 记录日志
            Log::warning('RabbitMQ消息已拒绝 - 标签: {tag}, 重新入队: {requeue}', [
                'tag'     => $deliveryTag,
                'requeue' => $requeue ? 'true' : 'false'
            ]);

            // 移除已拒绝的回调
            unset($this->confirmCallbacks[$deliveryTag]);
        }
    }

    /**
     * 注册发布者确认回调
     *
     * @param int $deliveryTag 投递标签
     * @param callable|null $ackCallback 确认回调
     * @param callable|null $nackCallback 拒绝回调
     * @return void
     */
    public function registerConfirmCallback($deliveryTag, $ackCallback = null, $nackCallback = null)
    {
        $this->confirmCallbacks[$deliveryTag] = [
            'ack'  => $ackCallback,
            'nack' => $nackCallback
        ];
    }

    /**
     * 等待发布者确认
     *
     * @param float $timeout 超时时间（秒）
     * @return bool 是否所有消息都已确认
     */
    public function waitForConfirms($timeout = 0.0)
    {
        if (!$this->publisherConfirms) {
            return true;
        }

        try {
            // 等待所有未确认的消息得到确认
            $this->channel->wait_for_pending_acks_returns($timeout);

            // 如果还有未确认的消息，返回false
            return empty($this->confirmCallbacks);
        } catch (Exception $e) {
            Log::error('等待发布者确认失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 关闭连接
     *
     * @return void
     */
    public function close()
    {
        if ($this->channel) {
            $this->channel->close();
        }

        if ($this->connection) {
            $this->connection->close();
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
