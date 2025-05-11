<?php
declare(strict_types=1);

namespace app\controller\redis;

use app\controller\RedisDemo;
use app\facade\Redis;
use think\facade\View;

/**
 * Redis List类型演示控制器
 * 
 * 演示Redis List类型的常见应用场景
 */
class ListDemo extends RedisDemo
{
    /**
     * 演示页面
     */
    public function index()
    {
        return View::fetch('redis/list/index');
    }
    
    /**
     * 基本用法示例
     */
    public function basic()
    {
        try {
            $redis = Redis::list();
            $key = 'list_demo_basic';
            
            // 清空之前的测试数据
            $redis->delete($key);
            
            // 从左侧添加元素
            $redis->lPush($key, 'Value 1');
            $redis->lPush($key, 'Value 2');
            
            // 从右侧添加元素
            $redis->rPush($key, 'Value 3');
            $redis->rPush($key, 'Value 4');
            
            // 获取列表长度
            $length = $redis->lLen($key);
            
            // 获取列表范围
            $allItems = $redis->lRange($key, 0, -1);
            
            // 获取指定索引的元素
            $firstItem = $redis->lIndex($key, 0);
            $lastItem = $redis->lIndex($key, -1);
            
            // 从左侧弹出元素
            $leftPop = $redis->lPop($key);
            
            // 从右侧弹出元素
            $rightPop = $redis->rPop($key);
            
            // 再次获取列表范围
            $remainingItems = $redis->lRange($key, 0, -1);
            
            return $this->success('List基本用法演示成功', [
                'length' => $length,
                'all_items' => $allItems,
                'first_item' => $firstItem,
                'last_item' => $lastItem,
                'left_pop' => $leftPop,
                'right_pop' => $rightPop,
                'remaining_items' => $remainingItems,
            ]);
        } catch (\Throwable $e) {
            return $this->error('List基本用法演示失败：' . $e->getMessage());
        }
    }
    
    /**
     * 简单消息队列示例
     */
    public function simpleQueue()
    {
        try {
            $redis = Redis::list();
            $queueKey = 'queue:demo';
            $action = $this->request->param('action', 'stats');
            $message = $this->request->param('message', '');
            
            switch ($action) {
                case 'push':
                    // 生产消息
                    if (!empty($message)) {
                        $messageData = [
                            'id' => uniqid(),
                            'content' => $message,
                            'timestamp' => time(),
                        ];
                        $redis->rPush($queueKey, json_encode($messageData));
                        $result = ['status' => 'success', 'message' => '消息已加入队列'];
                    } else {
                        $result = ['status' => 'error', 'message' => '消息内容不能为空'];
                    }
                    break;
                    
                case 'pop':
                    // 消费消息
                    $messageJson = $redis->lPop($queueKey);
                    if ($messageJson !== null) {
                        $messageData = json_decode($messageJson, true);
                        $result = ['status' => 'success', 'message' => '已消费一条消息', 'data' => $messageData];
                    } else {
                        $result = ['status' => 'info', 'message' => '队列为空'];
                    }
                    break;
                    
                case 'clear':
                    // 清空队列
                    $redis->delete($queueKey);
                    $result = ['status' => 'success', 'message' => '队列已清空'];
                    break;
                    
                case 'stats':
                default:
                    // 队列统计信息
                    $length = $redis->lLen($queueKey);
                    $head = $redis->lRange($queueKey, 0, 2); // 查看队头的几条消息
                    $headData = [];
                    foreach ($head as $item) {
                        $headData[] = json_decode($item, true);
                    }
                    $result = [
                        'status' => 'success', 
                        'length' => $length,
                        'head' => $headData,
                    ];
                    break;
            }
            
            return $this->success('队列操作成功', $result);
        } catch (\Throwable $e) {
            return $this->error('队列操作失败：' . $e->getMessage());
        }
    }
    
    /**
     * 最新动态/时间线示例
     */
    public function timeline()
    {
        try {
            $redis = Redis::list();
            $userId = $this->request->param('user_id', 1, 'intval');
            $action = $this->request->param('action', 'view');
            $content = $this->request->param('content', '');
            
            $timelineKey = "user:timeline:{$userId}";
            $maxItems = 10; // 最多保存10条动态
            
            switch ($action) {
                case 'add':
                    // 添加新动态
                    if (!empty($content)) {
                        $feedItem = [
                            'id' => uniqid(),
                            'content' => $content,
                            'timestamp' => time(),
                            'formatted_time' => date('Y-m-d H:i:s'),
                        ];
                        
                        // 添加到列表头部
                        $redis->lPush($timelineKey, json_encode($feedItem));
                        
                        // 保持列表最大长度
                        $redis->lTrim($timelineKey, 0, $maxItems - 1);
                        
                        $result = ['status' => 'success', 'message' => '动态已添加', 'item' => $feedItem];
                    } else {
                        $result = ['status' => 'error', 'message' => '动态内容不能为空'];
                    }
                    break;
                    
                case 'view':
                default:
                    // 获取用户动态列表
                    $items = $redis->lRange($timelineKey, 0, -1);
                    $timeline = [];
                    
                    foreach ($items as $item) {
                        $timeline[] = json_decode($item, true);
                    }
                    
                    $result = [
                        'status' => 'success',
                        'user_id' => $userId,
                        'count' => count($timeline),
                        'timeline' => $timeline,
                    ];
                    break;
            }
            
            return $this->success('动态获取成功', $result);
        } catch (\Throwable $e) {
            return $this->error('动态操作失败：' . $e->getMessage());
        }
    }
    
    /**
     * 基于滑动窗口的限流示例
     */
    public function slidingWindowRateLimit()
    {
        try {
            $redis = Redis::list();
            $ip = $this->request->ip();
            $action = $this->request->param('action', 'default');
            $windowSize = $this->request->param('window', 60, 'intval'); // 窗口大小，秒
            $limit = $this->request->param('limit', 10, 'intval'); // 窗口内最大请求数
            
            $key = "rate_limit:sliding:{$action}:{$ip}";
            $now = time();
            
            // 添加当前时间戳到列表
            $redis->lPush($key, $now);
            
            // 获取窗口内的所有请求
            $requests = $redis->lRange($key, 0, -1);
            
            // 计算窗口的起始时间
            $windowStart = $now - $windowSize;
            
            // 移除窗口外的请求
            $outOfWindowCount = 0;
            foreach ($requests as $index => $timestamp) {
                if ($timestamp < $windowStart) {
                    $outOfWindowCount++;
                } else {
                    break;
                }
            }
            
            // 如果有窗口外的请求，从列表中移除
            if ($outOfWindowCount > 0) {
                for ($i = 0; $i < $outOfWindowCount; $i++) {
                    $redis->rPop($key);
                }
            }
            
            // 获取窗口内的请求数
            $requestCount = $redis->lLen($key);
            
            // 设置过期时间，避免长期占用内存
            $redis->expire($key, $windowSize * 2);
            
            // 判断是否超过限制
            $allowed = $requestCount <= $limit;
            
            return $this->success('滑动窗口限流检查', [
                'ip' => $ip,
                'action' => $action,
                'window_size' => $windowSize,
                'limit' => $limit,
                'request_count' => $requestCount,
                'allowed' => $allowed,
                'remaining' => max(0, $limit - $requestCount),
                'reset_after' => $windowSize,
            ]);
        } catch (\Throwable $e) {
            return $this->error('滑动窗口限流失败：' . $e->getMessage());
        }
    }
    
    /**
     * 阻塞队列演示
     */
    public function blockingQueue()
    {
        try {
            $redis = Redis::list();
            $queueKey = 'queue:blocking_demo';
            $action = $this->request->param('action', 'stats');
            $message = $this->request->param('message', '');
            $timeout = $this->request->param('timeout', 1, 'intval');
            
            switch ($action) {
                case 'push':
                    // 生产消息
                    if (!empty($message)) {
                        $messageData = [
                            'id' => uniqid(),
                            'content' => $message,
                            'timestamp' => time(),
                        ];
                        $redis->rPush($queueKey, json_encode($messageData));
                        $result = ['status' => 'success', 'message' => '消息已加入队列'];
                    } else {
                        $result = ['status' => 'error', 'message' => '消息内容不能为空'];
                    }
                    break;
                    
                case 'bpop':
                    // 阻塞消费消息（注意：在Web环境下不推荐使用阻塞操作，这里仅作演示）
                    $response = $redis->blPop([$queueKey], $timeout);
                    if ($response !== null && is_array($response)) {
                        $messageJson = $response[1] ?? null;
                        if ($messageJson) {
                            $messageData = json_decode($messageJson, true);
                            $result = ['status' => 'success', 'message' => '已消费一条消息', 'data' => $messageData];
                        } else {
                            $result = ['status' => 'error', 'message' => '获取消息失败'];
                        }
                    } else {
                        $result = ['status' => 'info', 'message' => '队列为空或等待超时'];
                    }
                    break;
                    
                case 'clear':
                    // 清空队列
                    $redis->delete($queueKey);
                    $result = ['status' => 'success', 'message' => '队列已清空'];
                    break;
                    
                case 'stats':
                default:
                    // 队列统计信息
                    $length = $redis->lLen($queueKey);
                    $head = $redis->lRange($queueKey, 0, 2); // 查看队头的几条消息
                    $headData = [];
                    foreach ($head as $item) {
                        $headData[] = json_decode($item, true);
                    }
                    $result = [
                        'status' => 'success', 
                        'length' => $length,
                        'head' => $headData,
                        'note' => '注意：在Web环境下使用阻塞操作可能会导致请求超时，建议在命令行脚本中使用'
                    ];
                    break;
            }
            
            return $this->success('阻塞队列操作成功', $result);
        } catch (\Throwable $e) {
            return $this->error('阻塞队列操作失败：' . $e->getMessage());
        }
    }
} 