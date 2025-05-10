<?php
declare(strict_types=1);

namespace app\job;

use think\queue\Job;
use think\facade\Cache;
use think\facade\Log;
use think\facade\Queue;
use app\model\GoodsSpu;
use app\service\GoodsService;

/**
 * 商品缓存预热队列任务
 */
class PreloadGoodsCacheJob
{
    /**
     * 缓存前缀
     */
    private const CACHE_PREFIX = 'goods:';
    
    /**
     * 缓存时间（秒）
     */
    private const CACHE_TIME = 3600; // 1小时
    
    /**
     * 批处理大小
     */
    private const BATCH_SIZE = 50;

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
            
            $spuId = $data['spu_id'] ?? 0;
            $ids = $data['ids'] ?? [];
            $isHotGoods = $data['is_hot_goods'] ?? false;
            
            // 如果提供了spuId，则添加到ids列表中
            if ($spuId && !in_array($spuId, $ids)) {
                $ids[] = $spuId;
            }
            
            if (empty($ids) && !$isHotGoods) {
                Log::error('PreloadGoodsCacheJob: 缺少商品ID');
                $job->delete();
                return;
            }
            
            // 如果是预热热门商品
            if ($isHotGoods) {
                $ids = $this->getHotGoodsIds();
                if (empty($ids)) {
                    Log::warning("PreloadGoodsCacheJob: 未找到热门商品");
                    $job->delete();
                    return;
                }
            }
            
            // 按批次处理，避免一次性处理太多商品
            $batchCount = ceil(count($ids) / self::BATCH_SIZE);
            $goodsService = new GoodsService();
            $totalPreloaded = 0;
            
            for ($i = 0; $i < $batchCount; $i++) {
                $start = $i * self::BATCH_SIZE;
                $batchIds = array_slice($ids, $start, self::BATCH_SIZE);
                
                // 使用商品服务预加载缓存
                $result = $goodsService->preloadGoodsCache($batchIds);
                
                if ($result) {
                    $totalPreloaded += count($batchIds);
                }
                
                // 如果批次很多，可以适当暂停一下，减少对数据库和Redis的压力
                if ($batchCount > 3 && $i < $batchCount - 1) {
                    usleep(100000); // 100ms
                }
            }
            
            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000, 2); // 毫秒
            
            if ($totalPreloaded > 0) {
                Log::info("PreloadGoodsCacheJob: 商品缓存预热成功，共 {$totalPreloaded} 个商品，耗时: {$duration}ms");
            } else {
                Log::warning("PreloadGoodsCacheJob: 商品缓存预热未执行，可能没有找到商品，耗时: {$duration}ms");
            }
            
            $job->delete();
        } catch (\Exception $e) {
            Log::error("PreloadGoodsCacheJob: 缓存预热失败: " . $e->getMessage());
            
            // 如果尝试次数超过3次，则删除任务
            if ($job->attempts() > 3) {
                Log::error("PreloadGoodsCacheJob: 任务失败次数过多，已删除");
                $job->delete();
            } else {
                // 否则，延迟30秒后重试
                $job->release(30);
            }
        }
    }
    
    /**
     * 获取热门商品ID列表
     * 
     * @param int $limit 数量限制
     * @return array
     */
    private function getHotGoodsIds(int $limit = 100): array
    {
        // 优先从缓存获取热门商品ID
        $cacheKey = self::CACHE_PREFIX . 'hot_goods_ids';
        $hotGoodsIds = Cache::store('redis')->get($cacheKey);
        
        if (!empty($hotGoodsIds)) {
            return $hotGoodsIds;
        }
        
        // 查询热门商品ID（可以根据实际业务逻辑调整查询条件）
        try {
            // 策略1: 按照访问量降序
            $hotByViews = GoodsSpu::where('status', 1)
                ->order('view_count', 'desc')
                ->limit($limit / 2)
                ->column('id');
                
            // 策略2: 按照销量降序
            $hotBySales = GoodsSpu::where('status', 1)
                ->order('sales', 'desc')
                ->limit($limit / 2)
                ->column('id');
                
            // 合并并去重
            $hotGoodsIds = array_values(array_unique(array_merge($hotByViews, $hotBySales)));
            
            // 限制数量
            if (count($hotGoodsIds) > $limit) {
                $hotGoodsIds = array_slice($hotGoodsIds, 0, $limit);
            }
            
            // 缓存结果，有效期12小时
            if (!empty($hotGoodsIds)) {
                Cache::store('redis')->set($cacheKey, $hotGoodsIds, 12 * 3600);
            }
            
            return $hotGoodsIds;
        } catch (\Exception $e) {
            Log::error("PreloadGoodsCacheJob.getHotGoodsIds 异常: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 创建预热热门商品的任务
     * 
     * @return void
     */
    public static function preloadHotGoodsCache(): void
    {
        try {
            // 创建预热任务
            Queue::push('app\job\PreloadGoodsCacheJob', [
                'is_hot_goods' => true
            ]);
            
            Log::info("PreloadGoodsCacheJob: 已创建热门商品预热任务");
        } catch (\Exception $e) {
            Log::error("PreloadGoodsCacheJob: 创建热门商品预热任务失败: " . $e->getMessage());
        }
    }
} 