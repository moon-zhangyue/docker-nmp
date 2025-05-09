<?php
declare(strict_types=1);

namespace app\examples\rabbitmq;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use think\facade\Log;

/**
 * RabbitMQ工作模式示例
 * 
 * 工作模式是一个生产者对应多个消费者，每个消费者获取到的消息是唯一的，
 * 多个消费者共同消费同一个队列中的消息，实现任务的分发和负载均衡
 */
class WorkMode
{
    /**
     * 队列名称
     */
    protected $queueName = 'work_queue';
    
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
     * 生产者：发送任务消息
     *
     * @param string $message 消息内容
     * @param int $complexity 任务复杂度（模拟处理时间）
     * @return bool
     */
    public function sendTask(string $message, int $complexity = 1): bool
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
            
            // 声明队列
            $channel->queue_declare(
                $this->queueName, // 队列名称
                false,           // passive
                true,            // durable（持久化）
                false,           // exclusive
                false            // auto delete
            );
            
            // 创建消息，包含任务复杂度信息
            $data = json_encode([
                'message'    => $message,
                'complexity' => $complexity,
                'timestamp'  => time()
            ]);
            
            $msg = new AMQPMessage(
                $data,
                [
                    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT, // 消息持久化
                    'content_type'  => 'application/json',
                ]
            );
            
            // 发布消息到队列
            $channel->basic_publish($msg, '', $this->queueName);
            
            Log::info('工作模式 - 任务已发送: {message}, 复杂度: {complexity}', [
                'message'    => $message,
                'complexity' => $complexity
            ]);
            
            // 关闭通道和连接
            $channel->close();
            $connection->close();
            
            return true;
        } catch (\Exception $e) {
            Log::error('工作模式 - 发送任务失败: {error}', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return false;
        }
    }
    
    /**
     * 消费者：处理任务
     *
     * @param string $workerName 工作者名称
     * @param callable $callback 回调函数，用于处理消息
     * @return void
     */
    public function worker(string $workerName, callable $callback): void
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
            
            // 声明队列
            $channel->queue_declare(
                $this->queueName, // 队列名称
                false,           // passive
                true,            // durable（持久化）
                false,           // exclusive
                false            // auto delete
            );
            
            // 设置预取计数为1，确保在工作进程处理完当前消息前不会接收新消息
            // 这样可以根据消费者的处理能力分发消息，避免某个消费者负载过重
            $channel->basic_qos(null, 1, null);
            
            Log::info('工作模式 - 工作者 {worker} 等待任务...', ['worker' => $workerName]);
            
            // 消费消息
            $channel->basic_consume(
                $this->queueName,        // 队列名称
                '',                      // consumer tag
                false,                   // no local
                false,                   // no ack（设为false，需要手动确认）
                false,                   // exclusive
                false,                   // no wait
                function (AMQPMessage $message) use ($callback, $workerName) {
                    $data = json_decode($message->body, true);
                    
                    Log::info('工作模式 - 工作者 {worker} 接收到任务: {message}, 复杂度: {complexity}', [
                        'worker'     => $workerName,
                        'message'    => $data['message'] ?? 'unknown',
                        'complexity' => $data['complexity'] ?? 1
                    ]);
                    
                    // 调用回调函数处理消息
                    $startTime = microtime(true);
                    $result = call_user_func($callback, $data, $workerName);
                    $executionTime = microtime(true) - $startTime;
                    
                    // 确认消息已处理
                    $message->ack();
                    
                    Log::info('工作模式 - 工作者 {worker} 完成任务: {message}, 耗时: {time}秒, 结果: {result}', [
                        'worker'  => $workerName,
                        'message' => $data['message'] ?? 'unknown',
                        'time'    => round($executionTime, 2),
                        'result'  => $result ? 'success' : 'failed'
                    ]);
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
            Log::error('工作模式 - 工作者 {worker} 处理任务失败: {error}', [
                'worker' => $workerName,
                'error'  => $e->getMessage(),
                'trace'  => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * 批量发送任务示例
     *
     * @param int $count 任务数量
     * @return array 发送结果
     */
    public function batchSendTasks(int $count = 10): array
    {
        $results = [];
        
        for ($i = 1; $i <= $count; $i++) {
            // 随机设置任务复杂度（1-10）
            $complexity = rand(1, 10);
            $message = "Task #{$i} - " . date('Y-m-d H:i:s');
            
            $results[] = [
                'task_id'    => $i,
                'message'    => $message,
                'complexity' => $complexity,
                'success'    => $this->sendTask($message, $complexity)
            ];
        }
        
        return $results;
    }
    
    /**
     * 使用示例
     */
    public function example(): void
    {
        // 发送任务示例
        $this->batchSendTasks(5);
        
        // 工作者示例
        // 注意：在实际应用中，通常会在不同的进程或服务器上启动多个工作者
        // 以下代码仅作为示例，实际使用时需要在不同进程中运行
        
        // 工作者1
        // $this->worker('worker-1', function ($data, $workerName) {
        //     echo "[$workerName] 处理任务: {$data['message']}, 复杂度: {$data['complexity']}\n";
        //     
        //     // 模拟任务处理时间，复杂度越高耗时越长
        //     sleep($data['complexity']);
        //     
        //     return true;
        // });
        
        // 工作者2
        // $this->worker('worker-2', function ($data, $workerName) {
        //     echo "[$workerName] 处理任务: {$data['message']}, 复杂度: {$data['complexity']}\n";
        //     
        //     // 模拟任务处理时间，复杂度越高耗时越长
        //     sleep($data['complexity']);
        //     
        //     return true;
        // });
    }
} 