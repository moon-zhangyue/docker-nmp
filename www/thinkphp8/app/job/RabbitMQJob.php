<?php
declare(strict_types=1);

namespace app\job;

use think\facade\Log;
use think\queue\Job;

/**
 * RabbitMQ示例任务处理类
 * 
 * 该类用于处理RabbitMQ队列中的任务
 * 
 * @package app\job
 */
class RabbitMQJob
{
    /**
     * 任务处理方法
     *
     * @param Job $job 任务对象
     * @param array $data 任务数据
     * @return void
     */
    public function fire(Job $job, $data): void
    {
        try {
            // 记录任务开始处理
            Log::info('RabbitMQJob开始处理：job_id={job_id}，data={data}', [
                'job_id' => $job->getJobId(),
                'data'   => $data
            ]);

            // 根据任务类型执行不同的处理逻辑
            $taskType = $data['task_type'] ?? 'default';
            switch ($taskType) {
                case 'process_data':
                    $this->processData($data);
                    break;

                case 'send_notification':
                    $this->sendNotification($data);
                    break;

                case 'generate_report':
                    $this->generateReport($data);
                    break;

                default:
                    // 默认处理逻辑
                    $this->defaultProcess($data);
                    break;
            }

            // 标记任务为已完成
            $job->delete();

            // 记录任务完成
            Log::info('RabbitMQJob处理完成:job_id={job_id}， execution_time={execution_time}', [
                'job_id'         => $job->getJobId(),
                'execution_time' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            // 记录异常
            Log::error('RabbitMQJob处理异常:'.json_encode([
                'error'  => $e->getMessage(),
                'trace'  => $e->getTraceAsString(),
                'job_id' => $job->getJobId(),
                'data'   => $data
            ],JSON_UNESCAPED_UNICODE));

            // 如果有尝试次数，延迟重试
            $attempts = $job->attempts();
            if ($attempts < 3) {
                // 重试间隔成倍增加
                $delay = pow(2, $attempts);
                $job->release($delay);

                Log::info('任务将延迟重试:'.json_encode([
                    'job_id'   => $job->getJobId(),
                    'attempts' => $attempts,
                    'delay'    => $delay
                ],JSON_UNESCAPED_UNICODE));
            } else {
                // 尝试次数过多，删除任务
                $job->delete();

                Log::error('任务重试次数过多，已删除'.json_encode([
                    'job_id'   => $job->getJobId(),
                    'attempts' => $attempts
                ],JSON_UNESCAPED_UNICODE));
            }
        }
    }

    /**
     * 处理数据任务
     *
     * @param array $data 任务数据
     * @return void
     */
    protected function processData(array $data): void
    {
        // 模拟数据处理
        Log::info('处理数据:数据量={data_size}, 处理时间={processing_time}', [
            'data_size'       => count($data),
            'processing_time' => date('Y-m-d H:i:s')
        ]);

        // 睡眠一小段时间模拟处理
        sleep(1);
    }

    /**
     * 发送通知任务
     *
     * @param array $data 任务数据
     * @return void
     */
    protected function sendNotification(array $data): void
    {
        // 模拟发送通知
        $recipients = $data['recipients'] ?? [];
        $message    = $data['message'] ?? '默认通知消息';
        $type       = $data['type'] ?? 'email';

        Log::info('发送通知：类型={type}, 接收人数={recipients_count}, 消息长度={message_length}, 发送时间={send_time}', [
            'type'             => $type,
            'recipients_count' => count($recipients),
            'message_length'   => strlen($message),
            'send_time'        => date('Y-m-d H:i:s')
        ]);

        // 睡眠一小段时间模拟处理
        sleep(1);
    }

    /**
     * 生成报告任务
     *
     * @param array $data 任务数据
     * @return void
     */
    protected function generateReport(array $data): void
    {
        // 模拟生成报告
        $reportType = $data['report_type'] ?? 'default';
        $period     = $data['period'] ?? 'monthly';

        Log::info('生成报告：类型={report_type}, 时间段={period}, 生成时间={generation_time}', [
            'report_type'     => $reportType,
            'period'          => $period,
            'generation_time' => date('Y-m-d H:i:s')
        ]);

        // 睡眠一小段时间模拟处理
        sleep(2);
    }

    /**
     * 默认处理任务
     *
     * @param array $data 任务数据
     * @return void
     */
    protected function defaultProcess(array $data): void
    {
        // 默认任务处理逻辑
        Log::info('执行默认任务处理：数据={data}, 执行时间={execution_time}', [
            'data'           => $data,
            'execution_time' => date('Y-m-d H:i:s')
        ]);

        // 睡眠一小段时间模拟处理
        sleep(1);
    }

    /**
     * 任务失败处理
     * 
     * @param array $data 任务数据
     * @return void
     */
    public function failed($data): void
    {
        // 记录任务失败
        Log::error('RabbitMQJob任务最终失败: 数据={data}, 失败时间={failure_time}', [
            'data'         => $data,
            'failure_time' => date('Y-m-d H:i:s')
        ]);
    }
}
