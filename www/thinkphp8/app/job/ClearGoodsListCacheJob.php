<?php
declare(strict_types=1);

namespace app\job;

use think\queue\Job;
use think\facade\Cache;
use think\facade\Log;

/**
 * 商品列表缓存清理队列任务
 */
class ClearGoodsListCacheJob
{
    /**
     * 缓存前缀
     */
    private const CACHE_PREFIX = 'goods:';
    
    /**
     * 批处理大小
     */
    private const BATCH_SIZE = 100;

    /**
     * 执行队列任务
     * 
     * @param Job $job 队列任务实例
     * @param array $data 任务数据
     */
    public function fire(Job $job, array $data): void
    {
        try {
            $startTime = microtime(true);
            $redis = Cache::store('redis')->handler();
            
            // 获取所有列表缓存键
            $indexKey = self::CACHE_PREFIX . "list_index";
            $listKeys = $redis->hKeys($indexKey);
            
            if (empty($listKeys)) {
                // 没有列表缓存，直接完成任务
                Log::info("ClearGoodsListCacheJob: 没有找到列表缓存");
                $job->delete();
                return;
            }
            
            // 计算清理的缓存数量
            $totalKeys = count($listKeys);
            $batchCount = ceil($totalKeys / self::BATCH_SIZE);
            
            // 批量处理，避免单个pipeline过大
            for ($i = 0; $i < $batchCount; $i++) {
                $start = $i * self::BATCH_SIZE;
                $end = min($start + self::BATCH_SIZE, $totalKeys);
                $batchKeys = array_slice($listKeys, $start, $end - $start);
                
                $pipeline = $redis->pipeline();
                
                foreach ($batchKeys as $listKey) {
                    $pipeline->del($listKey);
                    $pipeline->hDel($indexKey, $listKey);
                }
                
                $pipeline->execute();
                
                // 如果批次很多，可以适当暂停一下，减少对Redis的压力
                if ($batchCount > 5 && $i < $batchCount - 1) {
                    usleep(50000); // 50ms
                }
            }
            
            // 清理搜索和分类筛选缓存
            $searchKeys = $redis->keys(self::CACHE_PREFIX . "search:*");
            $categoryKeys = $redis->keys(self::CACHE_PREFIX . "category:*");
            
            if (!empty($searchKeys)) {
                $redis->del($searchKeys);
            }
            
            if (!empty($categoryKeys)) {
                $redis->del($categoryKeys);
            }
            
            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000, 2); // 毫秒
            
            Log::info("ClearGoodsListCacheJob: 清理了 {$totalKeys} 个商品列表缓存，耗时: {$duration}ms");
            $job->delete();
        } catch (\Exception $e) {
            Log::error("ClearGoodsListCacheJob: 缓存清理失败: " . $e->getMessage());
            
            // 如果尝试次数超过3次，则删除任务
            if ($job->attempts() > 3) {
                Log::error("ClearGoodsListCacheJob: 任务失败次数过多，已删除");
                $job->delete();
            } else {
                // 否则，延迟30秒后重试
                $job->release(30);
            }
        }
    }
} 