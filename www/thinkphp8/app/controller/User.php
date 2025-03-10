<?php

namespace app\controller;

use app\BaseController;
use app\service\UserService;
use think\facade\Log;
use think\Request;
use think\facade\Queue;
use think\response\Json;  // 添加这行引入

class User extends BaseController
{
    private $userService;

    public function __construct()
    {
        // parent::__construct();
        $this->userService = new UserService();
    }

    /**
     * 用户注册接口
     */
    public function register(Request $request)
    {
        try {
            $data = $request->post();

            // 调用注册服务
            $this->userService->register($data);

            return json([
                'code' => 0,
                'msg' => 'Registration request received successfully',
                'data' => null
            ]);
        } catch (\Exception $e) {
            Log::error('Registration error: {message}', ['message' => $e->getMessage()]);

            return json([
                'code' => 1,
                'msg' => $e->getMessage(),
                'data' => null
            ]);
        }
    }

    /**
     * 测试redis队列
     */
    public function redis_queue(Request $request)
    {
        try {
            $data = ['order_id' => random_int('10000', '99999'), 'user_id' => random_int('10000', '99999')];

            // 推送任务到 Redis 队列
            $res = Queue::push('app\job\RedisTask', $data);

            return json([
                'code' => 200,
                'msg' => 'redis_queue push success!',
                'data' => $res
            ]);
        } catch (\Exception $e) {
            Log::error('redis_queue push error: {message}', ['message' => $e->getMessage()]);

            return json([
                'code' => 1,
                'msg' => $e->getMessage(),
                'data' => null
            ]);
        }
    }
    /**
     * Kafka消息队列测试
     */
    public function kafka_queue(Request $request)
    {
        try {
            $data = [
                'user_id' => random_int(10000, 99999),
                'message' => 'Test Kafka message',
                'timestamp' => time()
            ];

            // 推送任务到Kafka队列
            $isSuccess = Queue::push('app\job\KafkaTask', $data, 'kafka');

            if ($isSuccess) {
                return json([
                    'code' => 0,
                    'msg' => 'Message pushed to Kafka queue successfully',
                    'data' => [
                        'success' => $isSuccess,
                        'data' => $data
                    ]
                ]);
            } else {
                Log::error('Kafka queue push error');

                return json([
                    'code' => 1,
                    'msg' => 'Kafka queue push error',
                    'data' => null
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Kafka queue push error: {message}', ['message' => $e->getMessage()]);

            return json([
                'code' => 1,
                'msg' => $e->getMessage(),
                'data' => null
            ]);
        }
    }
}
