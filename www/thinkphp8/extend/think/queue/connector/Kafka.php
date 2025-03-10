<?php

declare(strict_types=1);

namespace think\queue\connector;

use think\queue\Connector;
use think\queue\InteractsWithTime;
use think\queue\KafkaJob;
use RdKafka\Producer;
use RdKafka\Conf;
use Exception;
use think\Container;
use think\facade\Log;

class Kafka extends Connector
{
    use InteractsWithTime; // 使用InteractsWithTime trait，提供时间相关的功能

    protected $producer; // 生产者对象，用于生产消息
    public $consumer; // 消费者对象，用于消费消息，修改访问权限为public以便于KafkaJob访问
    protected $options; // 配置选项，存储处理消息时的各种配置
    protected $container; // 容器对象，用于依赖注入
    protected $connectionName; // 连接名称，标识使用的连接配置

    public function __construct(array $options) // 构造函数，接收配置选项数组
    {
        // 设置传入的配置选项
        $this->options = $options;
        Log::info('Kafka Queue Connector Initialized' . json_encode($options, JSON_UNESCAPED_UNICODE));

        // 获取容器实例，用于依赖注入和管理不同服务或对象
        $this->container = Container::getInstance();

        // 从配置选项中提取连接名称，如果没有提供则默认为null
        $this->connectionName = $options['connection'] ?? null;

        // 初始化生产者，准备消息队列的发送功能
        $this->initProducer();

        // 初始化消费者，准备消息队列的接收功能
        $this->initConsumer();
    }

    protected function initProducer() // 初始化生产者
    {
        $conf = new Conf(); // 创建配置对象

        $conf->set('metadata.broker.list', $this->options['brokers']); // 设置broker列表

        if (isset($this->options['debug']) && $this->options['debug']) {
            $conf->set('debug', 'all'); // 如果配置中启用了调试模式，则设置调试选项
        }
        Log::info('Kafka producer init success');
        // 使用配置好的 $conf 对象创建一个新的 Kafka 生产者实例，并赋值给 $this->producer
        $this->producer = new Producer($conf);
    }

    /**
     * 初始化消息消费者
     *
     * 该方法配置并创建一个消息消费者实例 它首先创建一个配置对象，并设置基本的配置参数，
     * 如broker列表、消费者组ID和偏移量重置策略 如果设置了调试模式，则相应地配置调试选项
     */
    protected function initConsumer() // 初始化消费者
    {
        // 创建配置对象
        $conf = new Conf();
        // 设置broker列表
        $conf->set('metadata.broker.list', $this->options['brokers']);
        // 设置消费者组ID
        $conf->set('group.id', $this->options['group_id']);
        // 设置偏移量重置策略为从最早的消息开始消费
        $conf->set('auto.offset.reset', 'earliest');
        // 根据配置文件设置是否自动提交
        $conf->set('enable.auto.commit', $this->options['auto_commit'] ? 'true' : 'false');
        // 设置会话超时时间 - 减少超时时间以避免长时间阻塞
        $conf->set('session.timeout.ms', '30000');
        // 设置心跳间隔 - 保持较短的心跳间隔
        $conf->set('heartbeat.interval.ms', '3000');
        // 设置最大轮询间隔 - 减少轮询间隔以提高响应性
        $conf->set('max.poll.interval.ms', '120000');
        // 设置套接字超时时间 - 避免网络问题导致的长时间阻塞
        $conf->set('socket.timeout.ms', '30000');
        // 移除request.timeout.ms参数，因为它是生产者配置项，对消费者无效
        Log::info('Kafka consumer init success');
        // 创建消费者实例
        $this->consumer = new \RdKafka\KafkaConsumer($conf);
    }

    public function size($queue = null) // 获取队列大小
    {
        // Kafka 不支持直接获取队列大小
        return 0;
    }

    public function push($job, $data = '', $queue = null) // 推送消息到队列
    {
        return $this->pushRaw($this->createPayload($job, $data), $queue);
    }

    // 定义一个方法pushRaw，用于将原始数据推送到Kafka队列
    public function pushRaw($payload, $queue = null, array $options = []) // 推送原始数据到队列
    {
        try {
            $conf = new Conf(); // 创建配置对象

            $conf->set('metadata.broker.list', $this->options['brokers']); // 设置broker列表

            if (isset($this->options['debug']) && $this->options['debug']) {
                $conf->set('debug', 'all'); // 如果配置中启用了调试模式，则设置调试选项
            }

            // 创建一个新的Producer对象，传入配置对象
            $producer = new Producer($conf);
            // 创建一个新的Topic对象，如果传入的$queue为null，则使用当前对象的options数组中的topic
            $topic = $producer->newTopic($queue ?: $this->options['topic']);
            // 向Topic中生产消息，使用未分配的分区（RD_KAFKA_PARTITION_UA），消息的key为0，消息的内容为$payload
            $topic->produce(RD_KAFKA_PARTITION_UA, 0, $payload);
            // 刷新生产者，等待所有消息发送完成，超时时间为10000毫秒
            $producer->flush(10000);
            // 注释掉的代码：轮询生产者，等待事件发生，超时时间为0毫秒
            // $producer->poll(0);

            return true;
        } catch (Exception $e) {
            throw new Exception('Kafka push error: ' . $e->getMessage()); // 捕获异常并抛出
        }
    }

    public function later($delay, $job, $data = '', $queue = null) // 延迟推送消息
    {
        // 创建payload
        $payload = $this->createPayload($job, $data);

        // 解析payload
        $payloadArray = json_decode($payload, true);

        // 添加可执行时间
        $payloadArray['available_at'] = $this->availableAt($delay);
        $payloadArray['original_queue'] = $queue ?: $this->options['topic'];

        // 重新编码payload
        $payload = json_encode($payloadArray);

        // 将消息推送到延迟队列
        $delayQueue = ($queue ?: $this->options['topic']) . '_delayed';

        return $this->pushRaw($payload, $delayQueue);
    }

    public function pop($queue = null) // 从队列中弹出消息
    {
        try {
            // 确定要消费的队列名称
            $queueName = $queue ?: $this->options['topic'];

            // 检查是否是延迟队列
            $isDelayedQueue = strpos($queueName, '_delayed') !== false;

            // 订阅指定的队列或主题
            $this->consumer->subscribe([$queueName]);

            // 从订阅的队列或主题中消费消息
            // consume方法的参数是超时时间，单位为毫秒
            // 这里设置为30秒（30000毫秒），减少等待时间以避免长时间阻塞
            $message = $this->consumer->consume(30 * 1000);

            if ($message === null) {
                return null;
            }

            if ($message->err) {
                if (
                    // 检查消息错误类型是否为分区末尾或超时
                    $message->err === RD_KAFKA_RESP_ERR__PARTITION_EOF ||
                    $message->err === RD_KAFKA_RESP_ERR__TIMED_OUT
                ) {
                    return null;
                }
                throw new Exception($message->errstr()); // 抛出异常
            }

            // 如果是延迟队列，检查消息是否已经到达可执行时间
            if ($isDelayedQueue) {
                $payload = json_decode($message->payload, true);

                // 检查消息是否包含可执行时间和原始队列信息
                if (isset($payload['available_at']) && isset($payload['original_queue'])) {
                    // 如果当前时间小于可执行时间，说明消息还不能执行
                    if (time() < $payload['available_at']) {
                        // 将消息放回延迟队列，等待下次处理
                        $this->pushRaw($message->payload, $queueName);
                        // 提交消息偏移量，表示已经处理过这条消息
                        $this->consumer->commit($message);
                        Log::info('Delayed message not ready yet, put back to delayed queue', [
                            'queue' => $queueName,
                            'available_at' => date('Y-m-d H:i:s', $payload['available_at']),
                            'now' => date('Y-m-d H:i:s')
                        ]);
                        return null;
                    }

                    // 消息已经到达可执行时间，将其移回原始队列
                    $this->pushRaw($message->payload, $payload['original_queue']);
                    // 提交消息偏移量，表示已经处理过这条消息
                    $this->consumer->commit($message);
                    Log::info('Delayed message ready, moved to original queue', [
                        'from_queue' => $queueName,
                        'to_queue' => $payload['original_queue']
                    ]);
                    return null;
                }
            }

            Log::info('Kafka pop success!', ['topic' => $this->options['topic'], 'queue' => $queueName]);
            return $this->createJob($message); // 创建并返回KafkaJob实例
        } catch (\Exception $e) {
            $this->container->log->error('Kafka pop error: ' . $e->getMessage()); // 记录错误日志
            $this->consumer->unsubscribe(); // 取消订阅
            return null;
        }
    }
    protected function createJob($message) // 创建KafkaJob实例
    {
        // 返回一个新的 KafkaJob 实例
        return new KafkaJob(
            $this->app ?? Container::getInstance(), // 获取应用实例或容器实例
            $this,
            $message,
            $this->connectionName,
            $message->topic_name ?? $this->options['topic'] // 获取消息的主题名称或默认主题
        );
    }
}
