<?php
declare(strict_types=1);

namespace app\controller\redis;

use app\controller\RedisDemo;
use app\facade\Redis;
use think\facade\View;

/**
 * Redis HyperLogLog类型演示控制器
 * 
 * 演示Redis HyperLogLog类型的常见应用场景
 */
class HyperLogLogDemo extends RedisDemo
{
    /**
     * 演示页面
     */
    public function index()
    {
        return View::fetch('redis/hyperloglog/index');
    }
    
    /**
     * 基本用法示例
     */
    public function basic()
    {
        try {
            $redis = Redis::hyperLogLog();
            $key = 'hll_demo_basic';
            
            // 清空之前的测试数据
            $redis->delete($key);
            
            // 添加元素
            $redis->pfAdd($key, 'value1');
            $redis->pfAdd($key, 'value2');
            $redis->pfAdd($key, 'value3');
            $redis->pfAdd($key, 'value1'); // 重复添加，将被忽略
            
            // 批量添加
            $redis->pfAdd($key, ['value4', 'value5', 'value6']);
            
            // 统计基数
            $count = $redis->pfCount($key);
            
            // 创建第二个HyperLogLog
            $key2 = 'hll_demo_basic2';
            $redis->delete($key2);
            $redis->pfAdd($key2, ['value5', 'value6', 'value7', 'value8']);
            
            // 分别统计
            $count1 = $redis->pfCount($key);
            $count2 = $redis->pfCount($key2);
            
            // 合并统计
            $mergeCount = $redis->pfMergeCount([$key, $key2]);
            
            // 合并到新的HyperLogLog
            $mergeKey = 'hll_demo_merge';
            $redis->pfMerge($mergeKey, [$key, $key2]);
            $mergedCount = $redis->pfCount($mergeKey);
            
            return $this->success('HyperLogLog基本用法演示成功', [
                'key1_count' => $count1,
                'key2_count' => $count2,
                'merge_count' => $mergeCount,
                'merged_key_count' => $mergedCount,
                'explanation' => [
                    'precision' => '标准误差约为0.81%',
                    'memory_usage' => '无论数据量大小，只占用12KB内存',
                    'limitations' => '不能获取具体的元素，只能统计近似基数',
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->error('HyperLogLog基本用法演示失败：' . $e->getMessage());
        }
    }
    
    /**
     * UV统计示例
     */
    public function uvStats()
    {
        try {
            $redis = Redis::hyperLogLog();
            $action = $this->request->param('action', 'simulate');
            
            switch ($action) {
                case 'simulate':
                    // 模拟UV数据
                    $days = 7; // 模拟7天
                    $users = 10000; // 总用户池
                    $visitorsPerDay = 2000; // 每天的访问者数量
                    
                    // 清空之前的数据
                    for ($i = 0; $i < $days; $i++) {
                        $date = date('Ymd', strtotime("-{$i} days"));
                        $redis->delete("uv:daily:{$date}");
                    }
                    $redis->delete('uv:weekly');
                    
                    // 生成模拟数据
                    $dailyUV = [];
                    $allVisitors = [];
                    
                    for ($i = 0; $i < $days; $i++) {
                        $date = date('Ymd', strtotime("-{$i} days"));
                        $key = "uv:daily:{$date}";
                        
                        // 随机选择用户
                        $visitors = [];
                        for ($j = 0; $j < $visitorsPerDay; $j++) {
                            $userId = mt_rand(1, $users);
                            $visitors[] = $userId;
                            $allVisitors[] = $userId;
                            
                            // 添加到当日UV统计
                            $redis->pfAdd($key, "user:{$userId}");
                            
                            // 添加到周UV统计
                            $redis->pfAdd('uv:weekly', "user:{$userId}");
                        }
                        
                        // 统计当日UV
                        $dailyUV[$date] = $redis->pfCount($key);
                    }
                    
                    // 统计周UV
                    $weeklyUV = $redis->pfCount('uv:weekly');
                    
                    // 计算实际的独立访客数（去重后）
                    $actualUniqueVisitors = count(array_unique($allVisitors));
                    
                    $result = [
                        'status' => 'success',
                        'daily_uv' => $dailyUV,
                        'weekly_uv' => $weeklyUV,
                        'actual_unique_visitors' => $actualUniqueVisitors,
                        'error_rate' => $actualUniqueVisitors > 0 ? abs($weeklyUV - $actualUniqueVisitors) / $actualUniqueVisitors * 100 : 0,
                        'simulation_params' => [
                            'days' => $days,
                            'total_user_pool' => $users,
                            'visitors_per_day' => $visitorsPerDay,
                        ],
                    ];
                    break;
                    
                case 'record':
                    // 记录实际访问
                    $userId = $this->request->param('user_id', '');
                    $page = $this->request->param('page', 'home');
                    
                    if (empty($userId)) {
                        // 如果没有提供用户ID，使用客户端IP
                        $userId = $this->request->ip();
                    }
                    
                    // 获取当前日期
                    $today = date('Ymd');
                    
                    // 记录每日UV
                    $dailyKey = "uv:daily:{$today}";
                    $redis->pfAdd($dailyKey, "user:{$userId}");
                    
                    // 记录每日页面UV
                    $pageKey = "uv:daily:{$today}:page:{$page}";
                    $redis->pfAdd($pageKey, "user:{$userId}");
                    
                    // 记录每月UV
                    $month = date('Ym');
                    $monthlyKey = "uv:monthly:{$month}";
                    $redis->pfAdd($monthlyKey, "user:{$userId}");
                    
                    // 获取统计数据
                    $dailyUV = $redis->pfCount($dailyKey);
                    $pageUV = $redis->pfCount($pageKey);
                    $monthlyUV = $redis->pfCount($monthlyKey);
                    
                    $result = [
                        'status' => 'success',
                        'message' => '访问记录已添加',
                        'user_id' => $userId,
                        'page' => $page,
                        'date' => $today,
                        'daily_uv' => $dailyUV,
                        'page_uv' => $pageUV,
                        'monthly_uv' => $monthlyUV,
                    ];
                    break;
                    
                case 'stats':
                    // 获取统计数据
                    $date = $this->request->param('date', date('Ymd'));
                    $page = $this->request->param('page', '');
                    
                    $dailyKey = "uv:daily:{$date}";
                    $dailyUV = $redis->pfCount($dailyKey);
                    
                    $stats = [
                        'date' => $date,
                        'daily_uv' => $dailyUV,
                    ];
                    
                    if (!empty($page)) {
                        $pageKey = "uv:daily:{$date}:page:{$page}";
                        $pageUV = $redis->pfCount($pageKey);
                        $stats['page'] = $page;
                        $stats['page_uv'] = $pageUV;
                    }
                    
                    // 获取最近7天的数据
                    $recentDays = [];
                    for ($i = 0; $i < 7; $i++) {
                        $day = date('Ymd', strtotime("-{$i} days"));
                        $dayKey = "uv:daily:{$day}";
                        $dayUV = $redis->pfCount($dayKey);
                        $recentDays[$day] = $dayUV;
                    }
                    
                    $stats['recent_days'] = $recentDays;
                    
                    // 获取当月数据
                    $month = date('Ym');
                    $monthlyKey = "uv:monthly:{$month}";
                    $monthlyUV = $redis->pfCount($monthlyKey);
                    $stats['month'] = $month;
                    $stats['monthly_uv'] = $monthlyUV;
                    
                    $result = [
                        'status' => 'success',
                        'stats' => $stats,
                    ];
                    break;
            }
            
            return $this->success('UV统计操作成功', $result);
        } catch (\Throwable $e) {
            return $this->error('UV统计操作失败：' . $e->getMessage());
        }
    }
    
    /**
     * 搜索关键词统计示例
     */
    public function searchKeywords()
    {
        try {
            $redis = Redis::hyperLogLog();
            $action = $this->request->param('action', 'stats');
            
            switch ($action) {
                case 'record':
                    // 记录搜索关键词
                    $keyword = $this->request->param('keyword', '');
                    $userId = $this->request->param('user_id', $this->request->ip());
                    
                    if (empty($keyword)) {
                        $result = [
                            'status' => 'error',
                            'message' => '关键词不能为空',
                        ];
                        break;
                    }
                    
                    // 获取当前日期
                    $today = date('Ymd');
                    
                    // 记录关键词搜索次数
                    $totalSearchKey = "search:count:{$keyword}";
                    $redis->string()->increment($totalSearchKey);
                    
                    // 记录关键词每日搜索次数
                    $dailySearchKey = "search:count:{$keyword}:{$today}";
                    $redis->string()->increment($dailySearchKey);
                    
                    // 记录搜索关键词的独立用户数
                    $keywordUVKey = "search:uv:{$keyword}";
                    $redis->pfAdd($keywordUVKey, "user:{$userId}");
                    
                    // 记录每日搜索关键词的独立用户数
                    $dailyKeywordUVKey = "search:uv:{$keyword}:{$today}";
                    $redis->pfAdd($dailyKeywordUVKey, "user:{$userId}");
                    
                    // 记录所有搜索的关键词集合
                    $redis->set()->sAdd("search:keywords", $keyword);
                    
                    // 记录每日搜索的关键词集合
                    $redis->set()->sAdd("search:keywords:{$today}", $keyword);
                    
                    // 获取统计数据
                    $totalCount = $redis->string()->get($totalSearchKey);
                    $dailyCount = $redis->string()->get($dailySearchKey);
                    $uniqueUsers = $redis->pfCount($keywordUVKey);
                    $dailyUniqueUsers = $redis->pfCount($dailyKeywordUVKey);
                    
                    $result = [
                        'status' => 'success',
                        'message' => '搜索记录已添加',
                        'keyword' => $keyword,
                        'user_id' => $userId,
                        'date' => $today,
                        'total_search_count' => $totalCount,
                        'daily_search_count' => $dailyCount,
                        'unique_users' => $uniqueUsers,
                        'daily_unique_users' => $dailyUniqueUsers,
                    ];
                    break;
                    
                case 'keyword_stats':
                    // 获取特定关键词的统计数据
                    $keyword = $this->request->param('keyword', '');
                    
                    if (empty($keyword)) {
                        $result = [
                            'status' => 'error',
                            'message' => '关键词不能为空',
                        ];
                        break;
                    }
                    
                    // 获取当前日期
                    $today = date('Ymd');
                    
                    // 获取统计数据
                    $totalSearchKey = "search:count:{$keyword}";
                    $dailySearchKey = "search:count:{$keyword}:{$today}";
                    $keywordUVKey = "search:uv:{$keyword}";
                    $dailyKeywordUVKey = "search:uv:{$keyword}:{$today}";
                    
                    $totalCount = $redis->string()->get($totalSearchKey) ?: 0;
                    $dailyCount = $redis->string()->get($dailySearchKey) ?: 0;
                    $uniqueUsers = $redis->pfCount($keywordUVKey);
                    $dailyUniqueUsers = $redis->pfCount($dailyKeywordUVKey);
                    
                    $result = [
                        'status' => 'success',
                        'keyword' => $keyword,
                        'stats' => [
                            'total_search_count' => $totalCount,
                            'daily_search_count' => $dailyCount,
                            'unique_users' => $uniqueUsers,
                            'daily_unique_users' => $dailyUniqueUsers,
                        ],
                    ];
                    break;
                    
                case 'popular_keywords':
                    // 获取热门关键词
                    $limit = $this->request->param('limit', 10, 'intval');
                    $date = $this->request->param('date', date('Ymd'));
                    
                    $keywordsKey = "search:keywords";
                    if ($date) {
                        $keywordsKey = "search:keywords:{$date}";
                    }
                    
                    // 获取所有关键词
                    $allKeywords = $redis->set()->sMembers($keywordsKey);
                    
                    // 计算每个关键词的搜索次数和UV
                    $keywordStats = [];
                    foreach ($allKeywords as $keyword) {
                        $countKey = $date ? "search:count:{$keyword}:{$date}" : "search:count:{$keyword}";
                        $uvKey = $date ? "search:uv:{$keyword}:{$date}" : "search:uv:{$keyword}";
                        
                        $count = $redis->string()->get($countKey) ?: 0;
                        $uv = $redis->pfCount($uvKey);
                        
                        $keywordStats[] = [
                            'keyword' => $keyword,
                            'search_count' => $count,
                            'unique_users' => $uv,
                        ];
                    }
                    
                    // 按搜索次数排序
                    usort($keywordStats, function($a, $b) {
                        return $b['search_count'] <=> $a['search_count'];
                    });
                    
                    // 限制返回数量
                    $keywordStats = array_slice($keywordStats, 0, $limit);
                    
                    $result = [
                        'status' => 'success',
                        'date' => $date ?: 'all time',
                        'popular_keywords' => $keywordStats,
                        'count' => count($keywordStats),
                    ];
                    break;
                    
                case 'stats':
                default:
                    // 模拟数据和统计
                    // 清空测试数据
                    $redis->delete('search:keywords');
                    
                    // 模拟关键词
                    $keywords = [
                        'iPhone', 'Android', 'PHP', 'Java', 'Python', 
                        'Redis', 'ThinkPHP', 'Laravel', 'MySQL', 'Linux'
                    ];
                    
                    // 模拟用户
                    $userCount = 1000;
                    
                    // 模拟搜索行为
                    $searchCount = 5000;
                    
                    for ($i = 0; $i < $searchCount; $i++) {
                        $keyword = $keywords[array_rand($keywords)];
                        $userId = mt_rand(1, $userCount);
                        
                        // 记录关键词搜索次数
                        $totalSearchKey = "search:count:{$keyword}";
                        $redis->string()->increment($totalSearchKey);
                        
                        // 记录搜索关键词的独立用户数
                        $keywordUVKey = "search:uv:{$keyword}";
                        $redis->pfAdd($keywordUVKey, "user:{$userId}");
                        
                        // 记录所有搜索的关键词集合
                        $redis->set()->sAdd("search:keywords", $keyword);
                    }
                    
                    // 统计结果
                    $stats = [];
                    
                    foreach ($keywords as $keyword) {
                        $totalSearchKey = "search:count:{$keyword}";
                        $keywordUVKey = "search:uv:{$keyword}";
                        
                        $count = $redis->string()->get($totalSearchKey) ?: 0;
                        $uv = $redis->pfCount($keywordUVKey);
                        
                        $stats[$keyword] = [
                            'search_count' => $count,
                            'unique_users' => $uv,
                        ];
                    }
                    
                    // 按搜索次数排序
                    arsort($stats);
                    
                    $result = [
                        'status' => 'success',
                        'simulation_params' => [
                            'keywords' => $keywords,
                            'user_count' => $userCount,
                            'search_count' => $searchCount,
                        ],
                        'stats' => $stats,
                    ];
                    break;
            }
            
            return $this->success('搜索关键词统计操作成功', $result);
        } catch (\Throwable $e) {
            return $this->error('搜索关键词统计操作失败：' . $e->getMessage());
        }
    }
} 