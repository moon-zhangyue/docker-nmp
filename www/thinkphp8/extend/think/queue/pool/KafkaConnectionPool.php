<?php

namespace think\queue\pool;

use think\facade\Log;
use think\facade\Config;
use RdKafka\Producer;
use RdKafka\Conf;
use Swoole\Coroutine\Channel;
use think\swoole\pool\Pool as SwoolePool;
use think\swoole\pool\proxy\Proxy;

/**
 * Kafka连接池
 * 
 * 使用think-swoole实现连接池
 */
class KafkaConnectionPool extends SwoolePool
{
    /**
     * 连接池配置
     * @var array
     */
    protected $config;
    
    /**
     * 构造函数
     * 
     * @param array $config 连接池配置
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        
        parent::__construct(function() {
            return $this->createConnection();
        }, [
            'min' => $config['pool']['min'],
            'max' => $config['pool']['max'],
            'idle_time' => $config['pool']['idle_time'],
            'wait_time' => $config['pool']['wait_time']
        ]);
        
        Log::info('初始化Kafka连接池配置,min:{min},max:{max},idle_time:{idle_time},wait_time:{wait_time}', [
            'min' => $config['pool']['min'],
            'max' => $config['pool']['max'],
            'idle_time' => $config['pool']['idle_time'],
            'wait_time' => $config['pool']['wait_time']
        ]);
    }
    
    /**
     * 创建新连接
     * 
     * @return Producer|null
     */
    protected function createConnection()
    {
        try {
            $conf = new Conf();
            
            // 设置基本配置
            $conf->set('bootstrap.servers', $this->config['bootstrap_servers']);
            $conf->set('client.id', $this->config['client_id']);
            $conf->set('group.id', $this->config['group_id']);
            $conf->set('auto.offset.reset', $this->config['auto_offset_reset']);
            
            // 设置安全配置
            if (!empty($this->config['security_protocol'])) {
                $conf->set('security.protocol', $this->config['security_protocol']);
                $conf->set('sasl.mechanism', $this->config['sasl_mechanism']);
                $conf->set('sasl.username', $this->config['sasl_username']);
                $conf->set('sasl.password', $this->config['sasl_password']);
            }
            
            // 创建生产者实例
            $producer = new Producer($conf);
            
            // 设置日志回调
            $producer->setLogLevel(LOG_DEBUG);
            
            // 设置错误回调
            $producer->setErrorCb(function($kafka, $err, $reason) {
                Log::error('Kafka错误: {err},原因: {reason}', [
                    'err' => $err,
                    'reason' => $reason
                ]);
            });
            
            // 设置统计回调
            $producer->setStatsCb(function($kafka, $json, $json_len) {
                Log::info('Kafka统计: {stats}', ['stats' => $json]);
            });
            
            // 设置主题元数据回调
            $producer->setMetadataTimeout($this->config['metadata_timeout']);
            $producer->setTopicMetadataRefreshIntervalMs($this->config['topic_metadata_refresh_interval_ms']);
            
            // 设置消息发送配置
            $producer->setMessageMaxBytes($this->config['message_max_bytes']);
            $producer->setMaxPollIntervalMs($this->config['max_poll_interval_ms']);
            
            // 设置压缩配置
            if (!empty($this->config['compression_type'])) {
                $producer->setCompressionType($this->config['compression_type']);
            }
            
            // 设置批量发送配置
            $producer->setBatchSize($this->config['batch_size']);
            $producer->setBatchNumMessages($this->config['batch_num_messages']);
            $producer->setBatchTimeout($this->config['batch_timeout']);
            
            // 设置事务配置
            if ($this->config['transactional_id']) {
                $producer->setTransactionalId($this->config['transactional_id']);
            }
            
            // 设置消息确认配置
            $producer->setRequiredAcks($this->config['required_acks']);
            $producer->setRequestTimeoutMs($this->config['request_timeout_ms']);
            
            // 设置重试配置
            $producer->setRetries($this->config['retries']);
            $producer->setRetryBackoffMs($this->config['retry_backoff_ms']);
            
            // 设置连接超时
            $producer->setConnectionTimeout($this->config['connection_timeout']);
            
            // 设置调试配置
            if ($this->config['debug']) {
                $producer->setDebug('all');
            }
            
            Log::info('Kafka连接创建成功,active_count:{count}', ['count' => $this->getActiveCount()]);
            return $producer;
        } catch (\Exception $e) {
            Log::error('Kafka连接创建失败: {message}', ['message' => $e->getMessage()]);
            return null;
        }
    }
    
    /**
     * 获取连接
     * 
     * @return Producer|Proxy
     */
    public function get()
    {
        return parent::get();
    }
    
    /**
     * 归还连接
     * 
     * @param Producer|Proxy $producer
     */
    public function recycleObj($producer)
    {
        parent::recycle($producer);
    }
    
    /**
     * 关闭连接池
     */
    public function close()
    {
        try {
            parent::close();
            Log::info('Kafka连接池已关闭,active_count:{count}', ['count' => $this->getActiveCount()]);
        } catch (\Exception $e) {
            Log::error('关闭Kafka连接池失败: {message}', ['message' => $e->getMessage()]);
        }
    }
} 