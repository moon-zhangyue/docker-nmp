<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\examples\rabbitmq\SimpleMode;
use app\examples\rabbitmq\WorkMode;
use app\examples\rabbitmq\PublishSubscribeMode;
use app\examples\rabbitmq\RoutingMode;
use app\examples\rabbitmq\TopicMode;
use app\examples\rabbitmq\RPCMode;
use think\facade\Log;
use think\Response;

/**
 * RabbitMQ示例控制器
 * 
 * 该控制器提供了测试RabbitMQ六种工作模式的接口
 */
class RabbitMQExamplesController extends BaseController
{
    /**
     * 测试简单模式
     */
    public function testSimpleMode(): Response
    {
        try {
            $simpleMode = new SimpleMode();
            $message = "这是一条测试消息 - " . date('Y-m-d H:i:s');
            $result = $simpleMode->send($message);
            
            return json([
                'code' => 0,
                'msg'  => '简单模式消息发送' . ($result ? '成功' : '失败'),
                'data' => [
                    'message' => $message,
                    'result'  => $result
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('测试简单模式失败: {error}', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return json([
                'code' => 1,
                'msg'  => '测试简单模式失败: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * 测试工作模式
     * 
     * @param int $count 任务数量
     */
    public function testWorkMode(int $count = 5): Response
    {
        try {
            $workMode = new WorkMode();
            $results = $workMode->batchSendTasks($count);
            
            return json([
                'code' => 0,
                'msg'  => '工作模式任务发送完成',
                'data' => [
                    'count'   => $count,
                    'results' => $results
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('测试工作模式失败: {error}', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return json([
                'code' => 1,
                'msg'  => '测试工作模式失败: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * 测试发布/订阅模式
     * 
     * @param int $count 消息数量
     */
    public function testPublishSubscribeMode(int $count = 3): Response
    {
        try {
            $pubSubMode = new PublishSubscribeMode();
            $results = $pubSubMode->batchPublish($count);
            
            return json([
                'code' => 0,
                'msg'  => '发布/订阅模式消息发布完成',
                'data' => [
                    'count'   => $count,
                    'results' => $results
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('测试发布/订阅模式失败: {error}', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return json([
                'code' => 1,
                'msg'  => '测试发布/订阅模式失败: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * 测试路由模式
     */
    public function testRoutingMode(): Response
    {
        try {
            $routingMode = new RoutingMode();
            $results = $routingMode->publishLogs();
            
            return json([
                'code' => 0,
                'msg'  => '路由模式消息发布完成',
                'data' => [
                    'count'   => count($results),
                    'results' => $results
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('测试路由模式失败: {error}', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return json([
                'code' => 1,
                'msg'  => '测试路由模式失败: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * 测试主题模式
     */
    public function testTopicMode(): Response
    {
        try {
            $topicMode = new TopicMode();
            $results = $topicMode->publishTopicMessages();
            
            return json([
                'code' => 0,
                'msg'  => '主题模式消息发布完成',
                'data' => [
                    'count'   => count($results),
                    'results' => $results
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('测试主题模式失败: {error}', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return json([
                'code' => 1,
                'msg'  => '测试主题模式失败: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * 测试RPC模式
     * 
     * @param string $operation 操作类型（sum, multiply, max, min）
     * @param array $numbers 数字数组
     */
    public function testRPCMode(string $operation = 'sum', array $numbers = [1, 2, 3, 4, 5]): Response
    {
        try {
            $rpcMode = new RPCMode();
            
            $data = [
                'operation' => $operation,
                'numbers'   => $numbers
            ];
            
            // 注意：这里需要先启动RPC服务端才能正常工作
            // 实际应用中，服务端应该在独立的进程中运行
            $result = $rpcMode->call($data);
            
            return json([
                'code' => 0,
                'msg'  => 'RPC模式调用完成',
                'data' => [
                    'request'  => $data,
                    'response' => $result
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('测试RPC模式失败: {error}', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return json([
                'code' => 1,
                'msg'  => '测试RPC模式失败: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * 启动RPC服务端（需要在命令行中运行）
     */
    public function startRPCServer(): void
    {
        try {
            $rpcMode = new RPCMode();
            
            echo "RPC服务端启动，等待请求...\n";
            
            $rpcMode->serve(function ($data) {
                echo "收到RPC请求: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n";
                
                // 模拟处理请求
                if (isset($data['operation']) && isset($data['numbers']) && is_array($data['numbers'])) {
                    switch ($data['operation']) {
                        case 'sum':
                            return array_sum($data['numbers']);
                        case 'multiply':
                            return array_product($data['numbers']);
                        case 'max':
                            return max($data['numbers']);
                        case 'min':
                            return min($data['numbers']);
                        default:
                            throw new \Exception("不支持的操作: " . $data['operation']);
                    }
                }
                
                throw new \Exception("无效的请求数据");
            });
        } catch (\Exception $e) {
            echo "RPC服务端启动失败: " . $e->getMessage() . "\n";
            Log::error('RPC服务端启动失败: {error}', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
} 