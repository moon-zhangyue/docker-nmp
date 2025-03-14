<?php
declare(strict_types=1);

namespace think\queue\deadletter;

use think\facade\Cache;
use think\facade\Log;
use think\facade\Config;
use think\queue\metrics\PrometheusCollector;

/**
 * 死信队列处理器
 * 用于处理失败的消息，并提供分析和报警功能
 */
class DeadLetterQueue
{
    /**
     * Redis缓存键前缀
     */
    protected $keyPrefix = 'queue:deadletter:';
    
    /**
     * 过期时间（秒）
     */
    protected $expireTime = 604800; // 默认7天
    
    /**
     * 报警阈值
     */
    protected $alertThreshold = 10;
    
    /**
     * 指标收集器
     */
    protected $metricsCollector;
    
    /**
     * 构造函数
     * 
     * @param array $options 配置选项
     */
    public function __construct(array $options = [])
    {
        if (isset($options['key_prefix'])) {
            $this->keyPrefix = $options['key_prefix'];
        }
        
        if (isset($options['expire_time'])) {
            $this->expireTime = (int)$options['expire_time'];
        }
        
        if (isset($options['alert_threshold'])) {
            $this->alertThreshold = (int)$options['alert_threshold'];
        }
        
        // 初始化指标收集器
        $this->metricsCollector = PrometheusCollector::getInstance();
    }
    
    /**
     * 添加消息到死信队列
     * 
     * @param string $messageId 消息ID
     * @param string $queue 队列名称
     * @param array $payload 消息内容
     * @param string $error 错误信息
     * @return bool 操作是否成功
     */
    public function add(string $messageId, string $queue, array $payload, string $error): bool
    {
        $key = $this->getKey($queue);
        $data = [
            'message_id' => $messageId,
            'queue' => $queue,
            'payload' => $payload,
            'error' => $error,
            'failed_at' => time(),
            'retry_count' => $payload['retry_count'] ?? 0
        ];
        
        // 使用Redis列表存储死信队列消息
        $result = Cache::lPush($key, json_encode($data));
        
        if ($result) {
            Log::warning('Message added to dead letter queue: {message_id}, queue: {queue}, error: {error}', [
                'message_id' => $messageId,
                'queue' => $queue,
                'error' => $error
            ]);
            
            // 设置过期时间
            Cache::expire($key, $this->expireTime);
            
            // 更新指标
            $this->metricsCollector->increment('queue_jobs_deadletter', [
                'connection' => 'kafka',
                'queue' => $queue
            ]);
            
            // 检查是否需要触发报警
            $this->checkAlertThreshold($queue);
            
            return true;
        } else {
            Log::error('Failed to add message to dead letter queue: {message_id}, queue: {queue}', [
                'message_id' => $messageId,
                'queue' => $queue
            ]);
            return false;
        }
    }
    
    /**
     * 获取死信队列中的消息
     * 
     * @param string $queue 队列名称
     * @param int $start 开始位置
     * @param int $end 结束位置
     * @return array 消息列表
     */
    public function getMessages(string $queue, int $start = 0, int $end = -1): array
    {
        $key = $this->getKey($queue);
        $messages = Cache::lRange($key, $start, $end);
        
        if (!is_array($messages)) {
            return [];
        }
        
        $result = [];
        foreach ($messages as $message) {
            $data = json_decode($message, true);
            if ($data) {
                $result[] = $data;
            }
        }
        
        return $result;
    }
    
    /**
     * 获取死信队列长度
     * 
     * @param string $queue 队列名称
     * @return int 队列长度
     */
    public function count(string $queue): int
    {
        $key = $this->getKey($queue);
        return Cache::lLen($key) ?: 0;
    }
    
    /**
     * 重试死信队列中的消息
     * 
     * @param string $queue 队列名称
     * @param int $index 消息索引
     * @param callable $callback 重试回调函数
     * @return bool 操作是否成功
     */
    public function retry(string $queue, int $index, callable $callback): bool
    {
        $key = $this->getKey($queue);
        $messages = Cache::lRange($key, $index, $index);
        
        if (empty($messages)) {
            return false;
        }
        
        $data = json_decode($messages[0], true);
        if (!$data) {
            return false;
        }
        
        // 增加重试次数
        $data['payload']['retry_count'] = ($data['payload']['retry_count'] ?? 0) + 1;
        
        // 调用回调函数重试消息
        $result = $callback($data['payload']);
        
        if ($result) {
            // 从死信队列中移除消息
            Cache::lRem($key, 1, $messages[0]);
            
            Log::info('Message retried from dead letter queue: {message_id}, queue: {queue}', [
                'message_id' => $data['message_id'],
                'queue' => $queue,
                'retry_count' => $data['payload']['retry_count']
            ]);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * 清空死信队列
     * 
     * @param string $queue 队列名称
     * @return bool 操作是否成功
     */
    public function clear(string $queue): bool
    {
        $key = $this->getKey($queue);
        return Cache::delete($key) ? true : false;
    }
    
    /**
     * 分析死信队列中的错误
     * 
     * @param string $queue 队列名称
     * @return array 错误分析结果
     */
    public function analyzeErrors(string $queue): array
    {
        $messages = $this->getMessages($queue);
        $errorStats = [];
        
        foreach ($messages as $message) {
            $error = $message['error'] ?? 'Unknown error';
            if (!isset($errorStats[$error])) {
                $errorStats[$error] = 0;
            }
            $errorStats[$error]++;
        }
        
        // 按错误出现次数排序
        arsort($errorStats);
        
        return [
            'total_messages' => count($messages),
            'error_stats' => $errorStats
        ];
    }
    
    /**
     * 检查是否需要触发报警
     * 
     * @param string $queue 队列名称
     * @return void
     */
    protected function checkAlertThreshold(string $queue): void
    {
        $count = $this->count($queue);
        
        if ($count >= $this->alertThreshold) {
            $analysis = $this->analyzeErrors($queue);
            
            Log::alert('Dead letter queue threshold exceeded: {queue}, count: {count}, threshold: {threshold}', [
                'queue' => $queue,
                'count' => $count,
                'threshold' => $this->alertThreshold,
                'analysis' => $analysis
            ]);
            
            // 这里可以添加其他报警方式，如发送邮件、短信等
            $this->sendAlert($queue, $count, $analysis);
        }
    }
    
    /**
     * 发送报警
     * 
     * @param string $queue 队列名称
     * @param int $count 消息数量
     * @param array $analysis 错误分析结果
     * @return void
     */
    protected function sendAlert(string $queue, int $count, array $analysis): void
    {
        // 获取报警配置
        $alertConfig = Config::get('queue.dead_letter.alert', []);
        
        // 如果没有配置报警方式，则只记录日志
        if (empty($alertConfig) || empty($alertConfig['type'])) {
            return;
        }
        
        // 构建报警消息
        $message = "死信队列报警：队列 {$queue} 中有 {$count} 条失败消息，超过阈值 {$this->alertThreshold}。\n";
        $message .= "错误分析：\n";
        
        foreach ($analysis['error_stats'] as $error => $errorCount) {
            $message .= "- {$error}: {$errorCount} 条\n";
        }
        
        // 根据配置的报警方式发送报警
        switch ($alertConfig['type']) {
            case 'email':
                // 实现邮件报警
                if (function_exists('mail') && !empty($alertConfig['email'])) {
                    mail(
                        $alertConfig['email'],
                        "[报警] 队列 {$queue} 死信队列阈值超限",
                        $message
                    );
                }
                break;
                
            case 'webhook':
                // 实现webhook报警
                if (!empty($alertConfig['webhook_url'])) {
                    $this->sendWebhook($alertConfig['webhook_url'], [
                        'queue' => $queue,
                        'count' => $count,
                        'threshold' => $this->alertThreshold,
                        'analysis' => $analysis,
                        'message' => $message
                    ]);
                }
                break;
        }
    }
    
    /**
     * 发送Webhook报警
     * 
     * @param string $url Webhook URL
     * @param array $data 报警数据
     * @return bool 操作是否成功
     */
    protected function sendWebhook(string $url, array $data): bool
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            Log::info('Webhook alert sent successfully: {url}, response: {response}', ['url' => $url, 'response' => $response]);
            return true;
        } else {
            Log::error('Failed to send webhook alert: {url}, http_code: {http_code}, response: {response}', ['url' => $url, 'http_code' => $httpCode, 'response' => $response]);
            return false;
        }
    }
    
    /**
     * 生成Redis缓存键
     * 
     * @param string $queue 队列名称
     * @return string Redis缓存键
     */
    protected function getKey(string $queue): string
    {
        return $this->keyPrefix . $queue;
    }
}