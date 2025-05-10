<?php
declare(strict_types=1);

namespace app\job;

use think\queue\Job;
use think\facade\Cache;
use think\facade\Log;
use app\model\GoodsSku;

/**
 * 秒杀库存预热队列任务
 */
class SeckillStockWarmupJob
{
    /**
     * 执行队列任务
     * 
     * @param Job $job 队列任务实例
     * @param array $data 任务数据
     */
    public function fire(Job $job, array $data): void
    {
        try {
            $skuId = $data['sku_id'] ?? 0;
            $totalStock = $data['total_stock'] ?? 0;
            
            if (!$skuId || !$totalStock) {
                Log::error('SeckillStockWarmupJob: 缺少商品ID或库存数量');
                $job->delete();
                return;
            }
            
            // 获取SKU信息
            $sku = GoodsSku::find($skuId);
            if (!$sku) {
                Log::error("SeckillStockWarmupJob: 商品SKU {$skuId} 不存在");
                $job->delete();
                return;
            }
            
            // 将库存加载到Redis，预热秒杀库存
            $stockKey = "seckill:stock:{$skuId}";
            
            // 清除旧的库存队列（如果存在）
            Cache::store('redis')->delete($stockKey);
            
            // 批量导入库存
            $pipeline = Cache::store('redis')->handler()->pipeline();
            for ($i = 1; $i <= $totalStock; $i++) {
                $pipeline->rpush($stockKey, 1);
            }
            $pipeline->execute();
            
            // 获取秒杀信息，更新过期时间
            $seckillKey = "seckill:goods:{$skuId}";
            $seckillInfo = Cache::store('redis')->hGetAll($seckillKey);
            
            if (!empty($seckillInfo) && isset($seckillInfo['end_time'])) {
                // 设置库存队列的过期时间为活动结束后1小时
                $endTime = (int)$seckillInfo['end_time'];
                Cache::store('redis')->expireAt($stockKey, $endTime + 3600);
            } else {
                // 默认24小时过期
                Cache::store('redis')->expire($stockKey, 86400);
            }
            
            Log::info("SeckillStockWarmupJob: 商品 {$skuId} 库存预热成功，共 {$totalStock} 件");
            $job->delete();
        } catch (\Exception $e) {
            Log::error("SeckillStockWarmupJob: 库存预热失败: " . $e->getMessage());
            
            // 如果尝试次数超过3次，则删除任务
            if ($job->attempts() > 3) {
                Log::error("SeckillStockWarmupJob: 任务失败次数过多，已删除");
                $job->delete();
            } else {
                // 否则，延迟60秒后重试
                $job->release(60);
            }
        }
    }
} 