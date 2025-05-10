<?php
declare(strict_types=1);

namespace app\logic;

use app\service\GoodsService;
use app\service\PromotionService;
use think\facade\Cache;
use think\facade\Log;
use think\Exception;

class GoodsLogic
{
    protected GoodsService $goodsService;
    protected PromotionService $promotionService;
    
    public function __construct()
    {
        $this->goodsService = new GoodsService();
        $this->promotionService = new PromotionService();
    }
    
    /**
     * 获取商品列表，带促销信息
     * 
     * @param array $params 查询参数
     * @return array
     */
    public function getGoodsList(array $params): array
    {
        // 获取基础商品列表
        $result = $this->goodsService->getList($params);
        
        // 如果需要获取促销信息
        if (isset($params['with_promotion']) && $params['with_promotion']) {
            foreach ($result['list'] as &$goods) {
                foreach ($goods['skus'] as &$sku) {
                    try {
                        // 获取促销信息
                        $promotionInfo = $this->promotionService->getPromotionPrice($sku['id']);
                        $sku['promotion_info'] = $promotionInfo;
                    } catch (\Exception $e) {
                        // 记录错误但不中断流程
                        Log::error("获取商品 {$sku['id']} 促销信息失败: " . $e->getMessage());
                        $sku['promotion_info'] = [
                            'original_price' => $sku['price'],
                            'promotion_price' => $sku['price'],
                            'promotion_type' => 'regular',
                            'discount_amount' => 0,
                            'discount_percent' => 0
                        ];
                    }
                }
            }
        }
        
        return $result;
    }
    
    /**
     * 获取商品详情，带促销信息
     * 
     * @param int $id 商品ID
     * @return array|null
     */
    public function getGoodsDetail(int $id): ?array
    {
        // 获取基础商品详情
        $goods = $this->goodsService->getDetail($id);
        
        if (!$goods) {
            return null;
        }
        
        // 为每个SKU添加促销信息
        foreach ($goods['skus'] as &$sku) {
            try {
                // 获取促销信息
                $promotionInfo = $this->promotionService->getPromotionPrice($sku['id']);
                $sku['promotion_info'] = $promotionInfo;
            } catch (\Exception $e) {
                // 记录错误但不中断流程
                Log::error("获取商品 {$sku['id']} 促销信息失败: " . $e->getMessage());
                $sku['promotion_info'] = [
                    'original_price' => $sku['price'],
                    'promotion_price' => $sku['price'],
                    'promotion_type' => 'regular',
                    'discount_amount' => 0,
                    'discount_percent' => 0
                ];
            }
        }
        
        return $goods;
    }
    
    /**
     * 创建商品
     * 
     * @param array $data 商品数据
     * @return int 新商品ID
     */
    public function createGoods(array $data): int
    {
        // 验证商品数据
        $this->validateGoodsData($data);
        
        // 创建商品
        return $this->goodsService->create($data);
    }
    
    /**
     * 更新商品
     * 
     * @param int $id 商品ID
     * @param array $data 商品数据
     * @return bool
     */
    public function updateGoods(int $id, array $data): bool
    {
        // 验证商品数据
        $this->validateGoodsData($data, false);
        
        // 更新商品
        return $this->goodsService->update($id, $data);
    }
    
    /**
     * 删除商品
     * 
     * @param int $id 商品ID
     * @return bool
     */
    public function deleteGoods(int $id): bool
    {
        return $this->goodsService->delete($id);
    }
    
    /**
     * 验证商品数据
     * 
     * @param array $data 商品数据
     * @param bool $isCreate 是否是创建操作
     * @throws \Exception
     */
    protected function validateGoodsData(array $data, bool $isCreate = true): void
    {
        // 创建时必须包含名称
        if ($isCreate && empty($data['name'])) {
            throw new Exception("商品名称不能为空");
        }
        
        // 创建时必须包含至少一个SKU
        if ($isCreate && (empty($data['skus']) || !is_array($data['skus']) || count($data['skus']) === 0)) {
            throw new Exception("至少需要一个SKU");
        }
        
        // 验证SKU数据
        if (!empty($data['skus']) && is_array($data['skus'])) {
            foreach ($data['skus'] as $sku) {
                if (!isset($sku['price']) || !is_numeric($sku['price']) || $sku['price'] < 0) {
                    throw new Exception("SKU价格必须是大于等于0的数字");
                }
                
                if (!isset($sku['stock']) || !is_numeric($sku['stock']) || $sku['stock'] < 0) {
                    throw new Exception("SKU库存必须是大于等于0的整数");
                }
            }
        }
    }
    
    /**
     * 创建秒杀活动
     * 
     * @param array $data 秒杀活动数据
     * @return bool
     */
    public function createSeckill(array $data): bool
    {
        // 验证秒杀数据
        $this->validateSeckillData($data);
        
        // 创建秒杀活动
        return $this->promotionService->createSeckill($data);
    }
    
    /**
     * 参与秒杀活动
     * 
     * @param int $skuId 商品SKU ID
     * @param int $userId 用户ID
     * @param int $quantity 购买数量
     * @return array 抢购结果
     */
    public function joinSeckill(int $skuId, int $userId, int $quantity = 1): array
    {
        // 参与秒杀活动
        return $this->promotionService->joinSeckill($skuId, $userId, $quantity);
    }
    
    /**
     * 验证秒杀活动数据
     * 
     * @param array $data 秒杀活动数据
     * @throws \Exception
     */
    protected function validateSeckillData(array $data): void
    {
        if (empty($data['sku_id']) || !is_numeric($data['sku_id'])) {
            throw new Exception("SKU ID不能为空");
        }
        
        if (empty($data['start_time']) || !is_numeric($data['start_time'])) {
            throw new Exception("开始时间不能为空");
        }
        
        if (empty($data['end_time']) || !is_numeric($data['end_time'])) {
            throw new Exception("结束时间不能为空");
        }
        
        if ($data['start_time'] >= $data['end_time']) {
            throw new Exception("开始时间必须早于结束时间");
        }
        
        if (empty($data['seckill_price']) || !is_numeric($data['seckill_price']) || $data['seckill_price'] <= 0) {
            throw new Exception("秒杀价格必须大于0");
        }
        
        if (empty($data['total_stock']) || !is_numeric($data['total_stock']) || $data['total_stock'] <= 0) {
            throw new Exception("总库存必须大于0");
        }
    }
} 