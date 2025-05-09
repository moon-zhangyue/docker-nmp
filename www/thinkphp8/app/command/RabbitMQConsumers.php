<?php
declare(strict_types=1);

namespace app\command;

use app\examples\rabbitmq\SimpleMode;
use app\examples\rabbitmq\WorkMode;
use app\examples\rabbitmq\PublishSubscribeMode;
use app\examples\rabbitmq\RoutingMode;
use app\examples\rabbitmq\TopicMode;
use app\examples\rabbitmq\RPCMode;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Log;

/**
 * RabbitMQ消费者命令行工具
 */
class RabbitMQConsumers extends Command
{
    /**
     * 配置命令
     */
    protected function configure()
    {
        $this->setName('rabbitmq:consumer')
            ->addArgument('mode', Argument::REQUIRED, '工作模式: simple, work, pubsub, routing, topic, rpc')
            ->addArgument('name', Argument::OPTIONAL, '消费者名称', 'consumer-1')
            ->addOption('routing-keys', null, Option::VALUE_OPTIONAL, '路由键或主题模式，多个用逗号分隔', '')
            ->addOption('worker-count', null, Option::VALUE_OPTIONAL, '工作模式下的工作者数量', 1)
            ->setDescription('启动RabbitMQ消费者');
    }
    
    /**
     * 执行命令
     *
     * @param Input $input 输入对象
     * @param Output $output 输出对象
     * @return int 返回状态码
     */
    protected function execute(Input $input, Output $output)
    {
        $mode = $input->getArgument('mode');
        $name = $input->getArgument('name');
        $routingKeysStr = $input->getOption('routing-keys');
        $workerCount = (int) $input->getOption('worker-count');
        
        $routingKeys = $routingKeysStr ? explode(',', $routingKeysStr) : [];
        
        $output->writeln("<info>启动RabbitMQ {$mode} 模式消费者: {$name}</info>");
        
        try {
            switch ($mode) {
                case 'simple':
                    $this->startSimpleConsumer($output);
                    break;
                    
                case 'work':
                    $this->startWorkConsumer($output, $name, $workerCount);
                    break;
                    
                case 'pubsub':
                    $this->startPubSubConsumer($output, $name);
                    break;
                    
                case 'routing':
                    if (empty($routingKeys)) {
                        $output->writeln("<error>路由模式需要指定路由键，例如: --routing-keys=error,warning</error>");
                        return 1;
                    }
                    $this->startRoutingConsumer($output, $name, $routingKeys);
                    break;
                    
                case 'topic':
                    if (empty($routingKeys)) {
                        $output->writeln("<error>主题模式需要指定主题模式，例如: --routing-keys=app.#,*.error.*</error>");
                        return 1;
                    }
                    $this->startTopicConsumer($output, $name, $routingKeys);
                    break;
                    
                case 'rpc':
                    $this->startRPCServer($output);
                    break;
                    
                default:
                    $output->writeln("<error>不支持的工作模式: {$mode}</error>");
                    return 1;
            }
            
            return 0;
        } catch (\Exception $e) {
            $output->writeln("<error>启动消费者失败: {$e->getMessage()}</error>");
            Log::error('启动RabbitMQ消费者失败: {error}', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }
    
    /**
     * 启动简单模式消费者
     *
     * @param Output $output 输出对象
     */
    protected function startSimpleConsumer(Output $output)
    {
        $simpleMode = new SimpleMode();
        
        $output->writeln("<info>简单模式消费者已启动，等待消息...</info>");
        
        $simpleMode->receive(function ($message) use ($output) {
            $output->writeln("<comment>收到消息: {$message}</comment>");
            return true;
        });
    }
    
    /**
     * 启动工作模式消费者
     *
     * @param Output $output 输出对象
     * @param string $name 消费者名称
     * @param int $workerCount 工作者数量
     */
    protected function startWorkConsumer(Output $output, string $name, int $workerCount)
    {
        // 在实际应用中，应该在不同的进程中启动多个工作者
        // 这里仅作为示例，启动单个工作者
        $workMode = new WorkMode();
        
        $output->writeln("<info>工作模式消费者 {$name} 已启动，等待任务...</info>");
        
        $workMode->worker($name, function ($data, $workerName) use ($output) {
            $message = $data['message'] ?? 'unknown';
            $complexity = $data['complexity'] ?? 1;
            
            $output->writeln("<comment>[{$workerName}] 收到任务: {$message}, 复杂度: {$complexity}</comment>");
            
            // 模拟任务处理时间
            sleep($complexity);
            
            $output->writeln("<info>[{$workerName}] 完成任务: {$message}</info>");
            
            return true;
        });
    }
    
    /**
     * 启动发布/订阅模式消费者
     *
     * @param Output $output 输出对象
     * @param string $name 消费者名称
     */
    protected function startPubSubConsumer(Output $output, string $name)
    {
        $pubSubMode = new PublishSubscribeMode();
        
        $output->writeln("<info>发布/订阅模式订阅者 {$name} 已启动，等待消息...</info>");
        
        $pubSubMode->subscribe($name, function ($message, $subscriberName) use ($output) {
            $output->writeln("<comment>[{$subscriberName}] 收到消息: {$message}</comment>");
            return true;
        });
    }
    
    /**
     * 启动路由模式消费者
     *
     * @param Output $output 输出对象
     * @param string $name 消费者名称
     * @param array $routingKeys 路由键数组
     */
    protected function startRoutingConsumer(Output $output, string $name, array $routingKeys)
    {
        $routingMode = new RoutingMode();
        
        $output->writeln("<info>路由模式消费者 {$name} 已启动，订阅路由键: " . implode(', ', $routingKeys) . "</info>");
        
        $routingMode->subscribe($name, $routingKeys, function ($message, $routingKey, $consumerName) use ($output) {
            $output->writeln("<comment>[{$consumerName}] 收到[{$routingKey}]消息: {$message}</comment>");
            return true;
        });
    }
    
    /**
     * 启动主题模式消费者
     *
     * @param Output $output 输出对象
     * @param string $name 消费者名称
     * @param array $topicPatterns 主题模式数组
     */
    protected function startTopicConsumer(Output $output, string $name, array $topicPatterns)
    {
        $topicMode = new TopicMode();
        
        $output->writeln("<info>主题模式消费者 {$name} 已启动，订阅主题模式: " . implode(', ', $topicPatterns) . "</info>");
        
        $topicMode->subscribe($name, $topicPatterns, function ($message, $routingKey, $consumerName) use ($output) {
            $output->writeln("<comment>[{$consumerName}] 收到[{$routingKey}]消息: {$message}</comment>");
            return true;
        });
    }
    
    /**
     * 启动RPC服务端
     *
     * @param Output $output 输出对象
     */
    protected function startRPCServer(Output $output)
    {
        $rpcMode = new RPCMode();
        
        $output->writeln("<info>RPC服务端已启动，等待请求...</info>");
        
        $rpcMode->serve(function ($data) use ($output) {
            $output->writeln("<comment>收到RPC请求: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "</comment>");
            
            // 模拟处理请求
            if (isset($data['operation']) && isset($data['numbers']) && is_array($data['numbers'])) {
                switch ($data['operation']) {
                    case 'sum':
                        $result = array_sum($data['numbers']);
                        $output->writeln("<info>计算求和: " . implode(' + ', $data['numbers']) . " = {$result}</info>");
                        return $result;
                        
                    case 'multiply':
                        $result = array_product($data['numbers']);
                        $output->writeln("<info>计算乘积: " . implode(' * ', $data['numbers']) . " = {$result}</info>");
                        return $result;
                        
                    case 'max':
                        $result = max($data['numbers']);
                        $output->writeln("<info>计算最大值: max(" . implode(', ', $data['numbers']) . ") = {$result}</info>");
                        return $result;
                        
                    case 'min':
                        $result = min($data['numbers']);
                        $output->writeln("<info>计算最小值: min(" . implode(', ', $data['numbers']) . ") = {$result}</info>");
                        return $result;
                        
                    default:
                        $output->writeln("<error>不支持的操作: {$data['operation']}</error>");
                        throw new \Exception("不支持的操作: " . $data['operation']);
                }
            }
            
            $output->writeln("<error>无效的请求数据</error>");
            throw new \Exception("无效的请求数据");
        });
    }
} 