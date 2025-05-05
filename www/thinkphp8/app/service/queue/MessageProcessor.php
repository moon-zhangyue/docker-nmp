<?php

declare(strict_types=1);

namespace app\service\queue;

use think\console\Output;
use think\facade\Log;
use think\facade\Cache;

/**
 * 消息处理器
 *
 * 负责处理RabbitMQ消息并执行相应的任务
 *
 * @package app\service\queue
 */
class MessageProcessor
{
    /**
     * 控制台输出对象
     *
     * @var Output
     */
    protected $output;

    /**
     * 构造函数
     *
     * @param Output $output 控制台输出对象
     */
    public function __construct(Output $output)
    {
        $this->output = $output;
    }

    /**
     * 处理消息
     *
     * @param array $body 消息体
     * @param mixed $message 原始消息对象
     * @return bool 处理结果，true表示成功，false表示失败
     */
    public function process(array $body, $message): bool
    {
        $startTime = microtime(true);
        $jobId     = $body['id'] ?? uniqid('msg_', true);

        $this->output->writeln('');
        $this->output->info("收到消息 [{$jobId}]");

        // 记录详细消息内容（仅在调试模式下）
        if (config('app.debug')) {
            $this->output->comment('消息内容: ' . json_encode($body, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }

        try {
            // 验证消息格式
            if (!$this->validateMessage($body)) {
                $this->recordMetrics('invalid', $startTime);
                return false;
            }

            // 提取任务信息
            $jobClass = $body['job'];
            $data     = $body['data'] ?? [];

            $this->output->info("处理任务: {$jobClass}");

            // 创建任务处理类实例
            $jobInstance = $this->createJobInstance($jobClass);

            // 创建任务对象
            $job = new RabbitMQJob($body, $message);

            // 执行任务前的钩子
            $this->beforeJobExecution($job, $data);

            // 执行任务
            $jobInstance->fire($job, $data);

            // 执行任务后的钩子
            $this->afterJobExecution($job, $data);

            $processingTime = round((microtime(true) - $startTime) * 1000, 2);
            $this->output->info("任务处理完成，耗时: {$processingTime}ms");

            // 记录成功指标
            $this->recordMetrics('success', $startTime);

            return true;
        } catch (\Throwable $e) {
            $this->handleException($e, $body);

            // 记录失败指标
            $this->recordMetrics('failed', $startTime);

            return false;
        }
    }

    /**
     * 任务执行前的钩子方法
     *
     * @param RabbitMQJob $job 任务对象
     * @param array $data 任务数据
     * @return void
     */
    protected function beforeJobExecution(RabbitMQJob $job, array $data): void
    {
        // 这是一个钩子方法，在子类中重写以实现自定义逻辑
        // 参数在基类中未使用，但在子类中可能会用到
    }

    /**
     * 任务执行后的钩子方法
     *
     * @param RabbitMQJob $job 任务对象
     * @param array $data 任务数据
     * @return void
     */
    protected function afterJobExecution(RabbitMQJob $job, array $data): void
    {
        // 这是一个钩子方法，在子类中重写以实现自定义逻辑
        // 参数在基类中未使用，但在子类中可能会用到
    }

    /**
     * 记录处理指标
     *
     * @param string $status 处理状态
     * @param float $startTime 开始时间
     * @return void
     */
    protected function recordMetrics(string $status, float $startTime): void
    {
        try {
            $processingTime = round((microtime(true) - $startTime) * 1000, 2);

            // 从缓存中获取当前指标数据
            $metrics = Cache::get('rabbitmq_metrics', []);

            // 初始化指标数据
            if (!isset($metrics[$status])) {
                $metrics[$status] = [
                    'count'      => 0,
                    'total_time' => 0,
                    'avg_time'   => 0,
                    'min_time'   => PHP_FLOAT_MAX,
                    'max_time'   => 0
                ];
            }

            // 更新指标
            $metrics[$status]['count']++;
            $metrics[$status]['total_time'] += $processingTime;
            $metrics[$status]['avg_time']     = $metrics[$status]['total_time'] / $metrics[$status]['count'];
            $metrics[$status]['min_time']     = min($metrics[$status]['min_time'], $processingTime);
            $metrics[$status]['max_time']     = max($metrics[$status]['max_time'], $processingTime);
            $metrics[$status]['last_updated'] = time();

            // 保存更新后的指标数据到缓存
            Cache::set('rabbitmq_metrics', $metrics);
        } catch (\Throwable $e) {
            // 记录错误但不中断处理流程
            Log::error('记录RabbitMQ指标失败: {error}-{trace}', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * 验证消息格式
     *
     * @param array $body 消息体
     * @return bool 验证结果
     */
    protected function validateMessage(array $body): bool
    {
        if (empty($body['job'])) {
            $this->output->error('消息中缺少job字段');
            return false;
        }

        return true;
    }

    /**
     * 创建任务处理类实例
     *
     * @param string $jobClass 任务类名
     * @return object 任务处理类实例
     */
    protected function createJobInstance(string $jobClass)
    {
        return app()->make($jobClass);
    }

    /**
     * 处理异常
     *
     * @param \Throwable $e 异常对象
     * @param array $context 上下文信息
     * @return void
     */
    protected function handleException(\Throwable $e, array $context = []): void
    {
        $this->output->error("处理消息异常: " . $e->getMessage());

        $logData = [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ];

        // 添加上下文信息
        if (!empty($context)) {
            $logData['context'] = $context;
        }

        Log::error('处理消息异常: {error}，任务ID: {job_id}，上下文: {context}, 堆栈跟踪: {trace}', array_merge($logData, [
            'job_id' => $context['id'] ?? '未知'
        ]));
    }
}
