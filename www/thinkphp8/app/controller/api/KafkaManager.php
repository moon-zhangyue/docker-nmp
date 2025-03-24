<?php
declare(strict_types=1);

namespace app\controller\api;

use app\BaseController;
use think\facade\Log;
use think\facade\Config;
use think\Request;
use think\Response;
use RdKafka\Metadata;
use RdKafka\MetadataTopic;
use RdKafka\Admin;
use RdKafka\AdminOptions;
use RdKafka\Conf;
use think\queue\pool\PoolFactory;

/**
 * Kafka管理控制器
 */
class KafkaManager extends BaseController
{
    /**
     * 获取所有主题
     * 
     * @param Request $request
     * @return Response
     */
    public function getTopics(Request $request)
    {
        try {
            // 获取连接池
            $pool = PoolFactory::getPool('kafka');
            $producer = $pool->getObject();
            
            // 获取超时设置
            $timeout = $request->param('timeout', 10000);
            
            // 获取元数据
            $metadata = $producer->getMetadata(false, null, (int)$timeout);
            
            // 解析主题信息
            $topics = [];
            
            /** @var MetadataTopic $topic */
            foreach ($metadata->getTopics() as $topic) {
                // 跳过内部主题（以下划线开头）
                if (strpos($topic->getTopic(), '_') === 0) {
                    continue;
                }
                
                $partitions = [];
                foreach ($topic->getPartitions() as $partition) {
                    $partitions[] = [
                        'id' => $partition->getId(),
                        'leader' => $partition->getLeader(),
                        'replicas' => $partition->getReplicas(),
                        'isrs' => $partition->getIsrs()
                    ];
                }
                
                $topics[] = [
                    'name' => $topic->getTopic(),
                    'partitions' => $partitions,
                    'partition_count' => count($partitions),
                    'error' => $topic->getErr(),
                    'error_str' => rd_kafka_err2str($topic->getErr())
                ];
            }
            
            // 归还连接
            $pool->recycleObj($producer);
            
            return $this->success('获取主题成功', [
                'topics' => $topics,
                'total' => count($topics)
            ]);
        } catch (\Exception $e) {
            Log::error('获取Kafka主题失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('获取主题失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 创建主题
     * 
     * @param Request $request
     * @return Response
     */
    public function createTopic(Request $request)
    {
        try {
            // 权限检查（已在路由中进行）
            
            // 验证参数
            $validate = validate([
                'topic' => 'require|alphaDash',
                'partitions' => 'integer|min:1',
                'replication_factor' => 'integer|min:1'
            ], [
                'topic.require' => '主题名称不能为空',
                'topic.alphaDash' => '主题名称只能包含字母、数字、下划线和破折号',
                'partitions.integer' => '分区数必须为整数',
                'partitions.min' => '分区数必须大于等于1',
                'replication_factor.integer' => '副本因子必须为整数',
                'replication_factor.min' => '副本因子必须大于等于1'
            ]);
            
            if (!$validate->check($request->post())) {
                return $this->error($validate->getError());
            }
            
            // 获取参数
            $topic = $request->post('topic');
            $partitions = (int)$request->post('partitions', 1);
            $replicationFactor = (int)$request->post('replication_factor', 1);
            $config = $request->post('config', []);
            
            // 获取Kafka配置
            $kafkaConfig = Config::get('queue.connections.kafka', []);
            
            // 创建Admin客户端
            $conf = new Conf();
            
            // 设置broker列表
            $brokers = $kafkaConfig['brokers'] ?? 'localhost:9092';
            if (is_array($brokers)) {
                $brokers = implode(',', $brokers);
            }
            $conf->set('metadata.broker.list', $brokers);
            
            // 创建admin客户端
            $rk = new \RdKafka\Producer($conf);
            
            // 创建NewTopic对象
            $topicConfig = [];
            foreach ($config as $key => $value) {
                $topicConfig[] = $key . '=' . $value;
            }
            
            $newTopic = new \RdKafka\TopicConf();
            foreach ($topicConfig as $conf) {
                list($key, $value) = explode('=', $conf);
                $newTopic->set($key, $value);
            }
            
            $adminClient = $rk->newTopic(
                $topic,
                $partitions,
                $replicationFactor,
                $newTopic
            );
            
            // 创建主题
            $result = $adminClient->create();
            
            // 记录操作
            Log::info('创建Kafka主题', [
                'user_id' => $request->user['id'] ?? 0,
                'username' => $request->user['username'] ?? 'unknown',
                'topic' => $topic,
                'partitions' => $partitions,
                'replication_factor' => $replicationFactor,
                'config' => $config
            ]);
            
            return $this->success('创建主题成功', [
                'topic' => $topic,
                'partitions' => $partitions,
                'replication_factor' => $replicationFactor
            ]);
        } catch (\Exception $e) {
            Log::error('创建Kafka主题失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('创建主题失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 删除主题
     * 
     * @param Request $request
     * @return Response
     */
    public function deleteTopic(Request $request)
    {
        try {
            // 验证参数
            $validate = validate([
                'topic' => 'require'
            ], [
                'topic.require' => '主题名称不能为空'
            ]);
            
            if (!$validate->check($request->post())) {
                return $this->error($validate->getError());
            }
            
            // 获取参数
            $topic = $request->post('topic');
            
            // 获取Kafka配置
            $kafkaConfig = Config::get('queue.connections.kafka', []);
            
            // 创建Admin客户端
            $conf = new Conf();
            
            // 设置broker列表
            $brokers = $kafkaConfig['brokers'] ?? 'localhost:9092';
            if (is_array($brokers)) {
                $brokers = implode(',', $brokers);
            }
            $conf->set('metadata.broker.list', $brokers);
            
            // 创建admin客户端
            $rk = new \RdKafka\Producer($conf);
            
            // 删除主题
            $rk->deleteTopic($topic);
            
            // 记录操作
            Log::info('删除Kafka主题', [
                'user_id' => $request->user['id'] ?? 0,
                'username' => $request->user['username'] ?? 'unknown',
                'topic' => $topic
            ]);
            
            return $this->success('删除主题成功', [
                'topic' => $topic
            ]);
        } catch (\Exception $e) {
            Log::error('删除Kafka主题失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('删除主题失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 获取所有Broker
     * 
     * @param Request $request
     * @return Response
     */
    public function getBrokers(Request $request)
    {
        try {
            // 获取连接池
            $pool = PoolFactory::getPool('kafka');
            $producer = $pool->getObject();
            
            // 获取超时设置
            $timeout = $request->param('timeout', 10000);
            
            // 获取元数据
            $metadata = $producer->getMetadata(true, null, (int)$timeout);
            
            // 解析Broker信息
            $brokers = [];
            foreach ($metadata->getBrokers() as $broker) {
                $brokers[] = [
                    'id' => $broker->getId(),
                    'host' => $broker->getHost(),
                    'port' => $broker->getPort()
                ];
            }
            
            // 归还连接
            $pool->recycleObj($producer);
            
            return $this->success('获取Broker成功', [
                'brokers' => $brokers,
                'total' => count($brokers)
            ]);
        } catch (\Exception $e) {
            Log::error('获取Kafka Broker失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('获取Broker失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 返回成功响应
     * 
     * @param string $message 成功信息
     * @param array $data 数据
     * @return Response
     */
    protected function success(string $message, array $data = []): Response
    {
        return json([
            'code' => 200,
            'message' => $message,
            'data' => $data
        ]);
    }
    
    /**
     * 返回错误响应
     * 
     * @param string $message 错误信息
     * @param int $code 错误码
     * @param array $data 数据
     * @return Response
     */
    protected function error(string $message, int $code = 400, array $data = []): Response
    {
        return json([
            'code' => $code,
            'message' => $message,
            'data' => $data
        ]);
    }
} 