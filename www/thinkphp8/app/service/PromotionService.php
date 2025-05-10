<?php
declare(strict_types=1);

namespace app\service;

use app\model\GoodsSku;
use think\facade\Cache;
use think\facade\Queue;
use think\facade\Db;
use think\facade\Log;
use think\Exception;
use think\facade\Redis;

class PromotionService
{
    /**
     * 获取商品促销价格
     * 
     * @param int $skuId SKU ID
     * @return array 包含原价和促销价的数组
     */
    public function getPromotionPrice(int $skuId): array
    {
        // 获取SKU信息
        $sku = GoodsSku::find($skuId);
        if (!$sku) {
            throw new Exception("商品不存在");
        }
        
        $originalPrice = $sku->price;
        $promotionPrice = $originalPrice;
        $promotionType = 'regular'; // 默认常规销售
        
        // 先从缓存获取促销信息
        $cacheKey = "promotion_price:{$skuId}";
        $promotionInfo = Cache::get($cacheKey);
        
        if ($promotionInfo) {
            return $promotionInfo;
        }
        
        // 查询各种促销活动并计算最优惠的价格
        // 1. 常规折扣促销
        $regularPromotion = $this->getRegularPromotion($skuId);
        if ($regularPromotion && $regularPromotion['promotion_price'] < $promotionPrice) {
            $promotionPrice = $regularPromotion['promotion_price'];
            $promotionType = 'regular_discount';
        }
        
        // 2. 限时促销
        $flashSalePromotion = $this->getFlashSalePromotion($skuId);
        if ($flashSalePromotion && $flashSalePromotion['promotion_price'] < $promotionPrice) {
            $promotionPrice = $flashSalePromotion['promotion_price'];
            $promotionType = 'flash_sale';
        }
        
        // 3. 秒杀活动
        $seckillPromotion = $this->getSeckillPromotion($skuId);
        if ($seckillPromotion && $seckillPromotion['promotion_price'] < $promotionPrice) {
            $promotionPrice = $seckillPromotion['promotion_price'];
            $promotionType = 'seckill';
        }
        
        $result = [
            'original_price' => $originalPrice,
            'promotion_price' => $promotionPrice,
            'promotion_type' => $promotionType,
            'discount_amount' => ($originalPrice - $promotionPrice),
            'discount_percent' => round(($originalPrice - $promotionPrice) / $originalPrice * 100, 2)
        ];
        
        // 设置缓存，有效期30分钟
        Cache::set($cacheKey, $result, 1800);
        
        return $result;
    }
    
    /**
     * 获取常规促销折扣信息
     * 
     * @param int $skuId SKU ID
     * @return array|null 促销信息
     */
    private function getRegularPromotion(int $skuId): ?array
    {
        // 通常会从数据库中获取商品促销信息，这里简化为固定折扣
        // 模拟从数据库中查询常规促销信息
        $sku = GoodsSku::find($skuId);
        if (!$sku) {
            return null;
        }
        
        // 模拟一个固定折扣（实际应用中应从促销规则表查询）
        $discountRate = 0.9; // 9折
        $promotionPrice = round($sku->price * $discountRate, 2);
        
        return [
            'sku_id' => $skuId,
            'promotion_price' => $promotionPrice,
            'promotion_type' => 'regular_discount',
            'discount_rate' => $discountRate,
            'start_time' => null,
            'end_time' => null
        ];
    }
    
    /**
     * 获取限时促销信息
     * 
     * @param int $skuId SKU ID
     * @return array|null 促销信息
     */
    private function getFlashSalePromotion(int $skuId): ?array
    {
        // 模拟从数据库中查询限时促销信息
        $sku = GoodsSku::find($skuId);
        if (!$sku) {
            return null;
        }
        
        // 获取当前时间
        $now = time();
        
        // 模拟限时促销规则（实际应从促销规则表查询）
        // 例如：每天18:00-22:00进行限时促销
        $todayStart = strtotime(date('Y-m-d 18:00:00'));
        $todayEnd = strtotime(date('Y-m-d 22:00:00'));
        
        if ($now >= $todayStart && $now <= $todayEnd) {
            $discountRate = 0.8; // 8折
            $promotionPrice = round($sku->price * $discountRate, 2);
            
            return [
                'sku_id' => $skuId,
                'promotion_price' => $promotionPrice,
                'promotion_type' => 'flash_sale',
                'discount_rate' => $discountRate,
                'start_time' => $todayStart,
                'end_time' => $todayEnd
            ];
        }
        
        return null;
    }
    
    /**
     * 获取秒杀促销信息
     * 
     * @param int $skuId SKU ID
     * @return array|null 促销信息
     */
    private function getSeckillPromotion(int $skuId): ?array
    {
        // 从Redis缓存中获取秒杀商品信息
        $seckillKey = "seckill:goods:{$skuId}";
        $seckillInfo = Cache::store('redis')->hGetAll($seckillKey);
        
        if (empty($seckillInfo)) {
            return null;
        }
        
        $now = time();
        if (isset($seckillInfo['start_time']) && isset($seckillInfo['end_time'])) {
            $startTime = (int)$seckillInfo['start_time'];
            $endTime = (int)$seckillInfo['end_time'];
            
            if ($now >= $startTime && $now <= $endTime) {
                return [
                    'sku_id' => $skuId,
                    'promotion_price' => (float)$seckillInfo['seckill_price'],
                    'promotion_type' => 'seckill',
                    'discount_rate' => (float)$seckillInfo['seckill_price'] / (float)$seckillInfo['original_price'],
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'total_stock' => (int)$seckillInfo['total_stock'],
                    'remain_stock' => (int)$seckillInfo['remain_stock']
                ];
            }
        }
        
        return null;
    }
    
    /**
     * 创建秒杀活动
     * 
     * @param array $data 秒杀活动数据
     * @return bool
     * @throws \Exception
     */
    public function createSeckill(array $data): bool
    {
        try {
            $skuId = (int)$data['sku_id'];
            $startTime = (int)$data['start_time'];
            $endTime = (int)$data['end_time'];
            $seckillPrice = (float)$data['seckill_price'];
            $totalStock = (int)$data['total_stock'];
            
            // 获取SKU信息
            $sku = GoodsSku::find($skuId);
            if (!$sku) {
                throw new Exception("商品不存在");
            }
            
            // 验证库存是否足够
            if ($sku->stock < $totalStock) {
                throw new Exception("库存不足，无法设置秒杀活动");
            }
            
            // 验证秒杀价是否低于原价
            if ($seckillPrice >= $sku->price) {
                throw new Exception("秒杀价格必须低于原价");
            }
            
            // 设置秒杀活动信息到Redis
            $seckillKey = "seckill:goods:{$skuId}";
            $seckillData = [
                'sku_id' => $skuId,
                'spu_id' => $sku->spu_id,
                'seckill_price' => $seckillPrice,
                'original_price' => $sku->price,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'total_stock' => $totalStock,
                'remain_stock' => $totalStock,
                'status' => 1
            ];
            
            Cache::store('redis')->hMSet($seckillKey, $seckillData);
            
            // 设置秒杀活动过期时间
            Cache::store('redis')->expireAt($seckillKey, $endTime + 3600); // 活动结束后1小时过期
            
            // 清除促销价格缓存
            Cache::delete("promotion_price:{$skuId}");
            
            // 预热秒杀库存到队列
            Queue::push('app\job\SeckillStockWarmupJob', [
                'sku_id' => $skuId,
                'total_stock' => $totalStock
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error("创建秒杀活动失败: " . $e->getMessage());
            throw new Exception("创建秒杀活动失败: " . $e->getMessage());
        }
    }
    
    /**
     * 参与秒杀活动
     * 
     * @param int $skuId 商品SKU ID
     * @param int $userId 用户ID
     * @param int $quantity 购买数量
     * @return array 抢购结果
     * @throws \Exception
     */
    public function joinSeckill(int $skuId, int $userId, int $quantity = 1): array
    {
        // 获取秒杀信息
        $seckillKey = "seckill:goods:{$skuId}";
        $seckillInfo = Cache::store('redis')->hGetAll($seckillKey);
        
        if (empty($seckillInfo)) {
            throw new Exception("该商品没有进行秒杀活动");
        }
        
        // 检查秒杀活动是否进行中
        $now = time();
        $startTime = (int)$seckillInfo['start_time'];
        $endTime = (int)$seckillInfo['end_time'];
        
        if ($now < $startTime) {
            throw new Exception("秒杀活动还未开始");
        }
        
        if ($now > $endTime) {
            throw new Exception("秒杀活动已结束");
        }
        
        // 检查是否已经购买过(一人限购一件)
        $userBoughtKey = "seckill:user:{$userId}:bought:{$skuId}";
        if (Cache::store('redis')->get($userBoughtKey)) {
            throw new Exception("您已经参与过此秒杀活动");
        }
        
        // Redis事务，减库存并创建订单队列
        $redis = Redis::instance();
        $redis->multi();
        
        // 检查并减少库存
        $redis->hIncrBy($seckillKey, 'remain_stock', -$quantity);
        
        // 执行Redis事务
        $result = $redis->exec();
        
        // 检查库存是否充足
        $remainStock = Cache::store('redis')->hGet($seckillKey, 'remain_stock');
        if ($remainStock < 0) {
            // 库存不足，回滚
            Cache::store('redis')->hIncrBy($seckillKey, 'remain_stock', $quantity);
            throw new Exception("商品已售罄");
        }
        
        // 标记用户已购买，有效期到活动结束
        $expireTime = $endTime - $now;
        Cache::store('redis')->set($userBoughtKey, 1, $expireTime);
        
        // 生成订单号
        $orderSn = date('YmdHis') . rand(1000, 9999) . $userId;
        
        // 将订单信息推送到队列中异步处理
        Queue::push('app\job\SeckillOrderJob', [
            'user_id' => $userId,
            'sku_id' => $skuId,
            'quantity' => $quantity,
            'price' => $seckillInfo['seckill_price'],
            'order_sn' => $orderSn,
            'seckill_key' => $seckillKey
        ]);
        
        return [
            'order_sn' => $orderSn,
            'message' => '抢购成功，订单正在处理中'
        ];
    }
} 