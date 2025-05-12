<?php
declare(strict_types=1);

namespace app\job;

use think\queue\Job;
use think\facade\Cache;
use think\facade\Log;
use app\model\GoodsSpu;

/**
 * 商品缓存清理队列任务
 */
class ClearGoodsCacheJob
{
    /**
     * 缓存前缀
     */
    private const CACHE_PREFIX = 'goods:';

    /**
     * 执行队列任务
     *
     * @param Job $job 队列任务实例
     * @param array $data 任务数据
     */
    public function fire(Job $job, array $data): void
    {
        try {
            $spuId = $data['spu_id'] ?? 0;
            $skuIds = $data['sku_ids'] ?? [];
            $onlySku = $data['only_sku'] ?? false;

            if (!$spuId && empty($skuIds)) {
                Log::error('ClearGoodsCacheJob: 缺少商品ID或SKU ID');
                $job->delete();
                return;
            }

            $startTime = microtime(true);
            $redis = Cache::store('redis')->handler();
            $pipeline = $redis->pipeline();
            $clearedKeys = 0;

            // 清理SKU缓存
            if (!empty($skuIds)) {
                foreach ($skuIds as $skuId) {
                    // 清理SKU详情缓存
                    $skuCacheKey = self::CACHE_PREFIX . "sku:{$skuId}";
                    $pipeline->del($skuCacheKey);
                    $clearedKeys++;

                    // 清理SKU库存缓存
                    $stockCacheKey = self::CACHE_PREFIX . "stock:{$skuId}";
                    $pipeline->del($stockCacheKey);
                    $clearedKeys++;

                    // 清理锁定库存缓存
                    $lockedStockCacheKey = self::CACHE_PREFIX . "locked_stock:{$skuId}";
                    $pipeline->del($lockedStockCacheKey);
                    $clearedKeys++;
                }
            }

            // 如果只清理SKU缓存，则不需要清理商品详情缓存
            if (!$onlySku && $spuId > 0) {
                // 清理商品详情缓存
                $detailCacheKey = self::CACHE_PREFIX . "detail:{$spuId}";
                $pipeline->del($detailCacheKey);
                $clearedKeys++;

                // 查找并清理与该商品关联的列表缓存
                $goodsListsKey = self::CACHE_PREFIX . "goods_lists:{$spuId}";
                $relatedLists = $redis->sMembers($goodsListsKey);

                if (!empty($relatedLists)) {
                    // 删除相关列表缓存
                    foreach ($relatedLists as $listKey) {
                        $pipeline->del($listKey);
                        $clearedKeys++;
                    }

                    // 清理映射关系
                    $indexKey = self::CACHE_PREFIX . "list_index";
                    foreach ($relatedLists as $listKey) {
                        $pipeline->hDel($indexKey, $listKey);
                        $clearedKeys++;
                    }
                }

                // 清理映射关系
                $pipeline->del($goodsListsKey);
                $clearedKeys++;

                // 清理搜索缓存
                $pipeline->del(self::CACHE_PREFIX . "search:{$spuId}");
                $clearedKeys++;
            }

            $pipeline->exec();
            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000, 2); // 毫秒

            Log::info("ClearGoodsCacheJob: 商品缓存清理成功，SPU ID: {$spuId}，清理了 {$clearedKeys} 个缓存键，耗时: {$duration}ms");
            $job->delete();
        } catch (\Exception $e) {
            Log::error("ClearGoodsCacheJob: 缓存清理失败: " . $e->getMessage());

            // 如果尝试次数超过3次，则删除任务
            if ($job->attempts() > 3) {
                Log::error("ClearGoodsCacheJob: 任务失败次数过多，已删除");
                $job->delete();
            } else {
                // 否则，延迟30秒后重试
                $job->release(30);
            }
        }
    }
}