<?php

declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\service\queue\RabbitMQProducer;
use think\App;
use think\facade\Log;
use think\Response;
use think\Request;

/**
 * RabbitMQ测试控制器
 *
 * 该控制器提供了测试RabbitMQ队列功能的接口
 *
 * @package app\controller
 */
class RabbitMQTest extends BaseController
{
    /**
     * RabbitMQ生产者实例
     *
     * @var RabbitMQProducer
     */
    protected $producer;

    /**
     * 构造函数
     *
     * @param App $app 应用实例
     */
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->producer = new RabbitMQProducer();
    }

    /**
     * 发送消息到队列
     *
     * @return Response
     */
    public function send()
    {
        $data = [
            'id'         => uniqid(),
            'name'       => 'test job',
            'data'       => ['key' => 'value'],
            'task_type'  => 'default',
            'created_at' => date('Y-m-d H:i:s')
        ];

        try {
            // 推送任务到队列
            $result = $this->producer->send('app\job\RabbitMQJob', $data);

            return json([
                'code' => 0,
                'msg'  => '消息已发送到队列',
                'data' => [
                    'job_id'   => $result,
                    'job_data' => $data
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('发送消息到队列失败: {error}', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return json([
                'code' => 1,
                'msg'  => '发送消息失败: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * 发送延迟消息到队列
     *
     * @return Response
     */
    public function sendDelayed()
    {
        $data = [
            'id'         => uniqid(),
            'name'       => 'delayed job',
            'data'       => ['key' => 'value'],
            'task_type'  => 'default',
            'created_at' => date('Y-m-d H:i:s')
        ];

        $delay = 10; // 延迟10秒

        try {
            // 推送延迟任务到队列
            $result = $this->producer->sendLater($delay, 'app\job\RabbitMQJob', $data);

            return json([
                'code' => 0,
                'msg'  => '延迟消息已发送到队列',
                'data' => [
                    'job_id'   => $result,
                    'job_data' => $data,
                    'delay'    => $delay
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('发送延迟消息到队列失败: {error}, 延迟: {delay}秒', [
                'error' => $e->getMessage(),
                'delay' => $delay,
                'trace' => $e->getTraceAsString()
            ]);

            return json([
                'code' => 1,
                'msg'  => '发送延迟消息失败: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * 发送不同类型的任务到队列
     *
     * @param string $type 任务类型
     * @return Response
     */
    public function sendTaskType(Request $request)
    {
        $type = $request->param('type', 'default');

        $validTypes = ['default', 'process_data', 'send_notification', 'generate_report'];

        if (!in_array($type, $validTypes)) {
            return json([
                'code' => 1,
                'msg'  => '无效的任务类型，有效类型: ' . implode(', ', $validTypes)
            ]);
        }

        $data = [
            'id'         => uniqid(),
            'name'       => $type . ' job',
            'task_type'  => $type,
            'created_at' => date('Y-m-d H:i:s')
        ];

        // 根据任务类型添加特定数据
        switch ($type) {
            case 'process_data':
                $data['data_items'] = [
                    ['id' => 1, 'value' => 'item1'],
                    ['id' => 2, 'value' => 'item2'],
                    ['id' => 3, 'value' => 'item3'],
                ];
                break;

            case 'send_notification':
                $data['recipients'] = ['user1@example.com', 'user2@example.com'];
                $data['message'] = '这是一条测试通知';
                $data['type'] = 'email';
                break;

            case 'generate_report':
                $data['report_type'] = 'sales';
                $data['period'] = 'monthly';
                $data['parameters'] = [
                    'start_date' => date('Y-m-01'),
                    'end_date'   => date('Y-m-t')
                ];
                break;
        }

        try {
            // 推送任务到队列
            $result = $this->producer->send('app\job\RabbitMQJob', $data);

            return json([
                'code' => 0,
                'msg'  => $type . '类型的任务已发送到队列',
                'data' => [
                    'job_id'   => $result,
                    'job_data' => $data
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('发送任务到队列失败: {error}, 任务类型: {type}', [
                'type'  => $type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return json([
                'code' => 1,
                'msg'  => '发送任务失败: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * 批量发送消息到队列
     *
     * @param int $count 消息数量
     * @return Response
     */
    public function batchSend(Request $request)
    {
        $count = $request->param('count', 10);

        if ($count <= 0 || $count > 100) {
            return json([
                'code' => 1,
                'msg'  => '消息数量必须在1-100之间'
            ]);
        }

        $messages = [];
        for ($i = 0; $i < $count; $i++) {
            $messages[] = [
                'job'  => 'app\job\RabbitMQJob',
                'data' => [
                    'id'          => uniqid(),
                    'name'        => 'batch job ' . ($i + 1),
                    'task_type'   => 'default',
                    'batch_index' => $i,
                    'created_at'  => date('Y-m-d H:i:s')
                ]
            ];
        }

        try {
            // 批量推送任务到队列
            $results = $this->producer->batchSend($messages);

            $successCount = count(array_filter($results));

            return json([
                'code' => 0,
                'msg'  => "批量发送消息完成，成功: {$successCount}，失败: " . ($count - $successCount),
                'data' => [
                    'total'   => $count,
                    'success' => $successCount,
                    'results' => $results
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('批量发送消息到队列失败: {error}, 消息数量: {count}', [
                'error' => $e->getMessage(),
                'count' => $count,
                'trace' => $e->getTraceAsString()
            ]);

            return json([
                'code' => 1,
                'msg'  => '批量发送消息失败: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * 获取队列长度
     *
     * @param string $queue 队列名称
     * @return Response
     */
    public function getQueueSize(?string $queue = null)
    {
        try {
            $size = $this->producer->getQueueSize($queue);

            return json([
                'code' => 0,
                'msg'  => '获取队列长度成功',
                'data' => [
                    'queue' => $queue ?: 'default',
                    'size'  => $size
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('获取队列长度失败: {error}, 队列: {queue}', [
                'queue' => $queue ?: 'default',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return json([
                'code' => 1,
                'msg'  => '获取队列长度失败: ' . $e->getMessage()
            ]);
        }
    }
}
