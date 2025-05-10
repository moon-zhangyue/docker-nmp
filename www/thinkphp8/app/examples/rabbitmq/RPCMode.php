<?php
declare(strict_types=1);

namespace app\examples\rabbitmq;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use think\facade\Log;

/**
 * RabbitMQ RPC模式示例
 * 
 * RPC（远程过程调用）模式允许客户端发送请求并等待响应。
 * 客户端发送带有回调队列和关联ID的消息，服务端处理后将结果发送回回调队列。
 * 客户端通过关联ID将响应与请求匹配。
 */
class RPCMode
{
    /**
     * RPC队列名称
     */
    protected $rpcQueue = 'rpc_queue';

    /**
     * 连接配置
     */
    protected $config = [];

    /**
     * 构造函数
     */
    public function __construct()
    {
        // 从配置文件中获取RabbitMQ连接信息
        $this->config = [
            'host'     => config('queue.connections.rabbitmq.host', 'rabbitmq'),
            'port'     => config('queue.connections.rabbitmq.port', 5672),
            'user'     => config('queue.connections.rabbitmq.login', 'myuser'),
            'password' => config('queue.connections.rabbitmq.password', 'mypass'),
            'vhost'    => config('queue.connections.rabbitmq.vhost', '/'),
        ];
    }

    /**
     * 客户端：发送RPC请求并等待响应
     *
     * @param mixed $data 请求数据
     * @param int $timeout 超时时间（秒）
     * @return mixed 响应结果
     * @throws \Exception 超时或其他错误时抛出异常
     */
    public function call($data, int $timeout = 10)
    {
        try {
            // 创建连接
            $connection = new AMQPStreamConnection(
                $this->config['host'],
                $this->config['port'],
                $this->config['user'],
                $this->config['password'],
                $this->config['vhost']
            );

            // 创建通道
            $channel = $connection->channel();

            // 声明RPC队列
            $channel->queue_declare(
                $this->rpcQueue, // 队列名称
                false,          // passive
                true,           // durable（持久化）
                false,          // exclusive
                false           // auto delete
            );

            // 创建回调队列（临时队列，由RabbitMQ自动生成名称）
            list($callbackQueue, , ) = $channel->queue_declare(
                "",    // 队列名称为空，由RabbitMQ自动生成
                false, // passive
                false, // durable（非持久化）
                true,  // exclusive（排他性队列，仅限此连接使用）
                true   // auto delete（自动删除）
            );

            // 生成唯一的关联ID
            $correlationId = uniqid('rpc_');

            // 响应结果
            $response = null;

            // 设置消费者回调函数，用于接收响应
            $channel->basic_consume(
                $callbackQueue,    // 队列名称
                '',                // consumer tag
                false,             // no local
                true,              // no ack（自动确认）
                false,             // exclusive
                false,             // no wait
                function (AMQPMessage $message) use (&$response, $correlationId) {
                    // 检查关联ID是否匹配
                    if ($message->get('correlation_id') === $correlationId) {
                        $response = $message->body;
                    }
                }
            );

            // 准备请求数据
            $requestData = json_encode([
                'data'      => $data,
                'timestamp' => time()
            ]);

            // 创建请求消息
            $msg = new AMQPMessage(
                $requestData,
                [
                    'correlation_id' => $correlationId, // 关联ID，用于匹配请求和响应
                    'reply_to'       => $callbackQueue, // 回调队列，服务端将响应发送到此队列
                    'delivery_mode'  => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                    'content_type'   => 'application/json',
                    'timestamp'      => time()
                ]
            );

            // 发布请求消息到RPC队列
            $channel->basic_publish($msg, '', $this->rpcQueue);

            Log::info('RPC模式 - 客户端发送请求: {data}, 关联ID: {correlation_id}', [
                'data'           => $requestData,
                'correlation_id' => $correlationId
            ]);

            // 计算超时时间
            $endTime = time() + $timeout;

            // 等待响应或超时
            while ($response === null && time() < $endTime) {
                // 处理通道事件
                $channel->wait(null, false, 1);
            }

            // 如果超时未收到响应，则抛出异常
            if ($response === null) {
                throw new \Exception("RPC请求超时，未收到响应");
            }

            Log::info('RPC模式 - 客户端收到响应: {response}, 关联ID: {correlation_id}', [
                'response'       => $response,
                'correlation_id' => $correlationId
            ]);

            // 关闭通道和连接
            $channel->close();
            $connection->close();

            // 返回响应结果（解码JSON）
            return json_decode($response, true);
        } catch (\Exception $e) {
            Log::error('RPC模式 - 客户端请求失败: {error}', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * 服务端：处理RPC请求并返回响应
     *
     * @param callable $callback 回调函数，用于处理请求并返回结果
     * @return void
     */
    public function serve(callable $callback): void
    {
        try {
            // 创建连接
            $connection = new AMQPStreamConnection(
                $this->config['host'],
                $this->config['port'],
                $this->config['user'],
                $this->config['password'],
                $this->config['vhost']
            );

            // 创建通道
            $channel = $connection->channel();

            // 声明RPC队列
            $channel->queue_declare(
                $this->rpcQueue, // 队列名称
                false,          // passive
                true,           // durable（持久化）
                false,          // exclusive
                false           // auto delete
            );

            // 设置每次只接收一条消息
            $channel->basic_qos(0, 1, null);

            Log::info('RPC模式 - 服务端等待请求...');

            // 消费消息
            $channel->basic_consume(
                $this->rpcQueue,     // 队列名称
                '',                  // consumer tag
                false,               // no local
                false,               // no ack（设为false，需要手动确认）
                false,               // exclusive
                false,               // no wait
                function (AMQPMessage $request) use ($callback, $channel) {
                    // 获取关联ID和回调队列
                    $correlationId = $request->get('correlation_id');
                    $replyTo       = $request->get('reply_to');

                    // 解析请求数据
                    $requestData = json_decode($request->body, true);

                    Log::info('RPC模式 - 服务端收到请求: {data}, 关联ID: {correlation_id}', [
                        'data'           => $request->body,
                        'correlation_id' => $correlationId
                    ]);

                    try {
                        // 调用回调函数处理请求
                        $startTime     = microtime(true);
                        $result        = call_user_func($callback, $requestData['data'] ?? null);
                        $executionTime = microtime(true) - $startTime;

                        // 准备响应数据
                        $responseData = json_encode([
                            'result'         => $result,
                            'execution_time' => round($executionTime, 4),
                            'timestamp'      => time()
                        ]);

                        // 创建响应消息
                        $response = new AMQPMessage(
                            $responseData,
                            [
                                'correlation_id' => $correlationId, // 使用相同的关联ID
                                'content_type'   => 'application/json',
                                'timestamp'      => time()
                            ]
                        );

                        // 发布响应消息到回调队列
                        $channel->basic_publish($response, '', $replyTo);

                        Log::info('RPC模式 - 服务端发送响应: {response}, 关联ID: {correlation_id}, 耗时: {time}秒', [
                            'response'       => $responseData,
                            'correlation_id' => $correlationId,
                            'time'           => round($executionTime, 4)
                        ]);

                        // 确认请求消息已处理
                        $request->ack();
                    } catch (\Exception $e) {
                        // 处理异常，返回错误响应
                        $errorResponse = json_encode([
                            'error'     => $e->getMessage(),
                            'timestamp' => time()
                        ]);

                        // 创建错误响应消息
                        $response = new AMQPMessage(
                            $errorResponse,
                            [
                                'correlation_id' => $correlationId, // 使用相同的关联ID
                                'content_type'   => 'application/json',
                                'timestamp'      => time()
                            ]
                        );

                        // 发布错误响应消息到回调队列
                        $channel->basic_publish($response, '', $replyTo);

                        Log::error('RPC模式 - 服务端处理请求失败: {error}, 关联ID: {correlation_id}', [
                            'error'          => $e->getMessage(),
                            'correlation_id' => $correlationId,
                            'trace'          => $e->getTraceAsString()
                        ]);

                        // 确认请求消息已处理（即使处理失败）
                        $request->ack();
                    }
                }
            );

            // 持续等待消息，直到连接关闭
            while ($channel->is_consuming()) {
                $channel->wait();
            }

            // 关闭通道和连接
            $channel->close();
            $connection->close();
        } catch (\Exception $e) {
            Log::error('RPC模式 - 服务端启动失败: {error}', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * 使用示例
     */
    public function example(): void
    {
        // 服务端示例（在单独的进程中运行）
        // $this->serve(function ($data) {
        //     // 模拟处理请求
        //     if (isset($data['operation']) && isset($data['numbers']) && is_array($data['numbers'])) {
        //         switch ($data['operation']) {
        //             case 'sum':
        //                 return array_sum($data['numbers']);
        //             case 'multiply':
        //                 return array_product($data['numbers']);
        //             case 'max':
        //                 return max($data['numbers']);
        //             case 'min':
        //                 return min($data['numbers']);
        //             default:
        //                 throw new \Exception("不支持的操作: " . $data['operation']);
        //         }
        //     }
        //     
        //     throw new \Exception("无效的请求数据");
        // });

        // 客户端示例
        try {
            // 求和操作
            $sumResult = $this->call([
                'operation' => 'sum',
                'numbers'   => [1, 2, 3, 4, 5]
            ]);

            echo "求和结果: " . ($sumResult['result'] ?? 'N/A') . "\n";

            // 乘积操作
            $multiplyResult = $this->call([
                'operation' => 'multiply',
                'numbers'   => [2, 3, 4]
            ]);

            echo "乘积结果: " . ($multiplyResult['result'] ?? 'N/A') . "\n";

            // 最大值操作
            $maxResult = $this->call([
                'operation' => 'max',
                'numbers'   => [10, 5, 8, 15, 3]
            ]);

            echo "最大值结果: " . ($maxResult['result'] ?? 'N/A') . "\n";
        } catch (\Exception $e) {
            echo "RPC调用失败: " . $e->getMessage() . "\n";
        }
    }
}