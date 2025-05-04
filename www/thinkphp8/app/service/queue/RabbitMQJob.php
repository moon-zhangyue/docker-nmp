<?php

declare(strict_types=1);

namespace app\service\queue;

use think\queue\Job;

/**
 * RabbitMQ任务类
 * 
 * 该类用于模拟ThinkPHP队列任务对象，处理RabbitMQ消息
 * 
 * @package app\service\queue
 */
class RabbitMQJob extends Job
{
    /**
     * 消息载荷
     * 
     * @var array
     */
    protected $payload;
    
    /**
     * 原始消息对象
     * 
     * @var mixed
     */
    protected $message;
    
    /**
     * 构造函数
     * 
     * @param array $payload 消息载荷
     * @param mixed $message 原始消息对象
     */
    public function __construct(array $payload, $message)
    {
        $this->payload = $payload;
        $this->message = $message;
        $this->app = app();
    }
    
    /**
     * 获取任务ID
     * 
     * @return string
     */
    public function getJobId()
    {
        return $this->payload['id'] ?? '';
    }
    
    /**
     * 获取任务尝试次数
     * 
     * @return int
     */
    public function attempts()
    {
        return $this->payload['attempts'] ?? 1;
    }
    
    /**
     * 获取原始消息内容
     * 
     * @return string
     */
    public function getRawBody()
    {
        return json_encode($this->payload);
    }
    
    /**
     * 删除任务（确认消息已处理）
     * 
     * @return void
     */
    public function delete()
    {
        // 消息会在回调返回true时自动确认
    }
    
    /**
     * 重新发布任务
     * 
     * @param int $delay 延迟时间（秒）
     * @return void
     */
    public function release($delay = 0)
    {
        // 消息会在回调返回false时自动拒绝并重新入队
        // $delay 参数在此处不使用，但保留以符合接口要求
    }
    
    /**
     * 获取原始消息对象
     * 
     * @return mixed
     */
    public function getMessage()
    {
        return $this->message;
    }
    
    /**
     * 获取消息载荷
     * 
     * @return array
     */
    public function getPayload()
    {
        return $this->payload;
    }
}
