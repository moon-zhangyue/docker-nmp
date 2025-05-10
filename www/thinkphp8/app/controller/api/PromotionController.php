<?php
declare(strict_types=1);

namespace app\controller\api;

use app\BaseController;
use app\logic\GoodsLogic;
use think\facade\Log;
use think\facade\Request;
use think\Response;
use think\exception\ValidateException;

class PromotionController extends BaseController
{
    /**
     * 商品逻辑类实例
     * 
     * @var GoodsLogic
     */
    protected GoodsLogic $goodsLogic;
    
    /**
     * 控制器构造函数
     */
    public function __construct()
    {
        $this->goodsLogic = new GoodsLogic();
    }
    
    /**
     * 获取商品促销价格
     * 
     * @param int $id SKU ID
     * @return Response
     */
    public function price(int $id): Response
    {
        try {
            $goods = $this->goodsLogic->getGoodsDetail($id);
            
            if (!$goods) {
                return json([
                    'code' => 404,
                    'msg' => '商品不存在'
                ]);
            }
            
            // 假设第一个SKU即为请求的SKU
            $skuId = $goods['skus'][0]['id'] ?? 0;
            
            if (!$skuId) {
                return json([
                    'code' => 404,
                    'msg' => '商品SKU不存在'
                ]);
            }
            
            // 通过Logic层获取促销价格信息
            $promotionInfo = [];
            foreach ($goods['skus'] as $sku) {
                if (isset($sku['promotion_info'])) {
                    $promotionInfo[$sku['id']] = $sku['promotion_info'];
                }
            }
            
            return json([
                'code' => 200,
                'msg' => '获取成功',
                'data' => $promotionInfo
            ]);
        } catch (\Exception $e) {
            Log::error('获取商品促销价格失败: ' . $e->getMessage());
            
            return json([
                'code' => 500,
                'msg' => '获取商品促销价格失败: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * 创建秒杀活动
     * 
     * @return Response
     */
    public function createSeckill(): Response
    {
        try {
            $data = Request::post();
            
            // 验证数据
            if (empty($data['sku_id']) || !is_numeric($data['sku_id'])) {
                return json([
                    'code' => 400,
                    'msg' => 'SKU ID不能为空'
                ]);
            }
            
            if (empty($data['start_time']) || !is_numeric($data['start_time'])) {
                return json([
                    'code' => 400,
                    'msg' => '开始时间不能为空'
                ]);
            }
            
            if (empty($data['end_time']) || !is_numeric($data['end_time'])) {
                return json([
                    'code' => 400,
                    'msg' => '结束时间不能为空'
                ]);
            }
            
            if (empty($data['seckill_price']) || !is_numeric($data['seckill_price']) || $data['seckill_price'] <= 0) {
                return json([
                    'code' => 400,
                    'msg' => '秒杀价格必须大于0'
                ]);
            }
            
            if (empty($data['total_stock']) || !is_numeric($data['total_stock']) || $data['total_stock'] <= 0) {
                return json([
                    'code' => 400,
                    'msg' => '总库存必须大于0'
                ]);
            }
            
            // 创建秒杀活动
            $result = $this->goodsLogic->createSeckill($data);
            
            return json([
                'code' => 201,
                'msg' => '创建秒杀活动成功',
                'data' => [
                    'success' => $result
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('创建秒杀活动失败: ' . $e->getMessage());
            
            return json([
                'code' => 500,
                'msg' => '创建秒杀活动失败: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * 参与秒杀活动
     * 
     * @return Response
     */
    public function joinSeckill(): Response
    {
        try {
            $data = Request::post();
            
            // 验证数据
            if (empty($data['sku_id']) || !is_numeric($data['sku_id'])) {
                return json([
                    'code' => 400,
                    'msg' => 'SKU ID不能为空'
                ]);
            }
            
            if (empty($data['user_id']) || !is_numeric($data['user_id'])) {
                return json([
                    'code' => 400,
                    'msg' => '用户ID不能为空'
                ]);
            }
            
            $quantity = isset($data['quantity']) && is_numeric($data['quantity']) ? (int)$data['quantity'] : 1;
            
            // 参与秒杀活动
            $result = $this->goodsLogic->joinSeckill((int)$data['sku_id'], (int)$data['user_id'], $quantity);
            
            return json([
                'code' => 200,
                'msg' => '抢购成功',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('参与秒杀活动失败: ' . $e->getMessage());
            
            return json([
                'code' => 500,
                'msg' => $e->getMessage()
            ]);
        }
    }
} 