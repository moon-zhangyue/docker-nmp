<?php
declare(strict_types=1);

namespace app\controller\redis;

use app\controller\RedisDemo;
use app\facade\Redis;
use think\facade\Db;
use app\model\User;
use think\facade\View;

/**
 * Redis String类型演示控制器
 * 
 * 演示Redis String类型的常见应用场景
 */
class StringDemo extends RedisDemo
{
    /**
     * 演示页面
     */
    public function index()
    {
        return View::fetch('redis/string/index');
    }
    
    /**
     * 基本用法示例
     */
    public function basic()
    {
        try {
            $redis = Redis::string();
            
            // 设置字符串
            $redis->set('string_demo_basic', 'Hello Redis String!');
            
            // 设置带过期时间的字符串（60秒）
            $redis->set('string_demo_expire', 'This will expire in 60 seconds', 60);
            
            // 获取字符串
            $value1 = $redis->get('string_demo_basic');
            $value2 = $redis->get('string_demo_expire');
            
            // 批量设置
            $redis->mSet([
                'string_demo_batch1' => 'Batch Value 1',
                'string_demo_batch2' => 'Batch Value 2',
            ]);
            
            // 批量获取
            $batchValues = $redis->mGet(['string_demo_batch1', 'string_demo_batch2']);
            
            // 自增示例
            $redis->set('string_demo_counter', 0);
            $counter1 = $redis->increment('string_demo_counter');
            $counter2 = $redis->increment('string_demo_counter', 5);
            $counter3 = $redis->decrement('string_demo_counter', 2);
            
            $data = [
                'basic' => $value1,
                'expire' => $value2,
                'batch' => $batchValues,
                'counter' => [
                    'increment_1' => $counter1,
                    'increment_5' => $counter2,
                    'decrement_2' => $counter3,
                ],
            ];
            
            return $this->success('String基本用法演示成功', $data);
        } catch (\Throwable $e) {
            return $this->error('String基本用法演示失败：' . $e->getMessage());
        }
    }
    
    /**
     * 缓存用户信息示例
     */
    public function cacheUser()
    {
        try {
            $redis = Redis::string();
            $userId = $this->request->param('id', 1, 'intval');
            
            // 使用remember方法自动防止缓存穿透
            $user = $redis->remember("user:{$userId}", function() use ($userId) {
                // 模拟从数据库获取用户信息
                // 注：这里只是演示，实际应用中应该使用User模型查询
                $user = Db::name('user')->where('id', $userId)->find();
                return $user;
            }, 3600, true);
            
            if ($user) {
                return $this->success('获取用户信息成功', [
                    'user' => $user,
                    'from_cache' => true
                ]);
            } else {
                return $this->error("用户不存在或缓存已过期");
            }
        } catch (\Throwable $e) {
            return $this->error('缓存用户信息示例失败：' . $e->getMessage());
        }
    }
    
    /**
     * 分布式锁示例
     */
    public function distributedLock()
    {
        try {
            $key = $this->request->param('key', 'demo_lock');
            $ttl = $this->request->param('ttl', 10, 'intval');
            
            // 尝试获取锁
            $lockResult = Redis::acquireLock($key, $ttl);
            
            if ($lockResult) {
                // 模拟执行耗时操作
                usleep(100000); // 休眠100毫秒
                
                // 执行完成，释放锁
                $releaseResult = Redis::releaseLock($key);
                
                return $this->success('获取锁成功并已释放', [
                    'acquired' => true,
                    'released' => $releaseResult,
                ]);
            } else {
                return $this->success('获取锁失败', [
                    'acquired' => false,
                    'message' => '获取锁失败，请稍后再试',
                ]);
            }
        } catch (\Throwable $e) {
            return $this->error('分布式锁示例失败：' . $e->getMessage());
        }
    }
    
    /**
     * 防止缓存穿透示例
     */
    public function preventCachePenetration()
    {
        try {
            $redis = Redis::string();
            $id = $this->request->param('id', 999, 'intval'); // 默认使用不存在的ID
            
            // 使用remember方法自动处理空值缓存
            $data = $redis->remember("demo:penetration:{$id}", function() use ($id) {
                // 模拟从数据库获取不存在的数据
                return null;
            }, 3600, true, 60); // 对空值设置60秒的过期时间
            
            // 查询次数统计
            $queryCount = $redis->increment("demo:penetration:query_count:{$id}");
            
            // 查询数据库次数统计（由于空值缓存，只有第一次会查询数据库）
            $dbQueryCount = $redis->get("demo:penetration:db_query_count:{$id}");
            if (!$dbQueryCount) {
                $redis->set("demo:penetration:db_query_count:{$id}", 1);
                $dbQueryCount = 1;
            }
            
            return $this->success('防止缓存穿透演示', [
                'id' => $id,
                'data' => $data,
                'query_count' => $queryCount,
                'db_query_count' => $dbQueryCount,
                'conclusion' => '即使数据不存在，也只会查询一次数据库，后续请求直接返回空值缓存'
            ]);
        } catch (\Throwable $e) {
            return $this->error('防止缓存穿透示例失败：' . $e->getMessage());
        }
    }
    
    /**
     * 防止缓存雪崩示例
     */
    public function preventCacheAvalanche()
    {
        try {
            $redis = Redis::string();
            
            // 批量设置100个缓存，模拟同时过期的场景
            for ($i = 1; $i <= 10; $i++) {
                // 使用随机过期时间
                $expire = rand(5, 10); // 5-10秒随机过期时间
                $redis->set("demo:avalanche:{$i}", "Value {$i}", $expire);
            }
            
            // 获取所有缓存的过期时间
            $expirations = [];
            for ($i = 1; $i <= 10; $i++) {
                $expirations[$i] = $redis->ttl("demo:avalanche:{$i}");
            }
            
            return $this->success('防止缓存雪崩演示', [
                'expirations' => $expirations,
                'conclusion' => '通过随机化过期时间，避免了大量缓存同时过期导致的雪崩'
            ]);
        } catch (\Throwable $e) {
            return $this->error('防止缓存雪崩示例失败：' . $e->getMessage());
        }
    }
    
    /**
     * 计数器应用示例
     */
    public function counter()
    {
        try {
            $redis = Redis::string();
            $key = $this->request->param('key', 'page_view');
            
            // 增加计数
            $count = $redis->increment("counter:{$key}");
            
            // 获取今日访问量
            $today = date('Ymd');
            $todayCount = $redis->increment("counter:{$key}:{$today}");
            
            // 获取历史访问量前5天数据
            $historyCounts = [];
            for ($i = 1; $i <= 5; $i++) {
                $date = date('Ymd', strtotime("-{$i} day"));
                $historyCounts[$date] = $redis->get("counter:{$key}:{$date}") ?: 0;
            }
            
            return $this->success('计数器应用演示', [
                'total_count' => $count,
                'today_count' => $todayCount,
                'history_counts' => $historyCounts
            ]);
        } catch (\Throwable $e) {
            return $this->error('计数器应用示例失败：' . $e->getMessage());
        }
    }
    
    /**
     * 限流应用示例
     */
    public function rateLimit()
    {
        try {
            $redis = Redis::string();
            $ip = $this->request->ip();
            $action = $this->request->param('action', 'default');
            $limit = $this->request->param('limit', 10, 'intval');
            $period = $this->request->param('period', 60, 'intval'); // 60秒内最多请求10次
            
            $key = "rate_limit:{$ip}:{$action}";
            
            // 获取当前请求次数
            $count = $redis->get($key);
            
            if ($count === null) {
                // 第一次请求，设置计数为1，并设置过期时间
                $redis->set($key, 1, $period);
                $count = 1;
                $allowed = true;
            } else {
                $count = intval($count);
                if ($count < $limit) {
                    // 增加计数
                    $count = $redis->increment($key);
                    $allowed = true;
                } else {
                    // 超过限制
                    $allowed = false;
                }
            }
            
            // 获取剩余过期时间
            $ttl = $redis->ttl($key);
            
            $message = $allowed ? '请求允许' : '请求被限流';
            
            return $this->success($message, [
                'ip' => $ip,
                'action' => $action,
                'current_count' => $count,
                'limit' => $limit,
                'period' => $period,
                'ttl' => $ttl,
                'allowed' => $allowed,
                'reset_after' => $ttl . ' seconds'
            ]);
        } catch (\Throwable $e) {
            return $this->error('限流应用示例失败：' . $e->getMessage());
        }
    }
} 