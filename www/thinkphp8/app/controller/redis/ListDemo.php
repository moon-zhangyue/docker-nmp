<?php
declare(strict_types=1);

namespace app\controller\redis;

use app\controller\RedisDemo;
use app\facade\Redis;
use think\facade\View;
use think\facade\Log;

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
        Log::info('访问Redis List演示页面');
        return View::fetch('redis/list/index');
    }

    /**
     * 基本用法示例
     */
    public function basic()
    {
        try {
            Log::info('执行Redis List基本用法示例');
            $redis = Redis::list();
            $key   = 'list_demo_basic';

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
            Log::debug('List长度: {length}', ['length' => $length]);

            // 获取列表范围
            $allItems = $redis->lRange($key, 0, -1);

            // 获取指定索引的元素
            $firstItem = $redis->lIndex($key, 0);
            $lastItem  = $redis->lIndex($key, -1);

            // 从左侧弹出元素
            $leftPop = $redis->lPop($key);

            // 从右侧弹出元素
            $rightPop = $redis->rPop($key);

            // 再次获取列表范围
            $remainingItems = $redis->lRange($key, 0, -1);

            return $this->success('List基本用法演示成功', [
                'length'          => $length,
                'all_items'       => $allItems,
                'first_item'      => $firstItem,
                'last_item'       => $lastItem,
                'left_pop'        => $leftPop,
                'right_pop'       => $rightPop,
                'remaining_items' => $remainingItems,
            ]);
        } catch (\Throwable $e) {
            Log::error('List基本用法演示失败: {error}', ['error' => $e->getMessage()]);
            return $this->error('List基本用法演示失败：' . $e->getMessage());
        }
    }

    /**
     * 简单消息队列示例
     */
    public function simpleQueue()
    {
        try {
            Log::info('执行Redis List简单消息队列示例');
            $redis    = Redis::list();
            $queueKey = 'queue:demo';
            $action   = $this->request->param('action', 'stats');
            $message  = $this->request->param('message', '');
            
            Log::debug('队列操作: {action}', ['action' => $action]);

            switch ($action) {
                case 'push':
                    // 生产消息
                    if (!empty($message)) {
                        $messageData = [
                            'id'        => uniqid(),
                            'content'   => $message,
                            'timestamp' => time(),
                        ];
                        $redis->rPush($queueKey, json_encode($messageData));
                        Log::info('消息已加入队列: {id}', ['id' => $messageData['id']]);
                        $result = ['status' => 'success', 'message' => '消息已加入队列'];
                    } else {
                        Log::warning('尝试添加空消息到队列');
                        $result = ['status' => 'error', 'message' => '消息内容不能为空'];
                    }
                    break;

                case 'pop':
                    // 消费消息
                    $messageJson = $redis->lPop($queueKey);
                    if ($messageJson !== null) {
                        $messageData = json_decode($messageJson, true);
                        Log::info('已消费队列消息: {id}', ['id' => $messageData['id'] ?? '未知ID']);
                        $result = ['status' => 'success', 'message' => '已消费一条消息', 'data' => $messageData];
                    } else {
                        Log::info('尝试消费消息，但队列为空');
                        $result = ['status' => 'info', 'message' => '队列为空'];
                    }
                    break;

                case 'clear':
                    // 清空队列
                    $redis->delete($queueKey);
                    Log::info('队列已清空: {key}', ['key' => $queueKey]);
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
                    Log::info('获取队列统计信息: {length}条消息', ['length' => $length]);
                    $result = [
                        'status' => 'success',
                        'length' => $length,
                        'head'   => $headData,
                    ];
                    break;
            }

            return $this->success('队列操作成功', $result);
        } catch (\Throwable $e) {
            Log::error('队列操作失败: {error}', ['error' => $e->getMessage()]);
            return $this->error('队列操作失败：' . $e->getMessage());
        }
    }

    /**
     * 最新动态/时间线示例
     */
    public function timeline()
    {
        try {
            Log::info('执行Redis List时间线示例');
            $redis   = Redis::list();
            $userId  = $this->request->param('user_id', 1, 'intval');
            $action  = $this->request->param('action', 'view');
            $content = $this->request->param('content', '');

            $timelineKey = "user:timeline:{$userId}";
            $maxItems    = 10; // 最多保存10条动态
            
            Log::debug('时间线操作: {action}, 用户ID: {userId}', ['action' => $action, 'userId' => $userId]);

            switch ($action) {
                case 'add':
                    // 添加新动态
                    if (!empty($content)) {
                        $feedItem = [
                            'id'             => uniqid(),
                            'content'        => $content,
                            'timestamp'      => time(),
                            'formatted_time' => date('Y-m-d H:i:s'),
                        ];

                        // 添加到列表头部
                        $redis->lPush($timelineKey, json_encode($feedItem));
                        Log::info('用户添加新动态: {id}, 用户ID: {userId}', ['id' => $feedItem['id'], 'userId' => $userId]);

                        // 保持列表最大长度
                        $redis->lTrim($timelineKey, 0, $maxItems - 1);

                        $result = ['status' => 'success', 'message' => '动态已添加', 'item' => $feedItem];
                    } else {
                        Log::warning('尝试添加空内容动态, 用户ID: {userId}', ['userId' => $userId]);
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

                    Log::info('获取用户动态列表: {count}条, 用户ID: {userId}', ['count' => count($timeline), 'userId' => $userId]);
                    $result = [
                        'status'   => 'success',
                        'user_id'  => $userId,
                        'count'    => count($timeline),
                        'timeline' => $timeline,
                    ];
                    break;
            }

            return $this->success('动态获取成功', $result);
        } catch (\Throwable $e) {
            Log::error('动态操作失败: {error}', ['error' => $e->getMessage()]);
            return $this->error('动态操作失败：' . $e->getMessage());
        }
    }

    /**
     * 基于滑动窗口的限流示例
     */
    public function slidingWindowRateLimit()
    {
        try {
            Log::info('执行Redis List滑动窗口限流示例');
            $redis      = Redis::list();
            $ip         = $this->request->ip();
            $action     = $this->request->param('action', 'default');
            $windowSize = $this->request->param('window', 60, 'intval'); // 窗口大小，秒
            $limit      = $this->request->param('limit', 10, 'intval'); // 窗口内最大请求数

            $key = "rate_limit:sliding:{$action}:{$ip}";
            $now = time();
            
            Log::debug('限流检查: IP: {ip}, 操作: {action}', ['ip' => $ip, 'action' => $action]);

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
                Log::debug('移除过期请求: {count}个', ['count' => $outOfWindowCount]);
            }

            // 获取窗口内的请求数
            $requestCount = $redis->lLen($key);
      
            // 设置过期时间，避免长期占用内存
            // 修复：获取Redis原生实例设置过期时间
            Redis::getRedis()->expire($key, $windowSize * 2);

            // 判断是否超过限制
            $allowed = $requestCount <= $limit;
            
            if (!$allowed) {
                Log::warning('请求被限流: IP: {ip}, 操作: {action}, 请求数: {count}/{limit}', 
                    ['ip' => $ip, 'action' => $action, 'count' => $requestCount, 'limit' => $limit]);
            }

            return $this->success('滑动窗口限流检查', [
                'ip'            => $ip,
                'action'        => $action,
                'window_size'   => $windowSize,
                'limit'         => $limit,
                'request_count' => $requestCount,
                'allowed'       => $allowed,
                'remaining'     => max(0, $limit - $requestCount),
                'reset_after'   => $windowSize,
            ]);
        } catch (\Throwable $e) {
            Log::error('滑动窗口限流失败: {error}', ['error' => $e->getMessage()]);
            return $this->error('滑动窗口限流失败：' . $e->getMessage());
        }
    }

    /**
     * 阻塞队列演示
     */
    public function blockingQueue()
    {
        try {
            Log::info('执行Redis List阻塞队列示例');
            $redis    = Redis::list();
            $queueKey = 'queue:blocking_demo';
            $action   = $this->request->param('action', 'stats');
            $message  = $this->request->param('message', '');
            $timeout  = $this->request->param('timeout', 1, 'intval');
            
            Log::debug('阻塞队列操作: {action}, 超时: {timeout}秒', ['action' => $action, 'timeout' => $timeout]);

            switch ($action) {
                case 'push':
                    // 生产消息
                    if (!empty($message)) {
                        $messageData = [
                            'id'        => uniqid(),
                            'content'   => $message,
                            'timestamp' => time(),
                        ];
                        $redis->rPush($queueKey, json_encode($messageData));
                        Log::info('消息已加入阻塞队列: {id}', ['id' => $messageData['id']]);
                        $result = ['status' => 'success', 'message' => '消息已加入队列'];
                    } else {
                        Log::warning('尝试添加空消息到阻塞队列');
                        $result = ['status' => 'error', 'message' => '消息内容不能为空'];
                    }
                    break;

                case 'bpop':
                    // 阻塞消费消息（注意：在Web环境下不推荐使用阻塞操作，这里仅作演示）
                    Log::info('尝试阻塞获取队列消息, 超时: {timeout}秒', ['timeout' => $timeout]);
                    $response = $redis->blPop([$queueKey], $timeout);
                    if ($response !== null && is_array($response)) {
                        $messageJson = $response[1] ?? null;
                        if ($messageJson) {
                            $messageData = json_decode($messageJson, true);
                            Log::info('成功获取阻塞队列消息: {id}', ['id' => $messageData['id'] ?? '未知ID']);
                            $result = ['status' => 'success', 'message' => '已消费一条消息', 'data' => $messageData];
                        } else {
                            Log::warning('阻塞队列返回了无效消息');
                            $result = ['status' => 'error', 'message' => '获取消息失败'];
                        }
                    } else {
                        Log::info('阻塞队列为空或等待超时');
                        $result = ['status' => 'info', 'message' => '队列为空或等待超时'];
                    }
                    break;

                case 'clear':
                    // 清空队列
                    $redis->delete($queueKey);
                    Log::info('阻塞队列已清空: {key}', ['key' => $queueKey]);
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
                    Log::info('获取阻塞队列统计信息: {length}条消息', ['length' => $length]);
                    $result = [
                        'status' => 'success',
                        'length' => $length,
                        'head'   => $headData,
                        'note'   => '注意：在Web环境下使用阻塞操作可能会导致请求超时，建议在命令行脚本中使用'
                    ];
                    break;
            }

            return $this->success('阻塞队列操作成功', $result);
        } catch (\Throwable $e) {
            Log::error('阻塞队列操作失败: {error}', ['error' => $e->getMessage()]);
            return $this->error('阻塞队列操作失败：' . $e->getMessage());
        }
    }
}