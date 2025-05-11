<?php
declare(strict_types=1);

namespace app\controller\redis;

use app\controller\RedisDemo;
use app\facade\Redis;
use think\facade\View;

/**
 * Redis ZSet(有序集合)类型演示控制器
 * 
 * 演示Redis ZSet类型的常见应用场景
 */
class ZSetDemo extends RedisDemo
{
    /**
     * 演示页面
     */
    public function index()
    {
        return View::fetch('redis/zset/index');
    }
    
    /**
     * 基本用法示例
     */
    public function basic()
    {
        try {
            $redis = Redis::zset();
            $key = 'zset_demo_basic';
            
            // 清空之前的测试数据
            $redis->delete($key);
            
            // 添加元素
            $redis->zAdd($key, 1, 'member1');
            $redis->zAdd($key, 2, 'member2');
            $redis->zAdd($key, 3, 'member3');
            $redis->zAdd($key, 4, 'member4');
            
            // 批量添加元素
            $redis->zMAdd($key, [
                'member5' => 5,
                'member6' => 6
            ]);
            
            // 获取所有元素（按分数从小到大）
            $members = $redis->zRange($key, 0, -1);
            $membersWithScores = $redis->zRange($key, 0, -1, true);
            
            // 获取所有元素（按分数从大到小）
            $membersDesc = $redis->zRevRange($key, 0, -1);
            $membersDescWithScores = $redis->zRevRange($key, 0, -1, true);
            
            // 获取指定分数范围的元素
            $rangeByScore = $redis->zRangeByScore($key, 2, 5);
            $rangeByScoreWithScores = $redis->zRangeByScore($key, 2, 5, true);
            
            // 获取元素数量
            $count = $redis->zCard($key);
            $countInRange = $redis->zCount($key, 2, 5);
            
            // 获取元素的分数
            $score = $redis->zScore($key, 'member3');
            
            // 获取元素的排名（从0开始）
            $rank = $redis->zRank($key, 'member3');
            $revRank = $redis->zRevRank($key, 'member3');
            
            // 增加元素的分数
            $newScore = $redis->zIncrBy($key, 2.5, 'member1');
            
            // 再次获取所有元素和分数
            $finalMembers = $redis->zRange($key, 0, -1, true);
            
            // 移除元素
            $redis->zRem($key, 'member4');
            $membersAfterRemove = $redis->zRange($key, 0, -1);
            
            // 根据排名范围移除元素
            $redis->zRemRangeByRank($key, 0, 1); // 移除排名0到1的元素
            $membersAfterRangeRemove = $redis->zRange($key, 0, -1);
            
            return $this->success('ZSet基本用法演示成功', [
                'members' => $members,
                'members_with_scores' => $membersWithScores,
                'members_desc' => $membersDesc,
                'members_desc_with_scores' => $membersDescWithScores,
                'range_by_score' => $rangeByScore,
                'range_by_score_with_scores' => $rangeByScoreWithScores,
                'count' => $count,
                'count_in_range' => $countInRange,
                'score_of_member3' => $score,
                'rank_of_member3' => $rank,
                'rev_rank_of_member3' => $revRank,
                'new_score_of_member1' => $newScore,
                'final_members' => $finalMembers,
                'members_after_remove' => $membersAfterRemove,
                'members_after_range_remove' => $membersAfterRangeRemove,
            ]);
        } catch (\Throwable $e) {
            return $this->error('ZSet基本用法演示失败：' . $e->getMessage());
        }
    }
    
    /**
     * 排行榜示例
     */
    public function leaderboard()
    {
        try {
            $redis = Redis::zset();
            $action = $this->request->param('action', 'view');
            $userId = $this->request->param('user_id', 0, 'intval');
            $score = $this->request->param('score', 0, 'floatval');
            
            $leaderboardKey = 'leaderboard:scores';
            
            switch ($action) {
                case 'add':
                    // 添加或更新用户分数
                    if ($userId > 0) {
                        // 获取当前分数
                        $currentScore = $redis->zScore($leaderboardKey, $userId) ?: 0;
                        
                        // 只更新更高的分数（避免覆盖历史最高分）
                        if ($score > $currentScore) {
                            $redis->zAdd($leaderboardKey, $score, $userId);
                            $message = "用户 {$userId} 的分数已更新为 {$score}";
                        } else {
                            $message = "用户 {$userId} 的当前分数 {$currentScore} 高于或等于提交的分数 {$score}，未更新";
                        }
                        
                        $result = [
                            'status' => 'success',
                            'message' => $message,
                            'user_id' => $userId,
                            'score' => $score,
                            'current_score' => $redis->zScore($leaderboardKey, $userId),
                        ];
                    } else {
                        $result = [
                            'status' => 'error',
                            'message' => '用户ID不能为空',
                        ];
                    }
                    break;
                    
                case 'increment':
                    // 增加用户分数
                    if ($userId > 0) {
                        $newScore = $redis->zIncrBy($leaderboardKey, $score, $userId);
                        $result = [
                            'status' => 'success',
                            'message' => "用户 {$userId} 的分数已增加 {$score}",
                            'user_id' => $userId,
                            'increment' => $score,
                            'new_score' => $newScore,
                        ];
                    } else {
                        $result = [
                            'status' => 'error',
                            'message' => '用户ID不能为空',
                        ];
                    }
                    break;
                    
                case 'get_user_rank':
                    // 获取用户排名
                    if ($userId > 0) {
                        $score = $redis->zScore($leaderboardKey, $userId);
                        $rank = $redis->zRevRank($leaderboardKey, $userId); // 使用zRevRank获取分数从高到低的排名
                        
                        $result = [
                            'status' => 'success',
                            'user_id' => $userId,
                            'score' => $score,
                            'rank' => $rank !== null ? $rank + 1 : null, // 排名从1开始
                            'message' => $rank !== null ? "用户 {$userId} 的排名为第 " . ($rank + 1) . " 名" : "用户 {$userId} 不在排行榜中",
                        ];
                    } else {
                        $result = [
                            'status' => 'error',
                            'message' => '用户ID不能为空',
                        ];
                    }
                    break;
                    
                case 'get_top':
                    // 获取前N名
                    $limit = $this->request->param('limit', 10, 'intval');
                    $topScores = $redis->zRevRange($leaderboardKey, 0, $limit - 1, true);
                    
                    $formattedScores = [];
                    $rank = 1;
                    foreach ($topScores as $userId => $score) {
                        $formattedScores[] = [
                            'rank' => $rank++,
                            'user_id' => $userId,
                            'score' => $score,
                        ];
                    }
                    
                    $result = [
                        'status' => 'success',
                        'top_scores' => $formattedScores,
                        'count' => count($formattedScores),
                    ];
                    break;
                    
                case 'get_rank_range':
                    // 获取指定排名范围的用户
                    $start = $this->request->param('start', 0, 'intval');
                    $end = $this->request->param('end', 9, 'intval');
                    
                    $rangeScores = $redis->zRevRange($leaderboardKey, $start, $end, true);
                    
                    $formattedScores = [];
                    $rank = $start + 1;
                    foreach ($rangeScores as $userId => $score) {
                        $formattedScores[] = [
                            'rank' => $rank++,
                            'user_id' => $userId,
                            'score' => $score,
                        ];
                    }
                    
                    $result = [
                        'status' => 'success',
                        'range_scores' => $formattedScores,
                        'start' => $start + 1, // 从1开始显示排名
                        'end' => $start + count($formattedScores),
                        'count' => count($formattedScores),
                    ];
                    break;
                    
                case 'get_neighbors':
                    // 获取用户附近的排名
                    if ($userId > 0) {
                        $rank = $redis->zRevRank($leaderboardKey, $userId);
                        
                        if ($rank !== null) {
                            $count = $this->request->param('count', 2, 'intval');
                            
                            // 计算范围，确保不超出边界
                            $start = max(0, $rank - $count);
                            $end = $rank + $count;
                            
                            $neighbors = $redis->zRevRange($leaderboardKey, $start, $end, true);
                            
                            $formattedNeighbors = [];
                            $currentRank = $start + 1;
                            foreach ($neighbors as $id => $score) {
                                $formattedNeighbors[] = [
                                    'rank' => $currentRank++,
                                    'user_id' => $id,
                                    'score' => $score,
                                    'is_current' => ($id == $userId),
                                ];
                            }
                            
                            $result = [
                                'status' => 'success',
                                'user_id' => $userId,
                                'rank' => $rank + 1,
                                'neighbors' => $formattedNeighbors,
                                'count' => count($formattedNeighbors),
                            ];
                        } else {
                            $result = [
                                'status' => 'error',
                                'message' => "用户 {$userId} 不在排行榜中",
                            ];
                        }
                    } else {
                        $result = [
                            'status' => 'error',
                            'message' => '用户ID不能为空',
                        ];
                    }
                    break;
                    
                case 'clear':
                    // 清空排行榜
                    $redis->delete($leaderboardKey);
                    $result = [
                        'status' => 'success',
                        'message' => '排行榜已清空',
                    ];
                    break;
                    
                case 'view':
                default:
                    // 查看排行榜
                    $totalUsers = $redis->zCard($leaderboardKey);
                    $topScores = $redis->zRevRange($leaderboardKey, 0, 9, true);
                    
                    $formattedScores = [];
                    $rank = 1;
                    foreach ($topScores as $userId => $score) {
                        $formattedScores[] = [
                            'rank' => $rank++,
                            'user_id' => $userId,
                            'score' => $score,
                        ];
                    }
                    
                    $result = [
                        'status' => 'success',
                        'total_users' => $totalUsers,
                        'top_scores' => $formattedScores,
                    ];
                    break;
            }
            
            return $this->success('排行榜操作成功', $result);
        } catch (\Throwable $e) {
            return $this->error('排行榜操作失败：' . $e->getMessage());
        }
    }
    
    /**
     * 延迟队列示例
     */
    public function delayedQueue()
    {
        try {
            $redis = Redis::zset();
            $action = $this->request->param('action', 'stats');
            $id = $this->request->param('id', '');
            $delay = $this->request->param('delay', 60, 'intval'); // 默认延迟60秒
            $payload = $this->request->param('payload', '');
            
            $queueKey = 'delayed_queue:jobs';
            
            switch ($action) {
                case 'add':
                    // 添加延迟任务
                    if (!empty($payload)) {
                        $id = $id ?: uniqid('job_');
                        $executeAt = time() + $delay;
                        
                        $job = [
                            'id' => $id,
                            'payload' => $payload,
                            'create_time' => time(),
                            'execute_at' => $executeAt,
                        ];
                        
                        // 添加到有序集合，分数为执行时间
                        $redis->zAdd($queueKey, $executeAt, json_encode($job, JSON_UNESCAPED_UNICODE));
                        
                        $result = [
                            'status' => 'success',
                            'message' => "任务 {$id} a已添加到延迟队列，将在 " . date('Y-m-d H:i:s', $executeAt) . " 执行",
                            'job' => $job,
                        ];
                    } else {
                        $result = [
                            'status' => 'error',
                            'message' => '任务内容不能为空',
                        ];
                    }
                    break;
                    
                case 'get_ready':
                    // 获取已到期可执行的任务
                    $now = time();
                    $readyJobs = $redis->zRangeByScore($queueKey, 0, $now);
                    
                    $formattedJobs = [];
                    foreach ($readyJobs as $jobJson) {
                        $job = json_decode($jobJson, true);
                        $formattedJobs[] = $job;
                    }
                    
                    $result = [
                        'status' => 'success',
                        'ready_jobs' => $formattedJobs,
                        'count' => count($formattedJobs),
                        'now' => $now,
                        'formatted_now' => date('Y-m-d H:i:s', $now),
                    ];
                    break;
                    
                case 'process_one':
                    // 处理一个已到期的任务
                    $now = time();
                    $readyJobs = $redis->zRangeByScore($queueKey, 0, $now, false, false, ['limit' => [0, 1]]);
                    
                    if (!empty($readyJobs)) {
                        $jobJson = $readyJobs[0];
                        $job = json_decode($jobJson, true);
                        
                        // 从队列中移除任务
                        $redis->zRem($queueKey, $jobJson);
                        
                        // 模拟处理任务
                        $processingResult = "任务 {$job['id']} 已处理，内容：{$job['payload']}";
                        
                        $result = [
                            'status' => 'success',
                            'message' => '已处理一个任务',
                            'job' => $job,
                            'processing_result' => $processingResult,
                        ];
                    } else {
                        $result = [
                            'status' => 'info',
                            'message' => '没有可处理的任务',
                        ];
                    }
                    break;
                    
                case 'remove':
                    // 移除指定任务
                    if (!empty($id)) {
                        $allJobs = $redis->zRange($queueKey, 0, -1);
                        $found = false;
                        
                        foreach ($allJobs as $jobJson) {
                            $job = json_decode($jobJson, true);
                            if ($job['id'] === $id) {
                                $redis->zRem($queueKey, $jobJson);
                                $found = true;
                                break;
                            }
                        }
                        
                        if ($found) {
                            $result = [
                                'status' => 'success',
                                'message' => "任务 {$id} 已从队列中移除",
                            ];
                        } else {
                            $result = [
                                'status' => 'error',
                                'message' => "任务 {$id} 不存在",
                            ];
                        }
                    } else {
                        $result = [
                            'status' => 'error',
                            'message' => '任务ID不能为空',
                        ];
                    }
                    break;
                    
                case 'clear':
                    // 清空队列
                    $redis->delete($queueKey);
                    $result = [
                        'status' => 'success',
                        'message' => '延迟队列已清空',
                    ];
                    break;
                    
                case 'stats':
                default:
                    // 队列统计信息
                    $now = time();
                    $totalJobs = $redis->zCard($queueKey);
                    $readyJobs = $redis->zCount($queueKey, 0, $now);
                    $pendingJobs = $redis->zCount($queueKey, $now + 1, '+inf');
                    
                    // 获取近期将要执行的任务
                    $upcomingJobs = $redis->zRangeByScore($queueKey, $now + 1, '+inf', true, false, ['limit' => [0, 5]]);
                    
                    $formattedUpcoming = [];
                    foreach ($upcomingJobs as $jobJson => $score) {
                        $job = json_decode($jobJson, true);
                        $job['time_left'] = $score - $now;
                        $job['formatted_execute_time'] = date('Y-m-d H:i:s', $score);
                        $formattedUpcoming[] = $job;
                    }
                    
                    $result = [
                        'status' => 'success',
                        'total_jobs' => $totalJobs,
                        'ready_jobs' => $readyJobs,
                        'pending_jobs' => $pendingJobs,
                        'now' => $now,
                        'formatted_now' => date('Y-m-d H:i:s', $now),
                        'upcoming_jobs' => $formattedUpcoming,
                    ];
                    break;
            }
            
            return $this->success('延迟队列操作成功', $result);
        } catch (\Throwable $e) {
            return $this->error('延迟队列操作失败：' . $e->getMessage());
        }
    }
    
    /**
     * 权重搜索示例
     */
    public function weightedSearch()
    {
        try {
            $redis = Redis::zset();
            $action = $this->request->param('action', 'search');
            $keywordIndexKey = 'search:keywords:index';
            
            switch ($action) {
                case 'index':
                    // 建立搜索索引
                    // 清空旧索引
                    $redis->delete($keywordIndexKey);
                    
                    // 示例内容数据
                    $articles = [
                        1 => [
                            'title' => 'ThinkPHP 快速入门教程',
                            'content' => 'ThinkPHP是一个免费开源的，快速、简单的面向对象的轻量级PHP开发框架。',
                            'tags' => ['PHP', 'ThinkPHP', '框架', '入门']
                        ],
                        2 => [
                            'title' => 'Redis 高级特性详解',
                            'content' => 'Redis不仅仅是简单的键值存储，还支持多种数据结构如列表、集合、有序集合等。',
                            'tags' => ['Redis', '缓存', '数据库', '高级']
                        ],
                        3 => [
                            'title' => 'PHP与Redis结合使用指南',
                            'content' => '本文详细介绍PHP如何与Redis结合使用，包括连接池、事务等高级特性。',
                            'tags' => ['PHP', 'Redis', '缓存', '教程']
                        ],
                        4 => [
                            'title' => 'MySQL性能优化实战',
                            'content' => '通过索引、查询优化、配置调整等方式提升MySQL性能。',
                            'tags' => ['MySQL', '数据库', '性能', '优化']
                        ],
                        5 => [
                            'title' => 'Web开发中的缓存策略',
                            'content' => '详解Web开发中各种缓存策略，包括浏览器缓存、Redis缓存、CDN缓存等。',
                            'tags' => ['Web', '缓存', 'Redis', 'CDN']
                        ]
                    ];
                    
                    // 为每篇文章建立关键词索引
                    foreach ($articles as $id => $article) {
                        // 提取关键词并赋予不同权重
                        // 标题中的关键词权重最高
                        $titleWords = preg_split('/\s+/', $article['title']);
                        foreach ($titleWords as $word) {
                            $word = strtolower($word);
                            if (mb_strlen($word) > 1) {
                                $redis->zIncrBy($keywordIndexKey, 10, json_encode([
                                    'keyword' => $word,
                                    'article_id' => $id
                                ], JSON_UNESCAPED_UNICODE));
                            }
                        }
                        
                        // 内容中的关键词权重其次
                        $contentWords = preg_split('/\s+/', $article['content']);
                        foreach ($contentWords as $word) {
                            $word = strtolower($word);
                            if (mb_strlen($word) > 1) {
                                $redis->zIncrBy($keywordIndexKey, 5, json_encode([
                                    'keyword' => $word,
                                    'article_id' => $id
                                ], JSON_UNESCAPED_UNICODE));
                            }
                        }
                        
                        // 标签的权重最低
                        foreach ($article['tags'] as $tag) {
                            $tag = strtolower($tag);
                            $redis->zIncrBy($keywordIndexKey, 3, json_encode([
                                'keyword' => $tag,
                                'article_id' => $id
                            ], JSON_UNESCAPED_UNICODE));
                        }
                    }
                    
                    // 保存文章内容
                    foreach ($articles as $id => $article) {
                        $redis->string()->set("article:{$id}", $article, 3600);
                    }
                    
                    $indexSize = $redis->zCard($keywordIndexKey);
                    
                    $result = [
                        'status' => 'success',
                        'message' => '索引构建完成',
                        'index_size' => $indexSize,
                        'articles' => $articles,
                    ];
                    break;
                    
                case 'search':
                    // 执行搜索
                    $keyword = strtolower($this->request->param('keyword', ''));
                    $limit = $this->request->param('limit', 5, 'intval');
                    
                    if (empty($keyword)) {
                        $result = [
                            'status' => 'error',
                            'message' => '搜索关键词不能为空',
                        ];
                        break;
                    }
                    
                    // 搜索结果(article_id => 分数)
                    $searchResults = [];
                    
                    // 获取所有关键词-文章对
                    $allIndexes = $redis->zRevRange($keywordIndexKey, 0, -1, true);
                    
                    // 遍历查找匹配的关键词
                    foreach ($allIndexes as $indexJson => $score) {
                        $index = json_decode($indexJson, true);
                        if (strpos($index['keyword'], $keyword) !== false) {
                            $articleId = $index['article_id'];
                            if (!isset($searchResults[$articleId])) {
                                $searchResults[$articleId] = 0;
                            }
                            $searchResults[$articleId] += $score;
                        }
                    }
                    
                    // 按分数降序排序
                    arsort($searchResults);
                    
                    // 截取前N个结果
                    $searchResults = array_slice($searchResults, 0, $limit, true);
                    
                    // 获取文章详情
                    $articles = [];
                    foreach ($searchResults as $articleId => $score) {
                        $article = $redis->string()->get("article:{$articleId}", true);
                        if ($article) {
                            $article['id'] = $articleId;
                            $article['score'] = $score;
                            $articles[] = $article;
                        }
                    }
                    
                    $result = [
                        'status' => 'success',
                        'keyword' => $keyword,
                        'results' => $articles,
                        'count' => count($articles),
                    ];
                    break;
            }
            
            return $this->success('权重搜索操作成功', $result);
        } catch (\Throwable $e) {
            return $this->error('权重搜索操作失败：' . $e->getMessage());
        }
    }
} 