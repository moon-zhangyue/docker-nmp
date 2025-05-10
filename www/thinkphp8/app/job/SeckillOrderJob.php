<?php
declare(strict_types=1);

namespace app\job;

use think\queue\Job;
use think\facade\Cache;
use think\facade\Log;
use think\facade\Db;
use app\model\GoodsSku;

/**
 * 秒杀订单处理队列任务
 */
class SeckillOrderJob
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
            $userId = $data['user_id'] ?? 0;
            $skuId = $data['sku_id'] ?? 0;
            $quantity = $data['quantity'] ?? 1;
            $price = $data['price'] ?? 0;
            $orderSn = $data['order_sn'] ?? '';
            $seckillKey = $data['seckill_key'] ?? '';
            
            if (!$userId || !$skuId || !$price || !$orderSn) {
                Log::error('SeckillOrderJob: 缺少必要参数');
                $job->delete();
                return;
            }
            
            // 开启事务
            Db::startTrans();
            try {
                // 创建订单
                $orderId = $this->createOrder([
                    'user_id' => $userId,
                    'order_sn' => $orderSn,
                    'total_amount' => $price * $quantity,
                    'status' => 10, // 待支付
                    'order_type' => 'seckill', // 订单类型：秒杀
                    'create_time' => time()
                ]);
                
                // 创建订单商品
                $this->createOrderGoods([
                    'order_id' => $orderId,
                    'sku_id' => $skuId,
                    'quantity' => $quantity,
                    'price' => $price,
                    'total_price' => $price * $quantity
                ]);
                
                // 更新商品库存（这里不需要加锁，因为秒杀时已经在Redis中预扣减库存）
                $sku = GoodsSku::find($skuId);
                if ($sku) {
                    $sku->stock = $sku->stock - $quantity;
                    $sku->save();
                }
                
                Db::commit();
                
                // 记录订单创建成功
                Log::info("SeckillOrderJob: 用户 {$userId} 秒杀订单 {$orderSn} 创建成功");
                
                // 通知用户（实际场景中可能发送消息、短信等）
                // TODO: 调用通知服务
                
                // 删除任务
                $job->delete();
            } catch (\Exception $e) {
                // 事务回滚
                Db::rollback();
                
                // 回滚Redis中的库存（如果设置了seckillKey）
                if ($seckillKey) {
                    Cache::store('redis')->hIncrBy($seckillKey, 'remain_stock', $quantity);
                }
                
                Log::error("SeckillOrderJob: 创建订单失败: " . $e->getMessage());
                
                // 如果尝试次数超过3次，则放弃
                if ($job->attempts() > 3) {
                    Log::error("SeckillOrderJob: 创建订单 {$orderSn} 失败次数过多，已放弃");
                    
                    // 释放秒杀资格限制（允许用户重新参与秒杀）
                    $userBoughtKey = "seckill:user:{$userId}:bought:{$skuId}";
                    Cache::store('redis')->delete($userBoughtKey);
                    
                    $job->delete();
                } else {
                    // 否则，30秒后重试
                    $job->release(30);
                }
            }
        } catch (\Exception $e) {
            Log::error("SeckillOrderJob: 处理异常: " . $e->getMessage());
            
            if ($job->attempts() > 3) {
                $job->delete();
            } else {
                $job->release(60);
            }
        }
    }
    
    /**
     * 创建订单
     * 
     * @param array $data 订单数据
     * @return int 订单ID
     */
    protected function createOrder(array $data): int
    {
        // 实际应该调用订单服务创建订单
        // 这里简化为直接插入订单表
        return Db::name('order')->insertGetId($data);
    }
    
    /**
     * 创建订单商品
     * 
     * @param array $data 订单商品数据
     * @return int 订单商品ID
     */
    protected function createOrderGoods(array $data): int
    {
        // 实际应该调用订单服务创建订单商品
        // 这里简化为直接插入订单商品表
        return Db::name('order_goods')->insertGetId($data);
    }
} 