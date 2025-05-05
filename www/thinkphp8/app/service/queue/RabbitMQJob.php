<?php

declare(strict_types=1);

namespace app\service\queue;

use think\queue\Job;
use think\facade\Log;

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
     * 任务开始处理的时间
     *
     * @var float
     */
    protected $startTime;

    /**
     * 任务处理状态
     *
     * @var string
     */
    protected $status = 'pending';

    /**
     * 构造函数
     *
     * @param array $payload 消息载荷
     * @param mixed $message 原始消息对象
     */
    public function __construct(array $payload, $message)
    {
        $this->payload   = $payload;
        $this->message   = $message;
        $this->app       = app();
        $this->startTime = microtime(true);

        // 确保payload中包含必要的字段
        $this->ensurePayloadFields();
    }

    /**
     * 确保payload中包含必要的字段
     *
     * @return void
     */
    protected function ensurePayloadFields(): void
    {
        // 确保有ID字段
        if (empty($this->payload['id'])) {
            $this->payload['id'] = uniqid('job_', true);
        }

        // 确保有attempts字段
        if (!isset($this->payload['attempts'])) {
            $this->payload['attempts'] = 1;
        }

        // 确保有created_at字段
        if (empty($this->payload['created_at'])) {
            $this->payload['created_at'] = date('Y-m-d H:i:s');
        }
    }

    /**
     * 获取任务ID
     *
     * @return string
     */
    public function getJobId(): string
    {
        return $this->payload['id'] ?? '';
    }

    /**
     * 获取任务尝试次数
     *
     * @return int
     */
    public function attempts(): int
    {
        return (int) ($this->payload['attempts'] ?? 1);
    }

    /**
     * 获取原始消息内容
     *
     * @return string
     */
    public function getRawBody(): string
    {
        return json_encode($this->payload, JSON_UNESCAPED_UNICODE);
    }

    /**
     * 删除任务（确认消息已处理）
     *
     * @return void
     */
    public function delete(): void
    {
        // 消息会在回调返回true时自动确认
        $this->status = 'completed';
        $this->logJobStatus('completed');
    }

    /**
     * 重新发布任务
     *
     * @param int $delay 延迟时间（秒）
     * @return void
     */
    public function release($delay = 0): void
    {
        // 消息会在回调返回false时自动拒绝并重新入队
        $this->status = 'released';
        $this->logJobStatus('released', ['delay' => $delay]);
    }

    /**
     * 标记任务为失败
     *
     * @return void
     */
    public function fail(): void
    {
        parent::markAsFailed();
        $this->status = 'failed';
        $this->logJobStatus('failed');
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
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * 获取任务处理状态
     *
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * 获取任务处理时间（毫秒）
     *
     * @return float
     */
    public function getProcessingTime(): float
    {
        return round((microtime(true) - $this->startTime) * 1000, 2);
    }

    /**
     * 记录任务状态日志
     *
     * @param string $status 状态
     * @param array $extra 额外信息
     * @return void
     */
    protected function logJobStatus(string $status, array $extra = []): void
    {
        $processingTime = $this->getProcessingTime();
        $logData        = [
            'job_id'             => $this->getJobId(),
            'status'             => $status,
            'attempts'           => $this->attempts(),
            'processing_time_ms' => $processingTime,
            'queue'              => $this->payload['queue'] ?? 'default',
            'job_class'          => $this->payload['job'] ?? '',
            'timestamp'          => date('Y-m-d H:i:s')
        ];

        if (!empty($extra)) {
            $logData = array_merge($logData, $extra);
        }

        // 只在调试模式下记录详细信息
        if (config('app.debug')) {
            $logData['payload'] = $this->payload;
        }

        // 根据状态选择日志级别
        switch ($status) {
            case 'failed':
                Log::error('RabbitMQ任务失败 - ID: {job_id}, 尝试次数: {attempts}, 处理时间: {processing_time_ms}ms', $logData);
                break;
            case 'released':
                Log::warning('RabbitMQ任务重新入队 - ID: {job_id}, 尝试次数: {attempts}, 延迟: {delay}秒', $logData);
                break;
            case 'completed':
                Log::info('RabbitMQ任务完成 - ID: {job_id}, 处理时间: {processing_time_ms}ms', $logData);
                break;
            default:
                Log::info('RabbitMQ任务状态变更: {status} - ID: {job_id}', array_merge($logData, ['status' => $status]));
                break;
        }
    }
}
