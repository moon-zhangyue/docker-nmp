<?php

namespace think\queue\pool;

use EasySwoole\Pool\AbstractPool;
use EasySwoole\Pool\Config as PoolConfig;
use think\facade\Log;
use RdKafka\Producer;
use RdKafka\Conf;

/**
 * Kafka连接池
 * 
 * 用于在云原生和大规模分布式场景下高效管理Kafka连接
 */
class ConnectionPool extends AbstractPool
{
    /**
     * 配置信息
     * @var array
     */
    protected $config;

    /**
     * 构造函数
     * 
     * @param array $config Kafka配置
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        
        // 初始化连接池配置
        $poolConfig = new PoolConfig();
        
        // 从配置中获取连接池设置，或使用默认值
        $poolConfig->setMinObjectNum($config['pool']['min_connections'] ?? 5);
        $poolConfig->setMaxObjectNum($config['pool']['max_connections'] ?? 20);
        $poolConfig->setMaxIdleTime($config['pool']['max_idle_time'] ?? 60);
        $poolConfig->setMaxObjectWaitTime($config['pool']['max_wait_time'] ?? 3.0);
        
        // 设置获取连接和归还连接时的自动检查间隔
        $poolConfig->setGetObjectTimeout($config['pool']['get_timeout'] ?? 3.0);
        $poolConfig->setIntervalCheckTime($config['pool']['check_interval'] ?? 30 * 1000); // 默认30秒检查一次
        
        Log::info('初始化Kafka连接池', [
            'min_connections' => $poolConfig->getMinObjectNum(),
            'max_connections' => $poolConfig->getMaxObjectNum(),
            'max_idle_time' => $poolConfig->getMaxIdleTime(),
            'check_interval' => $poolConfig->getIntervalCheckTime(),
        ]);
        
        parent::__construct($poolConfig);
    }

    /**
     * 创建Kafka生产者对象
     * 
     * @return Producer
     */
    protected function createObject()
    {
        try {
            $conf = new Conf();
            
            // 获取broker列表
            $brokers = $this->config['brokers'] ?? 'localhost:9092';
            if (is_array($brokers)) {
                $brokers = implode(',', $brokers);
            }
            
            // 设置broker列表
            $conf->set('metadata.broker.list', $brokers);
            
            // 是否启用事务支持
            $enableTransactions = $this->config['transaction']['enabled'] ?? false;
            if ($enableTransactions) {
                // 设置事务ID
                $transactionId = $this->config['transaction_id'] ?? 'txn-' . uniqid('', true);
                $conf->set('transactional.id', $transactionId);
                
                // 启用幂等性
                $conf->set('enable.idempotence', 'true');
                
                // 设置确认机制为全部确认
                $conf->set('acks', 'all');
                
                Log::info('创建Kafka生产者(事务支持)', ['transaction_id' => $transactionId]);
            }
            
            // 设置压缩编码
            if (isset($this->config['producer']['compression.codec'])) {
                $conf->set('compression.codec', $this->config['producer']['compression.codec']);
            }
            
            // 设置重试次数
            if (isset($this->config['producer']['message.send.max.retries'])) {
                $conf->set('message.send.max.retries', (string)$this->config['producer']['message.send.max.retries']);
            }
            
            // 设置批量大小
            if (isset($this->config['producer']['batch.size'])) {
                $conf->set('batch.size', (string)$this->config['producer']['batch.size']);
            }
            
            // 设置linger.ms (消息批处理延迟时间)
            if (isset($this->config['producer']['linger.ms'])) {
                $conf->set('linger.ms', (string)$this->config['producer']['linger.ms']);
            }
            
            // 如果配置中启用了调试模式，则设置调试选项
            if (isset($this->config['debug']) && $this->config['debug']) {
                $conf->set('debug', 'all');
            }
            
            // 设置超时时间
            $conf->set('socket.timeout.ms', (string)($this->config['socket.timeout.ms'] ?? '30000'));
            $conf->set('request.timeout.ms', (string)($this->config['request.timeout.ms'] ?? '30000'));
            
            // 创建生产者
            $producer = new Producer($conf);
            
            // 如果启用了事务，初始化事务
            if ($enableTransactions) {
                try {
                    $producer->initTransactions(10000); // 10秒超时
                    Log::info('Kafka生产者事务初始化成功');
                } catch (\Exception $e) {
                    Log::error('Kafka生产者事务初始化失败: ' . $e->getMessage());
                }
            }
            
            Log::info('Kafka连接创建成功', ['broker' => $brokers]);
            return $producer;
        } catch (\Exception $e) {
            Log::error('Kafka连接创建失败: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            throw $e;
        }
    }

    /**
     * 连接回收时的处理
     * 
     * @param Producer $object
     * @return bool
     */
    public function recycleObj($object): bool
    {
        if (!$object instanceof Producer) {
            return false;
        }
        
        try {
            // 刷新所有剩余消息
            $result = $object->flush(5000); // 5秒超时
            if ($result !== RD_KAFKA_RESP_ERR_NO_ERROR) {
                Log::warning('回收Kafka连接时消息刷新失败', ['error_code' => $result]);
            }
            
            return true;
        } catch (\Exception $e) {
            Log::error('回收Kafka连接时出现异常: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 连接对象池满时的处理
     * 
     * @param Producer $object
     */
    protected function objectRestore($object)
    {
        try {
            // 刷新所有剩余消息
            $object->flush(1000); // 1秒超时
            
            // 不需要关闭连接，由GC处理
        } catch (\Exception $e) {
            Log::error('恢复Kafka连接时出现异常: ' . $e->getMessage());
        }
    }

    /**
     * 销毁连接池
     */
    function destroy()
    {
        try {
            // 关闭所有连接
            foreach ($this->getPoolObjects() as $object) {
                if ($object instanceof Producer) {
                    // 尝试刷新所有未发送的消息
                    $object->flush(1000); // 1秒超时
                }
            }
            
            Log::info('Kafka连接池已销毁');
            
            // 调用父类销毁方法
            parent::destroy();
        } catch (\Exception $e) {
            Log::error('销毁Kafka连接池时出现异常: ' . $e->getMessage());
        }
    }
    
    /**
     * 获取连接池中的所有对象
     * 
     * @return array
     */
    protected function getPoolObjects(): array
    {
        $objects = [];
        
        while (!$this->poolChannel->isEmpty()) {
            $obj = $this->poolChannel->pop(0.01);
            if ($obj) {
                $objects[] = $obj;
            }
        }
        
        return $objects;
    }
} 