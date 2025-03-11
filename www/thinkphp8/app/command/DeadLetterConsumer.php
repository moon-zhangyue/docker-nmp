<?php
declare(strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\facade\Log;
use think\queue\deadletter\DeadLetterQueue;

/**
 * 死信队列消费者命令
 * 用于处理、分析和管理死信队列中的失败消息
 */
class DeadLetterConsumer extends Command
{
    /**
     * 死信队列处理器
     */
    protected $deadLetterQueue;
    
    /**
     * 配置命令
     */
    protected function configure()
    {
        $this->setName('queue:deadletter')
            ->setDescription('处理死信队列中的失败消息')
            ->addOption('queue', null, Option::VALUE_OPTIONAL, '队列名称', 'default')
            ->addOption('action', null, Option::VALUE_OPTIONAL, '操作类型：list, analyze, retry, clear', 'list')
            ->addOption('index', null, Option::VALUE_OPTIONAL, '重试特定消息的索引', null)
            ->addOption('limit', null, Option::VALUE_OPTIONAL, '显示的消息数量', 10);
    }
    
    /**
     * 执行命令
     * 
     * @param Input $input 输入对象
     * @param Output $output 输出对象
     * @return int
     */
    protected function execute(Input $input, Output $output)
    {
        // 初始化死信队列处理器
        $this->deadLetterQueue = new DeadLetterQueue();
        
        // 获取参数
        $queue = $input->getOption('queue');
        $action = $input->getOption('action');
        $index = $input->getOption('index');
        $limit = (int)$input->getOption('limit');
        
        // 根据操作类型执行不同的操作
        switch ($action) {
            case 'list':
                return $this->listMessages($queue, $limit, $output);
                
            case 'analyze':
                return $this->analyzeMessages($queue, $output);
                
            case 'retry':
                return $this->retryMessage($queue, $index, $output);
                
            case 'clear':
                return $this->clearQueue($queue, $output);
                
            default:
                $output->writeln('<error>无效的操作类型，可用操作：list, analyze, retry, clear</error>');
                return 1;
        }
    }
    
    /**
     * 列出死信队列中的消息
     * 
     * @param string $queue 队列名称
     * @param int $limit 显示的消息数量
     * @param Output $output 输出对象
     * @return int
     */
    protected function listMessages(string $queue, int $limit, Output $output): int
    {
        $output->writeln("<info>正在获取队列 '{$queue}' 的死信消息...</info>\n");
        
        // 获取死信队列消息
        $messages = $this->deadLetterQueue->getMessages($queue, 0, $limit - 1);
        $count = $this->deadLetterQueue->count($queue);
        
        if (empty($messages)) {
            $output->writeln('<comment>死信队列为空</comment>');
            return 0;
        }
        
        $output->writeln("<info>队列 '{$queue}' 中共有 {$count} 条死信消息，显示前 {$limit} 条：</info>\n");
        
        // 显示消息列表
        foreach ($messages as $index => $message) {
            $output->writeln("<comment>消息 #{$index}</comment>");
            $output->writeln("ID: {$message['message_id']}");
            $output->writeln("失败时间: " . date('Y-m-d H:i:s', $message['failed_at']));
            $output->writeln("重试次数: {$message['retry_count']}");
            $output->writeln("错误信息: {$message['error']}");
            $output->writeln("消息内容: " . json_encode($message['payload'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $output->writeln("-----------------------------------");
        }
        
        return 0;
    }
    
    /**
     * 分析死信队列中的消息
     * 
     * @param string $queue 队列名称
     * @param Output $output 输出对象
     * @return int
     */
    protected function analyzeMessages(string $queue, Output $output): int
    {
        $output->writeln("<info>正在分析队列 '{$queue}' 的死信消息...</info>\n");
        
        // 分析死信队列错误
        $analysis = $this->deadLetterQueue->analyzeErrors($queue);
        
        if ($analysis['total_messages'] === 0) {
            $output->writeln('<comment>死信队列为空，无法进行分析</comment>');
            return 0;
        }
        
        $output->writeln("<info>队列 '{$queue}' 中共有 {$analysis['total_messages']} 条死信消息</info>\n");
        $output->writeln("<info>错误统计：</info>");
        
        // 显示错误统计
        foreach ($analysis['error_stats'] as $error => $count) {
            $percentage = round(($count / $analysis['total_messages']) * 100, 2);
            $output->writeln("- {$error}: {$count} 条 ({$percentage}%)");
        }
        
        return 0;
    }
    
    /**
     * 重试死信队列中的消息
     * 
     * @param string $queue 队列名称
     * @param int|null $index 消息索引
     * @param Output $output 输出对象
     * @return int
     */
    protected function retryMessage(string $queue, ?int $index, Output $output): int
    {
        // 如果没有指定索引，则重试所有消息
        if ($index === null) {
            return $this->retryAllMessages($queue, $output);
        }
        
        $output->writeln("<info>正在重试队列 '{$queue}' 中索引为 {$index} 的消息...</info>");
        
        // 重试指定消息
        $result = $this->deadLetterQueue->retry($queue, $index, function ($payload) use ($queue) {
            // 使用队列门面重新推送消息
            return \think\facade\Queue::push($payload['job'], $payload['data'] ?? '', $queue);
        });
        
        if ($result) {
            $output->writeln("<info>消息重试成功</info>");
            return 0;
        } else {
            $output->writeln("<error>消息重试失败，可能是索引无效或队列为空</error>");
            return 1;
        }
    }
    
    /**
     * 重试死信队列中的所有消息
     * 
     * @param string $queue 队列名称
     * @param Output $output 输出对象
     * @return int
     */
    protected function retryAllMessages(string $queue, Output $output): int
    {
        $output->writeln("<info>正在重试队列 '{$queue}' 中的所有消息...</info>");
        
        // 获取所有消息
        $messages = $this->deadLetterQueue->getMessages($queue);
        $count = count($messages);
        
        if ($count === 0) {
            $output->writeln('<comment>死信队列为空，没有消息需要重试</comment>');
            return 0;
        }
        
        $output->writeln("<info>队列 '{$queue}' 中共有 {$count} 条消息需要重试</info>");
        
        // 重试计数器
        $success = 0;
        $failed = 0;
        
        // 逐个重试消息
        foreach ($messages as $index => $message) {
            $result = $this->deadLetterQueue->retry($queue, $index, function ($payload) use ($queue) {
                return \think\facade\Queue::push($payload['job'], $payload['data'] ?? '', $queue);
            });
            
            if ($result) {
                $success++;
            } else {
                $failed++;
            }
        }
        
        $output->writeln("<info>重试完成：成功 {$success} 条，失败 {$failed} 条</info>");
        
        return $failed > 0 ? 1 : 0;
    }
    
    /**
     * 清空死信队列
     * 
     * @param string $queue 队列名称
     * @param Output $output 输出对象
     * @return int
     */
    protected function clearQueue(string $queue, Output $output): int
    {
        $output->writeln("<info>正在清空队列 '{$queue}' 的死信消息...</info>");
        
        // 获取消息数量
        $count = $this->deadLetterQueue->count($queue);
        
        if ($count === 0) {
            $output->writeln('<comment>死信队列已经为空</comment>');
            return 0;
        }
        
        // 清空队列
        $result = $this->deadLetterQueue->clear($queue);
        
        if ($result) {
            $output->writeln("<info>成功清空队列，共删除 {$count} 条消息</info>");
            return 0;
        } else {
            $output->writeln("<error>清空队列失败</error>");
            return 1;
        }
    }
}