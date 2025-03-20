<?php

declare(strict_types=1);

namespace think\queue\transaction;

use RdKafka\Producer;
use RdKafka\Conf;
use think\facade\Log;

/**
 * Kafka事务管理器
 * 用于管理Kafka生产者的事务，确保消息发送的原子性
 */
class KafkaTransaction
{
    /**
     * 生产者实例
     * @var Producer
     */
    protected $producer;

    /**
     * 事务ID
     * @var string
     */
    protected $transactionId;

    /**
     * 事务状态
     * @var string
     */
    protected $state = 'none'; // none, init, begin, commit, abort

    /**
     * 超时时间（毫秒）
     * @var int
     */
    protected $timeout = 10000;

    /**
     * 构造函数
     * 
     * @param array $options 配置选项
     */
    public function __construct(array $options = [])
    {
        // 设置事务ID，如果没有提供则生成一个唯一ID
        $this->transactionId = $options['transaction_id'] ?? 'txn-' . uniqid('', true);

        // 设置超时时间
        if (isset($options['timeout'])) {
            $this->timeout = (int)$options['timeout'];
        } elseif (isset($options['transaction']['timeout'])) {
            $this->timeout = (int)$options['transaction']['timeout'];
        }

        // 初始化生产者
        $this->initProducer($options);
    }

    /**
     * 初始化生产者
     * 
     * @param array $options 配置选项
     * @return void
     */
    protected function initProducer(array $options): void
    {
        try {
            $conf = new Conf();

            // 设置broker列表
            $brokers = $options['brokers'] ?? 'localhost:9092';
            $conf->set('metadata.broker.list', $brokers);

            // 设置事务ID，启用事务功能
            $conf->set('transactional.id', $this->transactionId);

            // 设置生产者确认机制，确保消息被正确提交
            $conf->set('enable.idempotence', 'true');
            $conf->set('acks', 'all');

            // 设置重试次数
            $conf->set('message.send.max.retries', '5');

            // 创建生产者实例
            $this->producer = new Producer($conf);

            Log::info('Kafka transaction producer initialized', ['transaction_id' => $this->transactionId]);
        } catch (\Exception $e) {
            Log::error('Failed to initialize Kafka transaction producer', [
                'error' => $e->getMessage(),
                'transaction_id' => $this->transactionId
            ]);
            throw $e;
        }
    }

    /**
     * 初始化事务
     * 
     * @return bool 是否成功
     */
    public function initTransactions(): bool
    {
        try {
            if ($this->state !== 'none') {
                Log::warning('Transaction already initialized', ['transaction_id' => $this->transactionId]);
                return false;
            }

            $this->producer->initTransactions($this->timeout);
            $this->state = 'init';

            Log::info('Transaction initialized', ['transaction_id' => $this->transactionId]);
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to initialize transaction', [
                'error' => $e->getMessage(),
                'transaction_id' => $this->transactionId
            ]);
            return false;
        }
    }

    /**
     * 开始事务
     * 
     * @return bool 是否成功
     */
    public function beginTransaction(): bool
    {
        try {
            if ($this->state !== 'init') {
                if ($this->state === 'none') {
                    $this->initTransactions();
                } else {
                    Log::warning('Cannot begin transaction in current state', [
                        'state' => $this->state,
                        'transaction_id' => $this->transactionId
                    ]);
                    return false;
                }
            }

            $this->producer->beginTransaction();
            $this->state = 'begin';

            Log::info('Transaction began', ['transaction_id' => $this->transactionId]);
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to begin transaction', [
                'error' => $e->getMessage(),
                'transaction_id' => $this->transactionId
            ]);
            return false;
        }
    }

    /**
     * 提交事务
     * 
     * @return bool 是否成功
     */
    public function commitTransaction(): bool
    {
        try {
            if ($this->state !== 'begin') {
                Log::warning('Cannot commit transaction in current state', [
                    'state' => $this->state,
                    'transaction_id' => $this->transactionId
                ]);
                return false;
            }

            $this->producer->commitTransaction($this->timeout);
            $this->state = 'commit';

            Log::info('Transaction committed', ['transaction_id' => $this->transactionId]);
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to commit transaction', [
                'error' => $e->getMessage(),
                'transaction_id' => $this->transactionId
            ]);

            // 尝试中止事务
            $this->abortTransaction();
            return false;
        }
    }

    /**
     * 中止事务
     * 
     * @return bool 是否成功
     */
    public function abortTransaction(): bool
    {
        try {
            if ($this->state !== 'begin') {
                Log::warning('Cannot abort transaction in current state', [
                    'state' => $this->state,
                    'transaction_id' => $this->transactionId
                ]);
                return false;
            }

            $this->producer->abortTransaction($this->timeout);
            $this->state = 'abort';

            Log::info('Transaction aborted', ['transaction_id' => $this->transactionId]);
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to abort transaction', [
                'error' => $e->getMessage(),
                'transaction_id' => $this->transactionId
            ]);
            return false;
        }
    }

    /**
     * 获取生产者实例
     * 
     * @return Producer 生产者实例
     */
    public function getProducer(): Producer
    {
        return $this->producer;
    }

    /**
     * 获取事务状态
     * 
     * @return string 事务状态
     */
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * 刷新生产者
     * 
     * @param int $timeout 超时时间（毫秒）
     * @return int 返回仍在队列中的消息数量
     */
    public function flush(?int $timeout = null): int
    {
        if ($timeout === null) {
            $timeout = $this->timeout;
        }

        return $this->producer->flush($timeout);
    }
}
