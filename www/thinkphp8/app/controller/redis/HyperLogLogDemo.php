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
     * Redis HyperLogLog实例
     * 
     * @var \app\service\redis\HyperLogLogService
     */
    protected $hyperLogLog;

    /**
     * Redis原始实例
     * 
     * @var \Redis
     */
    protected $redis;

    /**
     * 初始化方法
     */
    protected function initialize()
    {
        parent::initialize();
        $this->hyperLogLog = Redis::hyperLogLog();
        $this->redis       = Redis::getRedis();
    }

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
            $key = 'hll_demo_basic';

            // 清空之前的测试数据
            $this->hyperLogLog->delete($key);

            // 添加元素
            $this->hyperLogLog->pfAdd($key, 'value1');
            $this->hyperLogLog->pfAdd($key, 'value2');
            $this->hyperLogLog->pfAdd($key, 'value3');
            $this->hyperLogLog->pfAdd($key, 'value1'); // 重复添加，将被忽略

            // 批量添加
            $this->hyperLogLog->pfAdd($key, ['value4', 'value5', 'value6']);

            // 统计基数
            $count = $this->hyperLogLog->pfCount($key);

            // 创建第二个HyperLogLog
            $key2 = 'hll_demo_basic2';
            $this->hyperLogLog->delete($key2);
            $this->hyperLogLog->pfAdd($key2, ['value5', 'value6', 'value7', 'value8']);

            // 分别统计
            $count1 = $this->hyperLogLog->pfCount($key);
            $count2 = $this->hyperLogLog->pfCount($key2);

            // 合并统计
            $mergeCount = $this->hyperLogLog->pfMergeCount([$key, $key2]);

            // 合并到新的HyperLogLog
            $mergeKey = 'hll_demo_merge';
            $this->hyperLogLog->pfMerge($mergeKey, [$key, $key2]);
            $mergedCount = $this->hyperLogLog->pfCount($mergeKey);

            return $this->success('HyperLogLog基本用法演示成功', [
                'key1_count'       => $count1,
                'key2_count'       => $count2,
                'merge_count'      => $mergeCount,
                'merged_key_count' => $mergedCount,
                'explanation'      => [
                    'precision'    => '标准误差约为0.81%',
                    'memory_usage' => '无论数据量大小，只占用12KB内存',
                    'limitations'  => '不能获取具体的元素，只能统计近似基数',
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
            $action = $this->request->param('action', 'simulate');

            switch ($action) {
                case 'simulate':
                    $result = $this->uvStatsSimulate();
                    break;
                case 'record':
                    $result = $this->uvStatsRecord();
                    break;
                case 'stats':
                    $result = $this->uvStatsGetStats();
                    break;
                default:
                    return $this->error('无效的操作类型');
            }

            return $this->success('UV统计操作成功', $result);
        } catch (\Throwable $e) {
            return $this->error('UV统计操作失败：' . $e->getMessage());
        }
    }

    /**
     * 模拟UV数据
     * 
     * @return array
     */
    protected function uvStatsSimulate(): array
    {
        $days           = $this->getIntParam('days', 7);
        $users          = $this->getIntParam('users', 10000);
        $visitorsPerDay = $this->getIntParam('visitors_per_day', 2000);

        // 参数验证
        if ($days <= 0 || $users <= 0 || $visitorsPerDay <= 0) {
            return [
                'status'  => 'error',
                'message' => '参数必须为正整数',
            ];
        }

        // 清空之前的数据
        for ($i = 0; $i < $days; $i++) {
            $date = date('Ymd', strtotime("-{$i} days"));
            $this->hyperLogLog->delete("uv:daily:{$date}");
        }
        $this->hyperLogLog->delete('uv:weekly');

        // 生成模拟数据
        $dailyUV     = [];
        $allVisitors = [];

        for ($i = 0; $i < $days; $i++) {
            $date = date('Ymd', strtotime("-{$i} days"));
            $key  = "uv:daily:{$date}";

            $dayVisitors = [];
            for ($j = 0; $j < $visitorsPerDay; $j++) {
                $userId        = mt_rand(1, $users);
                $dayVisitors[] = "user:{$userId}"; // Prepare for pfAdd
                $allVisitors[] = $userId; // For actual unique count
            }

            // 批量添加到当日UV统计
            if (!empty($dayVisitors)) {
                $this->hyperLogLog->pfAdd($key, $dayVisitors);
                // 批量添加到周UV统计
                $this->hyperLogLog->pfAdd('uv:weekly', $dayVisitors);
            }

            // 统计当日UV
            $dailyUV[$date] = $this->hyperLogLog->pfCount($key);
        }

        // 统计周UV
        $weeklyUV = $this->hyperLogLog->pfCount('uv:weekly');

        // 计算实际的独立访客数（去重后）
        $actualUniqueVisitors = count(array_unique($allVisitors));

        return [
            'status'                 => 'success',
            'daily_uv'               => $dailyUV,
            'weekly_uv'              => $weeklyUV,
            'actual_unique_visitors' => $actualUniqueVisitors,
            'error_rate'             => $actualUniqueVisitors > 0 ? abs($weeklyUV - $actualUniqueVisitors) / $actualUniqueVisitors * 100 : 0,
            'simulation_params'      => [
                'days'             => $days,
                'total_user_pool'  => $users,
                'visitors_per_day' => $visitorsPerDay,
            ],
        ];
    }

    /**
     * 记录实际访问UV
     * 
     * @return array
     */
    protected function uvStatsRecord(): array
    {
        $userId = $this->request->param('user_id', '');
        $page   = $this->request->param('page', 'home');

        if (empty($userId)) {
            $userId = $this->request->ip();
        }

        $today = date('Ymd');
        $month = date('Ym');

        $dailyKey   = "uv:daily:{$today}";
        $pageKey    = "uv:daily:{$today}:page:{$page}";
        $monthlyKey = "uv:monthly:{$month}";

        $userIdentifier = "user:{$userId}";

        $this->hyperLogLog->pfAdd($dailyKey, $userIdentifier);
        $this->hyperLogLog->pfAdd($pageKey, $userIdentifier);
        $this->hyperLogLog->pfAdd($monthlyKey, $userIdentifier);

        $dailyUV   = $this->hyperLogLog->pfCount($dailyKey);
        $pageUV    = $this->hyperLogLog->pfCount($pageKey);
        $monthlyUV = $this->hyperLogLog->pfCount($monthlyKey);

        return [
            'status'     => 'success',
            'message'    => '访问记录已添加',
            'user_id'    => $userId,
            'page'       => $page,
            'date'       => $today,
            'daily_uv'   => $dailyUV,
            'page_uv'    => $pageUV,
            'monthly_uv' => $monthlyUV,
        ];
    }

    /**
     * 获取UV统计数据
     * 
     * @return array
     */
    protected function uvStatsGetStats(): array
    {
        $dateParam = $this->request->param('date', date('Ymd'));
        $page      = $this->request->param('page', '');

        $dailyKey = "uv:daily:{$dateParam}";
        $dailyUV  = $this->hyperLogLog->pfCount($dailyKey);

        $stats = [
            'date'     => $dateParam,
            'daily_uv' => $dailyUV,
        ];

        if (!empty($page)) {
            $pageKey          = "uv:daily:{$dateParam}:page:{$page}";
            $pageUV           = $this->hyperLogLog->pfCount($pageKey);
            $stats['page']    = $page;
            $stats['page_uv'] = $pageUV;
        }

        $recentDays = [];
        for ($i = 0; $i < 7; $i++) {
            $day              = date('Ymd', strtotime("-{$i} days"));
            $dayKey           = "uv:daily:{$day}";
            $recentDays[$day] = $this->hyperLogLog->pfCount($dayKey);
        }
        $stats['recent_days'] = $recentDays;

        $currentMonth        = date('Ym');
        $monthlyKey          = "uv:monthly:{$currentMonth}";
        $stats['month']      = $currentMonth;
        $stats['monthly_uv'] = $this->hyperLogLog->pfCount($monthlyKey);

        return [
            'status' => 'success',
            'stats'  => $stats,
        ];
    }

    /**
     * 搜索关键词统计示例
     */
    public function searchKeywords()
    {
        try {
            $action = $this->request->param('action', 'stats');

            switch ($action) {
                case 'record':
                    $result = $this->searchKeywordsRecord();
                    break;
                case 'keyword_stats':
                    $result = $this->searchKeywordsGetKeywordStats();
                    break;
                case 'popular_keywords':
                    $result = $this->searchKeywordsGetPopularKeywords();
                    break;
                case 'stats':
                default:
                    $result = $this->searchKeywordsSimulateStats();
                    break;
            }

            return $this->success('搜索关键词统计操作成功', $result);
        } catch (\Throwable $e) {
            return $this->error('搜索关键词统计操作失败：' . $e->getMessage());
        }
    }

    /**
     * 记录搜索关键词
     * 
     * @return array
     */
    protected function searchKeywordsRecord(): array
    {
        $keyword = trim((string) $this->request->param('keyword', ''));
        $userId  = $this->request->param('user_id', $this->request->ip());

        if (empty($keyword)) {
            return [
                'status'  => 'error',
                'message' => '关键词不能为空',
            ];
        }

        $today          = date('Ymd');
        $userIdentifier = "user:{$userId}";

        // 记录关键词搜索次数 (String)
        $totalSearchKey = "search:count:{$keyword}";
        $this->redis->incr($totalSearchKey);

        // 记录关键词每日搜索次数 (String)
        $dailySearchKey = "search:count:{$keyword}:{$today}";
        $this->redis->incr($dailySearchKey);

        // 记录搜索关键词的独立用户数 (HyperLogLog)
        $keywordUVKey = "search:uv:{$keyword}";
        $this->hyperLogLog->pfAdd($keywordUVKey, $userIdentifier);

        // 记录每日搜索关键词的独立用户数 (HyperLogLog)
        $dailyKeywordUVKey = "search:uv:{$keyword}:{$today}";
        $this->hyperLogLog->pfAdd($dailyKeywordUVKey, $userIdentifier);

        // 记录所有搜索的关键词集合 (Set)
        $this->redis->sAdd("search:keywords", $keyword);

        // 记录每日搜索的关键词集合 (Set)
        $this->redis->sAdd("search:keywords:{$today}", $keyword);

        // 获取统计数据
        $totalCount       = (int) $this->redis->get($totalSearchKey);
        $dailyCount       = (int) $this->redis->get($dailySearchKey);
        $uniqueUsers      = $this->hyperLogLog->pfCount($keywordUVKey);
        $dailyUniqueUsers = $this->hyperLogLog->pfCount($dailyKeywordUVKey);

        return [
            'status'             => 'success',
            'message'            => '搜索记录已添加',
            'keyword'            => $keyword,
            'user_id'            => $userId,
            'date'               => $today,
            'total_search_count' => $totalCount,
            'daily_search_count' => $dailyCount,
            'unique_users'       => $uniqueUsers,
            'daily_unique_users' => $dailyUniqueUsers,
        ];
    }

    /**
     * 获取特定关键词的统计数据
     * 
     * @return array
     */
    protected function searchKeywordsGetKeywordStats(): array
    {
        $keyword = trim((string) $this->request->param('keyword', ''));

        if (empty($keyword)) {
            return [
                'status'  => 'error',
                'message' => '关键词不能为空',
            ];
        }

        $today = date('Ymd');

        $totalSearchKey    = "search:count:{$keyword}";
        $dailySearchKey    = "search:count:{$keyword}:{$today}";
        $keywordUVKey      = "search:uv:{$keyword}";
        $dailyKeywordUVKey = "search:uv:{$keyword}:{$today}";

        $totalCount       = (int) ($this->redis->get($totalSearchKey) ?: 0);
        $dailyCount       = (int) ($this->redis->get($dailySearchKey) ?: 0);
        $uniqueUsers      = $this->hyperLogLog->pfCount($keywordUVKey);
        $dailyUniqueUsers = $this->hyperLogLog->pfCount($dailyKeywordUVKey);

        return [
            'status'  => 'success',
            'keyword' => $keyword,
            'stats'   => [
                'total_search_count' => $totalCount,
                'daily_search_count' => $dailyCount,
                'unique_users'       => $uniqueUsers,
                'daily_unique_users' => $dailyUniqueUsers,
            ],
        ];
    }

    /**
     * 获取热门关键词
     * 
     * @return array
     */
    protected function searchKeywordsGetPopularKeywords(): array
    {
        $limit     = $this->getIntParam('limit', 10);
        $dateParam = $this->request->param('date', ''); // 允许为空，表示所有时间

        $keywordsKey = $dateParam ? "search:keywords:{$dateParam}" : "search:keywords";

        $allKeywords = $this->redis->sMembers($keywordsKey);
        if (empty($allKeywords)) {
            return [
                'status'           => 'success',
                'date'             => $dateParam ?: 'all time',
                'popular_keywords' => [],
                'count'            => 0,
            ];
        }

        $keywordStats = [];
        foreach ($allKeywords as $keyword) {
            $countKey = $dateParam ? "search:count:{$keyword}:{$dateParam}" : "search:count:{$keyword}";
            $uvKey    = $dateParam ? "search:uv:{$keyword}:{$dateParam}" : "search:uv:{$keyword}";

            $count = (int) ($this->redis->get($countKey) ?: 0);
            $uv    = $this->hyperLogLog->pfCount($uvKey);

            $keywordStats[] = [
                'keyword'      => $keyword,
                'search_count' => $count,
                'unique_users' => $uv,
            ];
        }

        usort($keywordStats, function ($a, $b) {
            return $b['search_count'] <=> $a['search_count'];
        });

        $keywordStats = array_slice($keywordStats, 0, $limit);

        return [
            'status'           => 'success',
            'date'             => $dateParam ?: 'all time',
            'popular_keywords' => $keywordStats,
            'count'            => count($keywordStats),
        ];
    }

    /**
     * 模拟搜索关键词数据和统计
     * 
     * @return array
     */
    protected function searchKeywordsSimulateStats(): array
    {
        // 清空可能存在的旧模拟数据
        $simulatedKeywords = ['iPhone', 'Android', 'PHP', 'Java', 'Python', 'Redis', 'ThinkPHP', 'Laravel', 'MySQL', 'Linux'];
        foreach ($simulatedKeywords as $kw) {
            $this->hyperLogLog->delete("search:count:{$kw}");
            $this->hyperLogLog->delete("search:uv:{$kw}");
        }
        $this->redis->delete('search:keywords'); // 清空总的关键词集合

        $userCount   = $this->getIntParam('user_count', 1000);
        $searchCount = $this->getIntParam('search_count', 5000);

        // 参数验证
        if ($userCount <= 0 || $searchCount <= 0) {
            return [
                'status'  => 'error',
                'message' => '参数必须为正整数',
            ];
        }

        for ($i = 0; $i < $searchCount; $i++) {
            $keyword        = $simulatedKeywords[array_rand($simulatedKeywords)];
            $userId         = mt_rand(1, $userCount);
            $userIdentifier = "user:{$userId}";

            // 使用Redis原始实例处理字符串和集合操作
            $this->redis->incr("search:count:{$keyword}");
            $this->hyperLogLog->pfAdd("search:uv:{$keyword}", $userIdentifier);
            $this->redis->sAdd("search:keywords", $keyword);
        }

        $stats                = [];
        $allSimulatedKeywords = $this->redis->sMembers("search:keywords"); // 获取实际生成的关键词

        foreach ($allSimulatedKeywords as $keyword) {
            $count = (int) ($this->redis->get("search:count:{$keyword}") ?: 0);
            $uv    = $this->hyperLogLog->pfCount("search:uv:{$keyword}");

            $stats[$keyword] = [
                'search_count' => $count,
                'unique_users' => $uv,
            ];
        }

        arsort($stats); // Sort by search_count descending

        return [
            'status'            => 'success',
            'simulation_params' => [
                'keywords_pool' => $simulatedKeywords,
                'user_count'    => $userCount,
                'search_count'  => $searchCount,
            ],
            'stats'             => $stats,
        ];
    }

    /**
     * 获取整数参数
     * 
     * @param string $name 参数名
     * @param int $default 默认值
     * @return int
     */
    protected function getIntParam(string $name, int $default = 0): int
    {
        return $this->request->param($name, $default, 'intval');
    }
}