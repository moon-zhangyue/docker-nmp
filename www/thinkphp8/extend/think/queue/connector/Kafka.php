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
use think\queue\metrics\PrometheusCollector;
use think\queue\idempotent\RedisIdempotent;
use think\queue\deadletter\DeadLetterQueue;
use think\queue\config\HotReloadManager;
use think\queue\partition\PartitionManager;

class Kafka extends Connector
{
    use InteractsWithTime; // 使用InteractsWithTime trait，提供时间相关的功能

    protected $producer; // 生产者对象，用于生产消息
    public $consumer; // 消费者对象，用于消费消息，修改访问权限为public以便于KafkaJob访问
    protected $options; // 配置选项，存储处理消息时的各种配置
    protected $container; // 容器对象，用于依赖注入
    protected $connectionName; // 连接名称，标识使用的连接配置
    protected $isShuttingDown = false; // 是否正在关闭标志
    protected $batchSize = 10; // 批量处理大小
    protected $metricsCollector; // 指标收集器
    protected $idempotent; // 幂等性检查工具
    protected $deadLetterQueue; // 死信队列处理器
    protected $configManager; // 配置热加载管理器
    protected $partitionManager; // 分区管理器
    protected $lastRebalanceCheck = 0; // 上次分区重平衡检查时间
    protected $processedMessageIds = []; // 已处理消息ID列表，用于幂等性检查

    public function __construct(array $options) // 构造函数，接收配置选项数组
    {
        // 设置传入的配置选项
        $this->options = $options;
        Log::info('Kafka Queue Connector Initialized' . json_encode($options, JSON_UNESCAPED_UNICODE));

        // 获取容器实例，用于依赖注入和管理不同服务或对象
        $this->container = Container::getInstance();

        // 从配置选项中提取连接名称，如果没有提供则默认为null
        $this->connectionName = $options['connection'] ?? null;

        // 设置批量处理大小
        $this->batchSize = $options['batch_size'] ?? 10;

        // 初始化指标收集器
        $this->metricsCollector = PrometheusCollector::getInstance();

        // 初始化幂等性检查工具
        $this->idempotent = new RedisIdempotent([
            'expire_time' => $options['idempotent']['expire_time'] ?? 86400
        ]);

        // 初始化死信队列处理器
        $this->deadLetterQueue = new DeadLetterQueue([
            'expire_time' => $options['dead_letter']['expire_time'] ?? 604800,
            'alert_threshold' => $options['dead_letter']['alert_threshold'] ?? 10
        ]);

        // 初始化配置热加载管理器
        $this->configManager = HotReloadManager::getInstance();

        // 初始化分区管理器
        $this->partitionManager = new PartitionManager();

        // 初始化生产者，准备消息队列的发送功能
        $this->initProducer();

        // 初始化消费者，准备消息队列的接收功能
        $this->initConsumer();

        // 注册信号处理器，用于优雅关闭
        $this->registerSignalHandlers();
    }

    protected function initProducer() // 初始化生产者
    {
        $conf = new Conf(); // 创建配置对象

        // 从配置热加载管理器获取broker列表
        $brokers = $this->configManager->get('kafka.connections.kafka.brokers', $this->options['brokers']);
        $conf->set('metadata.broker.list', $brokers); // 设置broker列表

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
        // 从配置热加载管理器获取broker列表
        $brokers = $this->configManager->get('kafka.connections.kafka.brokers', $this->options['brokers']);
        // 设置broker列表
        $conf->set('metadata.broker.list', $brokers);

        // 从consumer配置组中获取配置
        $consumerConfig = $this->options['consumer'] ?? [];

        // 设置消费者组ID
        $conf->set('group.id', $consumerConfig['group.id'] ?? $this->options['group.id'] ?? 'thinkphp_consumer_group');
        // 设置偏移量重置策略
        $conf->set('auto.offset.reset', $consumerConfig['auto.offset.reset'] ?? $this->options['auto.offset.reset'] ?? 'earliest');
        // 设置是否自动提交
        $conf->set('enable.auto.commit', ($consumerConfig['enable.auto.commit'] ?? $this->options['enable.auto.commit'] ?? true) ? 'true' : 'false');
        // 设置自动提交间隔
        $conf->set('auto.commit.interval.ms', (string)($consumerConfig['auto.commit.interval.ms'] ?? $this->options['auto.commit.interval.ms'] ?? '1000'));
        // 设置会话超时时间
        $conf->set('session.timeout.ms', (string)($consumerConfig['session.timeout.ms'] ?? $this->options['session.timeout.ms'] ?? '30000'));
        // 设置心跳间隔
        $conf->set('heartbeat.interval.ms', (string)($this->options['heartbeat.interval.ms'] ?? '3000'));
        // 设置最大轮询间隔
        $conf->set('max.poll.interval.ms', (string)($this->options['max.poll.interval.ms'] ?? '300000'));
        // 设置套接字超时时间
        $conf->set('socket.timeout.ms', (string)($this->options['socket.timeout.ms'] ?? '30000'));

        // 设置客户端ID
        if (isset($this->options['client.id'])) {
            $conf->set('client.id', $this->options['client.id']);
        }

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
        // 创建payload
        $payload = $this->createPayload($job, $data);

        // 解析payload为数组
        $payloadArray = json_decode($payload, true);

        // 添加唯一消息ID用于幂等性处理
        if (!isset($payloadArray['message_id'])) {
            $payloadArray['message_id'] = uniqid('msg_', true);
        }

        // 重新编码payload
        $payload = json_encode($payloadArray);

        return $this->pushRaw($payload, $queue);
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
            // 如果正在关闭，则返回null
            if ($this->isShuttingDown) {
                Log::info('Consumer is shutting down, not consuming more messages');
                return null;
            }

            // 确定要消费的队列名称
            $queueName = $queue ?: $this->options['topic'];

            // 检查是否是延迟队列
            $isDelayedQueue = strpos($queueName, '_delayed') !== false;

            // 订阅指定的队列或主题
            $this->consumer->subscribe([$queueName]);

            // 从订阅的队列或主题中消费消息
            // consume方法的参数是超时时间，单位为毫秒
            // 这里设置为1秒（1000毫秒），减少等待时间以便于优雅关闭
            $message = $this->consumer->consume(1000);

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

            // 检查消息ID是否已处理（使用Redis进行幂等性检查）
            $payload = json_decode($message->payload, true);
            if (isset($payload['message_id']) && $this->idempotent->isProcessed($payload['message_id'], $queueName)) {
                Log::info('Skipping already processed message', ['message_id' => $payload['message_id']]);
                $this->consumer->commit($message);
                return null;
            }

            // 检查是否需要重新平衡分区
            if ($this->partitionManager->needRebalance($queueName, $this->lastRebalanceCheck)) {
                $this->lastRebalanceCheck = time();
                $consumerId = $this->options['client.id'] ?? gethostname();
                $partitions = $this->partitionManager->getConsumerPartitions($queueName, $consumerId);

                Log::info('Rebalancing partitions', [
                    'topic' => $queueName,
                    'consumer_id' => $consumerId,
                    'partitions' => $partitions
                ]);

                // 重新订阅指定分区
                $this->consumer->unsubscribe();
                $topicPartitions = [];
                foreach ($partitions as $partition) {
                    $topicPartitions[] = new \RdKafka\TopicPartition($queueName, $partition);
                }
                $this->consumer->assign($topicPartitions);
            }

            // 如果是延迟队列，检查消息是否已经到达可执行时间
            if ($isDelayedQueue) {
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
            Log::error('Kafka pop error: ' . $e->getMessage()); // 记录错误日志
            $this->consumer->unsubscribe(); // 取消订阅

            // 记录失败指标
            if (isset($this->options['topic'])) {
                $this->metricsCollector->recordFailure($this->connectionName ?? 'kafka', $this->options['topic'], 0);
            }

            return null;
        }
    }

    /**
     * 批量处理消息
     * 
     * @param string|null $queue 队列名称
     * @param callable $callback 回调函数，用于处理消息
     * @param int $count 批量处理数量
     * @param int $timeout 超时时间（秒）
     * @return int 成功处理的消息数量
     */
    public function batch(callable $callback, $queue = null, $count = 0, $timeout = 60)
    {
        $processed = 0;
        $startTime = time();
        $batchSize = $count > 0 ? $count : $this->batchSize;
        $messages = [];

        try {
            // 确定要消费的队列名称
            $queueName = $queue ?: $this->options['topic'];

            // 订阅指定的队列或主题
            $this->consumer->subscribe([$queueName]);

            Log::info("Starting batch processing", ['queue' => $queueName, 'batch_size' => $batchSize]);

            // 收集消息直到达到批量大小或超时
            while ($processed < $batchSize && (time() - $startTime) < $timeout && !$this->isShuttingDown) {
                // 从订阅的队列或主题中消费消息，使用较短的超时时间以便于检查关闭状态
                $message = $this->consumer->consume(1000);

                if ($message === null) {
                    continue;
                }

                if ($message->err) {
                    if (
                        $message->err === RD_KAFKA_RESP_ERR__PARTITION_EOF ||
                        $message->err === RD_KAFKA_RESP_ERR__TIMED_OUT
                    ) {
                        continue;
                    }
                    throw new Exception($message->errstr());
                }

                // 检查消息ID是否已处理（幂等性检查）
                $payload = json_decode($message->payload, true);
                if (isset($payload['message_id']) && in_array($payload['message_id'], $this->processedMessageIds)) {
                    Log::info('Skipping already processed message', ['message_id' => $payload['message_id']]);
                    $this->consumer->commit($message);
                    continue;
                }

                // 将消息添加到批处理数组
                $messages[] = $message;
                $processed++;

                // 如果达到批量大小，处理这批消息
                if (count($messages) >= $batchSize) {
                    break;
                }
            }

            // 处理收集到的消息
            if (!empty($messages)) {
                Log::info("Processing batch of messages", ['count' => count($messages)]);

                // 调用回调函数处理消息
                call_user_func($callback, $messages);

                // 提交所有消息的偏移量
                foreach ($messages as $message) {
                    $this->consumer->commit($message);

                    // 记录成功指标
                    $this->metricsCollector->recordSuccess($this->connectionName ?? 'kafka', $queueName, 0);

                    // 保存消息ID到已处理列表
                    $payload = json_decode($message->payload, true);
                    if (isset($payload['message_id'])) {
                        $this->updateProcessedMessageIds($payload['message_id']);
                    }
                }
            }

            return $processed;
        } catch (\Exception $e) {
            Log::error('Kafka batch processing error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'processed' => $processed
            ]);

            // 记录失败指标
            $queueName = $queue ?: $this->options['topic'];
            $this->metricsCollector->recordFailure($this->connectionName ?? 'kafka', $queueName, 0);

            return $processed;
        }
    }

    /**
     * 更新已处理消息ID列表
     * 
     * @param string $messageId 消息ID
     * @param int $maxSize 最大保存的ID数量
     * @return void
     */
    public function updateProcessedMessageIds($messageId, $maxSize = 1000)
    {
        // 添加消息ID到已处理列表
        $this->processedMessageIds[] = $messageId;

        // 如果列表超过最大大小，移除最旧的ID
        if (count($this->processedMessageIds) > $maxSize) {
            array_shift($this->processedMessageIds);
        }
    }

    /**
     * 更新监控指标
     * 
     * @param string $status 状态（success或failed）
     * @return void
     */
    public function updateMetrics($status)
    {
        $connection = $this->connectionName ?? 'kafka';
        $queue = $this->options['topic'] ?? 'default';

        if ($status === 'success') {
            $this->metricsCollector->recordSuccess($connection, $queue, 0);
        } else {
            $this->metricsCollector->recordFailure($connection, $queue, 0);
        }
    }

    /**
     * 注册信号处理器，用于优雅关闭
     */
    protected function registerSignalHandlers()
    {
        // 在Windows环境下，pcntl扩展可能不可用，需要进行检查
        if (function_exists('pcntl_signal')) {
            // 注册SIGTERM信号处理器
            pcntl_signal(SIGTERM, function () {
                $this->shutdown();
            });

            // 注册SIGINT信号处理器
            pcntl_signal(SIGINT, function () {
                $this->shutdown();
            });

            // 注册SIGHUP信号处理器
            pcntl_signal(SIGHUP, function () {
                $this->shutdown();
            });

            Log::info('Signal handlers registered for graceful shutdown');
        } else {
            Log::info('pcntl extension not available, signal handlers not registered');
        }
    }

    /**
     * 优雅关闭
     */
    public function shutdown()
    {
        if ($this->isShuttingDown) {
            return;
        }

        $this->isShuttingDown = true;
        Log::info('Shutting down Kafka consumer gracefully...');

        // 取消订阅
        if ($this->consumer) {
            try {
                $this->consumer->unsubscribe();
                Log::info('Kafka consumer unsubscribed successfully');
            } catch (\Exception $e) {
                Log::error('Error unsubscribing Kafka consumer: ' . $e->getMessage());
            }
        }

        Log::info('Kafka consumer shutdown complete');
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

    /**
     * 处理消息成功
     * 
     * @param string $messageId 消息ID
     * @param string $queue 队列名称
     * @param float $processingTime 处理时间（秒）
     * @return void
     */
    public function markMessageAsProcessed(string $messageId, string $queue, float $processingTime = 0.0): void
    {
        // 使用Redis进行幂等性检查，标记消息为已处理
        $this->idempotent->markAsProcessed($messageId, $queue, [
            'processed_at' => time(),
            'processing_time' => $processingTime
        ]);

        // 更新监控指标
        $this->metricsCollector->recordSuccess($this->connectionName ?? 'kafka', $queue, $processingTime);

        Log::info('Message processed successfully', [
            'message_id' => $messageId,
            'queue' => $queue,
            'processing_time' => round($processingTime, 4)
        ]);
    }

    /**
     * 处理消息失败
     * 
     * @param string $messageId 消息ID
     * @param string $queue 队列名称
     * @param array $payload 消息内容
     * @param string $error 错误信息
     * @param float $processingTime 处理时间（秒）
     * @return void
     */
    public function markMessageAsFailed(string $messageId, string $queue, array $payload, string $error, float $processingTime = 0.0): void
    {
        // 添加到死信队列
        $this->deadLetterQueue->add($messageId, $queue, $payload, $error);

        // 更新监控指标
        $this->metricsCollector->recordFailure($this->connectionName ?? 'kafka', $queue, $processingTime);

        Log::error('Message processing failed: message_id={message_id}, queue={queue}, error={error}, processing_time={processing_time}', [
            'message_id' => $messageId,
            'queue' => $queue,
            'error' => $error,
            'processing_time' => round($processingTime, 4)
        ]);
    }
}
