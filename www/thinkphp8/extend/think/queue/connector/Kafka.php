<?php

declare(strict_types=1); // 严格类型声明

namespace think\queue\connector; // 命名空间声明

use think\queue\Connector; // 引入Connector类
use think\queue\InteractsWithTime; // 引入InteractsWithTime trait
use think\queue\KafkaJob; // 引入KafkaJob类
use RdKafka\Producer; // 引入RdKafka\Producer类
use RdKafka\Conf; // 引入RdKafka\Conf类
use Exception; // 引入Exception类
use think\Container; // 引入Container类
use think\facade\Log; // 引入Log门面
use think\queue\metrics\PrometheusCollector; // 引入PrometheusCollector类
use think\queue\idempotent\RedisIdempotent; // 引入RedisIdempotent类
use think\queue\deadletter\DeadLetterQueue; // 引入DeadLetterQueue类
use think\queue\config\HotReloadManager; // 引入HotReloadManager类
use think\queue\partition\PartitionManager; // 引入PartitionManager类
use think\queue\transaction\KafkaTransaction; // 引入KafkaTransaction类
use think\queue\error\SentryReporter; // 引入SentryReporter类
use think\queue\health\HealthCheck; // 引入HealthCheck类
use think\queue\config\ConfigValidator; // 引入ConfigValidator类

class Kafka extends Connector // Kafka类继承自Connector
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
    protected $transaction; // 事务管理器
    protected $sentryReporter; // Sentry错误报告器
    protected $healthCheck; // 健康检查器
    protected $configValidator; // 配置验证器
    protected $consumerId; // 消费者唯一标识
    protected $brokers; // 新增的brokers属性

    public function __construct(array $options) // 构造函数，接收配置选项数组
    {
        // 验证配置的合法性
        $this->configValidator = ConfigValidator::getInstance();
        $validationResult = $this->configValidator->validate('kafka', $options);

        if (!$validationResult['valid']) {
            $errorMessage = 'Kafka queue configuration validation failed: ' . json_encode($validationResult['errors']);
            Log::error($errorMessage);
            throw new \InvalidArgumentException($errorMessage);
        }

        // 设置传入的配置选项
        $this->options = $options;
        Log::info('Kafka Queue Connector Initialized' . json_encode($options, JSON_UNESCAPED_UNICODE));

        // 生成消费者唯一ID
        $this->consumerId = 'consumer-' . gethostname() . '-' . getmypid() . '-' . uniqid();

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

        // 初始化事务管理器
        $this->transaction = new KafkaTransaction($options);

        // 初始化Sentry错误报告器
        $this->sentryReporter = SentryReporter::getInstance();

        // 初始化健康检查器
        $this->healthCheck = HealthCheck::getInstance();

        // 事务配置已在transaction.enabled中定义，无需额外映射

        // 初始化生产者，准备消息队列的发送功能
        $this->initProducer();

        // 初始化消费者，准备消息队列的接收功能
        $this->initConsumer();

        // 注册信号处理器，用于优雅关闭
        $this->registerSignalHandlers();

        // 注册消费者健康状态
        $this->registerConsumerHealth();

        // 设置brokers属性
        $this->brokers = $options['brokers'] ?? [];
    }

    /**
     * 注册消费者健康状态
     */
    protected function registerConsumerHealth(): void
    {
        // 更新消费者心跳
        $this->healthCheck->updateHeartbeat($this->consumerId, [
            'connection' => $this->connectionName,
            'topic' => $this->options['topic'] ?? '',
            'group_id' => $this->options['group_id'] ?? '',
            'status' => 'active'
        ]);

        // 记录健康检查指标
        $this->metricsCollector->incrementGauge('consumer_health_status', 1, [
            'consumer_id' => $this->consumerId,
            'connection' => $this->connectionName ?? 'kafka',
            'status' => 'active'
        ]);
    }

    protected function initProducer() // 初始化生产者
    {
        $conf = new Conf(); // 创建配置对象

        // 从配置热加载管理器获取broker列表
        $brokers = $this->configManager->get('kafka.connections.kafka.brokers', $this->options['brokers']);
        $conf->set('metadata.broker.list', $brokers); // 设置broker列表

        // 启用幂等性和事务支持
        $enableTransactions = $this->options['transaction']['enabled'] ?? false;
        if ($enableTransactions) {
            // 设置事务ID
            $transactionId = $this->options['transaction_id'] ?? 'txn-' . uniqid('', true);
            $conf->set('transactional.id', $transactionId);

            // 启用幂等性
            $conf->set('enable.idempotence', 'true');

            // 设置确认机制为全部确认
            $conf->set('acks', 'all');

            Log::info('Kafka producer initialized with transaction support', ['transaction_id' => $transactionId]);
        }

        // 设置压缩编码
        if (isset($this->options['producer']['compression.codec'])) {
            $conf->set('compression.codec', $this->options['producer']['compression.codec']);
        }

        // 设置重试次数
        if (isset($this->options['producer']['message.send.max.retries'])) {
            $conf->set('message.send.max.retries', (string)$this->options['producer']['message.send.max.retries']);
        }

        // 设置批量大小
        if (isset($this->options['producer']['batch.size'])) {
            $conf->set('batch.size', (string)$this->options['producer']['batch.size']);
        }

        // 设置linger.ms (消息批处理延迟时间)
        if (isset($this->options['producer']['linger.ms'])) {
            $conf->set('linger.ms', (string)$this->options['producer']['linger.ms']);
        }

        if (isset($this->options['debug']) && $this->options['debug']) {
            $conf->set('debug', 'all'); // 如果配置中启用了调试模式，则设置调试选项
        }

        Log::info('Kafka producer init success');
        // 使用配置好的 $conf 对象创建一个新的 Kafka 生产者实例，并赋值给 $this->producer
        $this->producer = new Producer($conf);

        // 如果启用了事务，初始化事务
        if ($enableTransactions) {
            try {
                $this->producer->initTransactions(10000); // 10秒超时
                Log::info('Kafka producer transactions initialized');
            } catch (\Exception $e) {
                $errorMessage = 'Failed to initialize Kafka transactions: ' . $e->getMessage();
                Log::error($errorMessage);
                $this->sentryReporter->captureException($e, [
                    'component' => 'kafka_producer',
                    'action' => 'init_transactions'
                ]);
            }
        }
    }

    public function push($job, $data = '', $queue = null) // 推送消息到队列
    {
        try {
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

            // 添加Sentry面包屑
            $this->sentryReporter->addBreadcrumb(
                'Pushing job to queue',
                'queue',
                [
                    'job' => is_object($job) ? get_class($job) : $job,
                    'queue' => $queue ?: $this->options['topic'],
                    'message_id' => $payloadArray['message_id']
                ]
            );

            // 使用事务发送消息
            $enableTransactions = $this->options['transaction']['enabled'] ?? false;
            if ($enableTransactions) {
                return $this->pushWithTransaction($payload, $queue);
            }

            return $this->pushRaw($payload, $queue);
        } catch (\Exception $e) {
            // 报告异常到Sentry
            $this->sentryReporter->captureException($e, [
                'component' => 'kafka_producer',
                'action' => 'push',
                'job' => is_object($job) ? get_class($job) : $job,
                'queue' => $queue ?: $this->options['topic']
            ]);

            throw $e;
        }
    }

    /**
     * 使用事务推送消息到队列
     * 
     * @param string $payload 消息内容
     * @param string|null $queue 队列名称
     * @return bool 是否成功
     */
    protected function pushWithTransaction($payload, $queue = null)
    {
        try {
            // 开始事务
            $this->producer->beginTransaction();

            // 创建一个新的Topic对象
            $topic = $this->producer->newTopic($queue ?: $this->options['topic']);

            // 向Topic中生产消息
            $topic->produce(RD_KAFKA_PARTITION_UA, 0, $payload);

            // 提交事务
            $this->producer->commitTransaction(10000); // 10秒超时

            Log::info('Message pushed with transaction', [
                'queue' => $queue ?: $this->options['topic'],
                'payload_size' => strlen($payload)
            ]);

            return true;
        } catch (\Exception $e) {
            // 中止事务
            try {
                $this->producer->abortTransaction(10000); // 10秒超时
            } catch (\Exception $abortException) {
                Log::error('Failed to abort Kafka transaction: ' . $abortException->getMessage());
            }

            // 报告异常到Sentry
            $this->sentryReporter->captureException($e, [
                'component' => 'kafka_producer',
                'action' => 'push_with_transaction',
                'queue' => $queue ?: $this->options['topic']
            ]);

            throw new \Exception('Kafka transaction push error: ' . $e->getMessage(), 0, $e);
        }
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
        // 尝试解决主题不存在的问题，设置主题可以自动创建
        $conf->set('allow.auto.create.topics', 'true');

        // 设置客户端ID
        if (isset($this->options['client.id'])) {
            $conf->set('client.id', $this->options['client.id']);
        } else {
            // 使用消费者唯一ID作为客户端ID
            $conf->set('client.id', $this->consumerId);
        }

        // 设置隔离级别，用于事务支持
        if ($this->options['transaction']['enabled'] ?? false) {
            // 设置为读已提交，确保只读取已提交的事务消息
            $conf->set('isolation.level', 'read_committed');
            Log::info('Kafka consumer configured with read_committed isolation level for transaction support');
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

    // 定义一个方法pushRaw，用于将原始数据推送到Kafka队列
    public function pushRaw($payload, $queue = null, array $options = []) // 推送原始数据到队列
    {
        try {
            // 使用已经初始化好的生产者，不要每次都创建新的
            Log::info('Kafka pushRaw: Pushing message to topic: ' . ($queue ?: $this->options['topic']));
            
            // 创建一个新的Topic对象，如果传入的$queue为null，则使用当前对象的options数组中的topic
            $topic = $this->producer->newTopic($queue ?: $this->options['topic']);
            
            Log::info('Kafka pushRaw: Payload: ' . substr($payload, 0, 100) . (strlen($payload) > 100 ? '...' : ''));
            
            // 向Topic中生产消息，使用未分配的分区（RD_KAFKA_PARTITION_UA），消息的key为0，消息的内容为$payload
            $topic->produce(RD_KAFKA_PARTITION_UA, 0, $payload);
            
            // 刷新生产者，等待所有消息发送完成，超时时间为10000毫秒
            $result = $this->producer->flush(10000);
            
            if ($result !== RD_KAFKA_RESP_ERR_NO_ERROR) {
                Log::error('Kafka pushRaw: Failed to flush message. Error code: ' . $result);
                return false;
            }
            
            Log::info('Kafka pushRaw: Message successfully sent to topic: ' . ($queue ?: $this->options['topic']));
            return true;
        } catch (Exception $e) {
            // 报告异常到Sentry
            $this->sentryReporter->captureException($e, [
                'component' => 'kafka_producer',
                'action' => 'push_raw',
                'queue' => $queue ?: $this->options['topic']
            ]);

            Log::error('Kafka pushRaw error: ' . $e->getMessage() . ', File: ' . $e->getFile() . ':' . $e->getLine());
            throw new Exception('Kafka push error: ' . $e->getMessage()); // 捕获异常并抛出
        }
    }

    // 延迟推送消息
    public function later($delay, $job, $data = '', $queue = null)
    {
        try {
            // 创建payload
            $payload = $this->createPayload($job, $data);

            // 解析payload
            $payloadArray = json_decode($payload, true);

            // 添加可执行时间
            $payloadArray['available_at'] = $this->availableAt($delay);
            $payloadArray['original_queue'] = $queue ?: $this->options['topic'];

            // 添加唯一消息ID用于幂等性处理
            if (!isset($payloadArray['message_id'])) {
                $payloadArray['message_id'] = uniqid('msg_', true);
            }

            // 重新编码payload
            $payload = json_encode($payloadArray);

            // 添加Sentry面包屑
            $this->sentryReporter->addBreadcrumb(
                'Pushing delayed job to queue',
                'queue',
                [
                    'job' => is_object($job) ? get_class($job) : $job,
                    'queue' => $queue ?: $this->options['topic'],
                    'message_id' => $payloadArray['message_id'],
                    'delay' => $delay,
                    'available_at' => date('Y-m-d H:i:s', $payloadArray['available_at'])
                ]
            );

            // 将消息推送到延迟队列
            $delayQueue = ($queue ?: $this->options['topic']) . '_delayed';

            // 使用事务发送消息
            $enableTransactions = $this->options['transaction']['enabled'] ?? false;
            if ($enableTransactions) {
                return $this->pushWithTransaction($payload, $delayQueue);
            }

            return $this->pushRaw($payload, $delayQueue);
        } catch (\Exception $e) {
            // 报告异常到Sentry
            $this->sentryReporter->captureException($e, [
                'component' => 'kafka_producer',
                'action' => 'later',
                'job' => is_object($job) ? get_class($job) : $job,
                'queue' => $queue ?: $this->options['topic'],
                'delay' => $delay
            ]);

            throw $e;
        }
    }

    // 从队列中弹出消息
    public function pop($queue = null)
    {
        try {
            // 如果正在关闭，则返回null
            if ($this->isShuttingDown) {
                Log::info('Consumer is shutting down, not consuming more messages');
                return null;
            }

            // 更新消费者健康状态
            $this->updateHealthStatus('consuming');

            // 确定要消费的队列名称
            $queueName = $queue ?: $this->options['topic'];

            // 检查是否是延迟队列
            $isDelayedQueue = strpos($queueName, '_delayed') !== false;

            try {
                // 尝试订阅指定的队列或主题
                $this->consumer->subscribe([$queueName]);
                
                // 从订阅的队列或主题中消费消息
                // consume方法的参数是超时时间，单位为毫秒
                // 这里设置为1秒（1000毫秒），减少等待时间以便于优雅关闭
                $message = $this->consumer->consume(1000);
            } catch (\Exception $e) {
                // 如果遇到主题不存在的错误，尝试创建主题
                if (strpos($e->getMessage(), 'Unknown topic or partition') !== false) {
                    Log::warning('Topic {topic} not found, attempting to create it', ['topic' => $queueName]);
                    
                    // 创建主题
                    $this->createTopic($queueName);
                    
                    // 重新订阅
                    $this->consumer->subscribe([$queueName]);
                    $message = $this->consumer->consume(1000);
                } else {
                    // 其他错误，重新抛出
                    throw $e;
                }
            }

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

                // 如果是主题不存在的错误，尝试创建主题
                if ($message->err === RD_KAFKA_RESP_ERR__UNKNOWN_TOPIC || $message->err === RD_KAFKA_RESP_ERR_UNKNOWN_TOPIC_OR_PART) {
                    Log::warning('Topic {topic} not found in consume, attempting to create it', ['topic' => $queueName]);
                    
                    // 创建主题
                    $this->createTopic($queueName);
                    
                    // 重新订阅
                    $this->consumer->subscribe([$queueName]);
                    return null;
                }

                // 报告错误到Sentry
                $errorMessage = $message->errstr();
                $this->sentryReporter->captureMessage(
                    'Kafka consumer error: ' . $errorMessage,
                    [
                        'component' => 'kafka_consumer',
                        'action' => 'consume',
                        'queue' => $queueName,
                        'error_code' => $message->err
                    ],
                    'error'
                );

                throw new Exception($errorMessage); // 抛出异常
            }

            // 检查消息ID是否已处理（使用Redis进行幂等性检查）
            $payload = json_decode($message->payload, true);
            if (isset($payload['message_id']) && $this->idempotent->isProcessed($payload['message_id'], $queueName)) {
                Log::info('Skipping already processed message: {message_id}', ['message_id' => $payload['message_id']]);
                $this->consumer->commit($message);
                return null;
            }

            // 检查是否需要重新平衡分区
            $this->checkPartitionRebalance($queueName);

            // 如果是延迟队列，检查消息是否已经到达可执行时间
            if ($isDelayedQueue && isset($payload['available_at']) && isset($payload['original_queue'])) {
                return $this->handleDelayedMessage($message, $payload, $queueName);
            }

            // 添加Sentry面包屑
            $this->sentryReporter->addBreadcrumb(
                'Message popped from queue',
                'queue',
                [
                    'queue' => $queueName,
                    'message_id' => $payload['message_id'] ?? 'unknown',
                    'offset' => $message->offset,
                    'partition' => $message->partition
                ]
            );

            Log::info('Kafka pop success! topic: {topic}, queue: {queue}', ['topic' => $this->options['topic'], 'queue' => $queueName]);

            // 更新消费者健康状态
            $this->updateHealthStatus('processing', [
                'current_message_id' => $payload['message_id'] ?? 'unknown',
                'current_offset' => $message->offset,
                'current_partition' => $message->partition
            ]);

            return $this->createJob($message); // 创建并返回KafkaJob实例
        } catch (\Exception $e) {
            Log::error('Kafka pop error: ' . $e->getMessage()); // 记录错误日志

            // 报告异常到Sentry
            $this->sentryReporter->captureException($e, [
                'component' => 'kafka_consumer',
                'action' => 'pop',
                'queue' => $queue ?: $this->options['topic']
            ]);

            $this->consumer->unsubscribe(); // 取消订阅

            // 记录失败指标
            if (isset($this->options['topic'])) {
                $this->metricsCollector->incrementCounter('consumer_errors_total', 1, [
                    'connection' => $this->connectionName ?? 'kafka',
                    'topic' => $this->options['topic']
                ]);
            }

            // 更新消费者健康状态
            $this->updateHealthStatus('error', [
                'error' => $e->getMessage(),
                'error_time' => time()
            ]);

            return null;
        }
    }

    /**
     * 检查是否需要重新平衡分区
     * 
     * @param string $queueName 队列名称
     * @return void
     */
    protected function checkPartitionRebalance(string $queueName): void
    {
        // 检查是否需要重新平衡分区
        if ($this->partitionManager->needRebalance($queueName, $this->lastRebalanceCheck)) {
            $this->lastRebalanceCheck = time();

            // 获取消费者应该消费的分区
            $partitions = $this->partitionManager->getConsumerPartitions($queueName, $this->consumerId);

            Log::info('Rebalancing partitions: topic: {topic}, consumer_id: {consumer_id}, partitions: {partitions}', [
                'topic' => $queueName,
                'consumer_id' => $this->consumerId,
                'partitions' => $partitions
            ]);

            // 重新订阅指定分区
            $this->consumer->unsubscribe();
            $topicPartitions = [];
            foreach ($partitions as $partition) {
                $topicPartitions[] = new \RdKafka\TopicPartition($queueName, $partition);
            }
            $this->consumer->assign($topicPartitions);

            // 添加Sentry面包屑
            $this->sentryReporter->addBreadcrumb(
                'Partitions rebalanced',
                'queue',
                [
                    'topic' => $queueName,
                    'consumer_id' => $this->consumerId,
                    'partitions' => $partitions
                ]
            );

            // 更新健康状态
            $this->updateHealthStatus('rebalancing', [
                'partitions' => $partitions,
                'rebalance_time' => time()
            ]);
        }
    }

    /**
     * 处理延迟队列中的消息
     * 
     * @param \RdKafka\Message $message 消息对象
     * @param array $payload 消息内容
     * @param string $queueName 队列名称
     * @return null
     */
    protected function handleDelayedMessage(\RdKafka\Message $message, array $payload, string $queueName)
    {
        // 如果当前时间小于可执行时间，说明消息还不能执行
        if (time() < $payload['available_at']) {
            // 将消息放回延迟队列，等待下次处理
            $this->pushRaw($message->payload, $queueName);
            // 提交消息偏移量，表示已经处理过这条消息
            $this->consumer->commit($message);
            Log::info('Delayed message not ready yet, put back to delayed queue: {queue}, available_at: {available_at}, now: {now}', [
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
        Log::info('Delayed message ready, moved to original queue: {from_queue}, to_queue: {to_queue}', [
            'from_queue' => $queueName,
            'to_queue' => $payload['original_queue']
        ]);

        // 添加Sentry面包屑
        $this->sentryReporter->addBreadcrumb(
            'Delayed message moved to original queue',
            'queue',
            [
                'from_queue' => $queueName,
                'to_queue' => $payload['original_queue'],
                'message_id' => $payload['message_id'] ?? 'unknown',
                'available_at' => date('Y-m-d H:i:s', $payload['available_at'])
            ]
        );

        return null;
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

        // 更新健康状态
        $this->updateHealthStatus('shutting_down');

        // 取消订阅
        if ($this->consumer) {
            try {
                $this->consumer->unsubscribe();
                Log::info('Kafka consumer unsubscribed successfully');
            } catch (\Exception $e) {
                Log::error('Error unsubscribing Kafka consumer: ' . $e->getMessage());

                // 报告异常到Sentry
                $this->sentryReporter->captureException($e, [
                    'component' => 'kafka_consumer',
                    'action' => 'shutdown'
                ]);
            }
        }

        // 注销消费者
        try {
            $topic = $this->options['topic'] ?? 'default';
            $this->partitionManager->unregisterConsumer($topic, $this->consumerId);
            Log::info('Consumer unregistered: {consumer_id}', ['consumer_id' => $this->consumerId]);
        } catch (\Exception $e) {
            Log::error('Error unregistering consumer: ' . $e->getMessage());
        }

        Log::info('Kafka consumer shutdown complete');
    }

    /**
     * 创建KafkaJob实例
     * 
     * @param \RdKafka\Message $message 消息对象
     * @return KafkaJob
     */
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
        $this->metricsCollector->incrementCounter('failed_messages_total', 1, [
            'connection' => $this->connectionName ?? 'kafka',
            'topic' => $queue
        ]);

        // 更新健康状态
        $this->updateHealthStatus('error', [
            'last_failed_message_id' => $messageId,
            'last_error' => $error,
            'error_time' => time()
        ]);

        // 报告错误到Sentry
        $this->sentryReporter->captureMessage(
            'Message processing failed: ' . $error,
            [
                'component' => 'kafka_job',
                'action' => 'process',
                'message_id' => $messageId,
                'queue' => $queue,
                'processing_time' => round($processingTime, 4),
                'payload' => $payload
            ],
            'error'
        );

        Log::error('Message processing failed: message_id={message_id}, queue={queue}, error={error}, processing_time={processing_time}', [
            'message_id' => $messageId,
            'queue' => $queue,
            'error' => $error,
            'processing_time' => round($processingTime, 4)
        ]);
    }
    protected function updateHealthStatus(string $status, array $metadata = []): void
    {
        try {
            $this->healthCheck->setConsumerStatus($this->consumerId, $status, $metadata);
        } catch (\Exception $e) {
            Log::error('Failed to update consumer health status: ' . $e->getMessage());
        }
    }

    /**
     * 创建Kafka主题
     * 
     * @param string $topic 主题名称
     * @return bool
     */
    protected function createTopic(string $topic): bool
    {
        $config = $this->options;
        $brokers = $this->brokers;
        
        try {
            if (!is_string($brokers)) {
                $brokers = implode(',', $brokers);
            }
            
            $this->log('debug', "尝试创建Kafka主题: {$topic}, 使用broker: {$brokers}");
            
            // 创建一个临时shell命令来创建主题
            $command = "kafka-topics.sh --create --topic {$topic} --bootstrap-server {$brokers} --partitions 1 --replication-factor 1 2>&1";
            
            $this->log('debug', "执行命令: {$command}");
            $output = shell_exec($command);
            
            if ($output) {
                $this->log('info', "Kafka主题创建输出: {$output}");
            }
            
            // 不管输出如何，都认为主题已创建或已存在
            return true;
        } catch (\Exception $e) {
            $this->log('error', "创建Kafka主题失败: {$e->getMessage()}", ['topic' => $topic, 'exception' => $e]);
            return false;
        }
    }

    /**
     * 记录日志
     *
     * @param string $level 日志级别
     * @param string $message 日志消息
     * @param array $context 日志上下文
     * @return void
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        if (class_exists('\\think\\facade\\Log')) {
            \think\facade\Log::$level($message, $context);
        }
    }

    /**
     * 简化的主题创建方法，适用于无法使用kafka-topics.sh的环境
     *
     * @param string $topic 主题名称
     * @return bool
     */
    protected function createTopicAlternative(string $topic): bool
    {
        try {
            $this->log('debug', "尝试使用RdKafka直接发送消息来创建主题: {$topic}");
            
            // 创建生产者配置
            $conf = new \RdKafka\Conf();
            
            // 转换brokers为字符串
            $brokers = $this->options['brokers'];
            if (!is_string($brokers)) {
                $brokers = implode(',', $brokers);
            }
            
            // 创建生产者并发送测试消息以触发主题创建
            $producer = new \RdKafka\Producer($conf);
            $producer->addBrokers($brokers);
            
            // 等待broker连接
            $this->log('debug', "等待broker连接: {$brokers}");
            
            // 获取主题对象
            $topic_obj = $producer->newTopic($topic);
            
            // 发送一个消息以触发主题创建
            $topic_obj->produce(RD_KAFKA_PARTITION_UA, 0, json_encode(['test' => 'create_topic']));
            $producer->flush(1000);
            
            $this->log('info', "主题创建成功: {$topic}");
            return true;
        } catch (\Exception $e) {
            $this->log('error', "使用RdKafka创建主题失败: {$e->getMessage()}", ['topic' => $topic, 'exception' => $e]);
            return false;
        }
    }

    public function getTenantSpecificTopic(string $tenantId, string $topic): string
    {
        $queueName = $tenantId . '.' . $topic;
        Log::info('生成租户队列名称: {queue}', ['queue' => $queueName]);
        return $queueName;
    }
}
