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
        $this->options = $options;
        $this->default = $options['queue'] ?? 'default';
        $this->exchange = $options['exchange'] ?? 'default';
        $this->exchangeType = $options['exchange_type'] ?? 'direct';
        $this->delayedExchange = $options['delayed_exchange'] ?? 'delayed';
        $this->connectionName = $options['connection_name'] ?? 'rabbitmq';

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
                $this->exchange,
                $this->exchangeType,
                $this->options['passive'] ?? false,
                $this->options['durable'] ?? true,
                $this->options['auto_delete'] ?? false
            );

            // 声明延迟交换机
            $this->channel->exchange_declare(
                $this->delayedExchange,
                $this->exchangeType,
                $this->options['passive'] ?? false,
                $this->options['durable'] ?? true,
                $this->options['auto_delete'] ?? false
            );

            // 声明默认队列
            $this->declareQueue($this->default);

            // 设置QOS
            $prefetchSize = $this->options['prefetch_size'] ?? 0;
            $prefetchCount = $this->options['prefetch_count'] ?? 1;
            $global = $this->options['global_qos'] ?? false;
            $this->channel->basic_qos($prefetchSize, $prefetchCount, $global);

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
        // 声明队列
        $this->channel->queue_declare(
            $queue,
            $this->options['passive'] ?? false,
            $this->options['durable'] ?? true,
            $this->options['exclusive'] ?? false,
            $this->options['auto_delete'] ?? false,
            false,
            new AMQPTable($this->options['queue_arguments'] ?? [])
        );

        // 绑定队列到交换机
        $this->channel->queue_bind($queue, $this->exchange, $queue);

        // 声明延迟队列
        $delayedQueue = $queue . '_delayed';
        $arguments = new AMQPTable([
            'x-dead-letter-exchange' => $this->exchange,
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
                    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                    'content_type' => 'application/json',
                    'content_encoding' => 'utf-8',
                ] + ($options['properties'] ?? [])
            );

            // 发布消息
            $this->channel->basic_publish($message, $this->exchange, $queue);

            // 返回任务ID
            $payloadArray = json_decode($payload, true);
            return $payloadArray['id'] ?? null;
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
        try {
            $queue = $this->getQueue($queue);
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
                    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                    'content_type' => 'application/json',
                    'content_encoding' => 'utf-8',
                    'expiration' => $delay * 1000, // 转换为毫秒
                ]
            );

            // 发布消息到延迟队列
            $this->channel->basic_publish($message, $this->delayedExchange, $delayedQueue);

            Log::info('延迟消息已推送到队列: ' . $delayedQueue . ', 延迟: ' . $delay . '秒');

            // 返回任务ID
            return $payloadArray['id'] ?? null;
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
            $payload = $job->getRawBody();
            $payloadArray = json_decode($payload, true);
            $payloadArray['attempts'] = ($payloadArray['attempts'] ?? 0) + 1;
            $payload = json_encode($payloadArray);

            $delayedQueue = $queue . '_delayed';

            $message = new AMQPMessage(
                $payload,
                [
                    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                    'content_type' => 'application/json',
                    'content_encoding' => 'utf-8',
                    'expiration' => $delay * 1000, // 转换为毫秒
                ]
            );

            $this->channel->basic_publish($message, $this->delayedExchange, $delayedQueue);

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
            'id' => $this->getRandomId(),
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
