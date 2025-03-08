<?php
namespace app\service;

use RdKafka\Conf;
use RdKafka\Producer;
use RdKafka\KafkaConsumer;
use think\facade\Config;
use think\facade\Log;

class KafkaService
{
    private $config;
    private $producer;
    private $consumer;

    public function __construct()
    {
        $this->config = [
            'brokers' => env('KAFKA_BROKERS', 'localhost:9092'),
            'group_id' => env('KAFKA_GROUP_ID', 'user-registration-group'),
            'client_id' => env('KAFKA_CLIENT_ID', 'user-registration-client'),
        ];
    }

    /**
     * 获取生产者实例
     */
    public function getProducer(): Producer
    {
        if (!$this->producer) {
            $conf = new Conf();
            $conf->set('metadata.broker.list', $this->config['brokers']);
            $conf->set('client.id', $this->config['client_id']);
            
            $this->producer = new Producer($conf);
        }
        return $this->producer;
    }

    /**
     * 获取消费者实例
     */
    public function getConsumer(): KafkaConsumer
    {
        if (!$this->consumer) {
            $conf = new Conf();
            $conf->set('metadata.broker.list', $this->config['brokers']);
            $conf->set('group.id', $this->config['group_id']);
            $conf->set('client.id', $this->config['client_id']);
            $conf->set('auto.offset.reset', 'earliest');
            
            $this->consumer = new KafkaConsumer($conf);
        }
        return $this->consumer;
    }

    /**
     * 发送用户注册消息
     */
    public function sendUserRegistrationMessage(array $userData): void
    {
        try {
            $producer = $this->getProducer();
            $topic = $producer->newTopic('user-registration');
            
            // 将用户数据转换为JSON
            $message = json_encode($userData);
            
            // 发送消息
            $topic->produce(RD_KAFKA_PARTITION_UA, 0, $message);
            $producer->flush(10000);
            
            Log::info('User registration message sent successfully', $userData);
        } catch (\Exception $e) {
            Log::error('Failed to send user registration message: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 消费用户注册消息
     */
    public function consumeUserRegistrationMessages(callable $callback): void
    {
        try {
            $consumer = $this->getConsumer();
            $consumer->subscribe(['user-registration']);

            while (true) {
                $message = $consumer->consume(120 * 1000);
                
                switch ($message->err) {
                    case RD_KAFKA_RESP_ERR_NO_ERROR:
                        $userData = json_decode($message->payload, true);
                        call_user_func($callback, $userData);
                        break;
                    case RD_KAFKA_RESP_ERR__PARTITION_EOF:
                        Log::info('No more messages; waiting...');
                        break;
                    case RD_KAFKA_RESP_ERR__TIMED_OUT:
                        Log::info('Timed out waiting for message');
                        break;
                    default:
                        Log::error('Kafka error: ' . $message->errstr());
                        throw new \Exception($message->errstr(), $message->err);
                }
            }
        } catch (\Exception $e) {
            Log::error('Kafka consumer error: ' . $e->getMessage());
            throw $e;
        }
    }
}