<?php
declare(strict_types=1);

namespace app\controller\api;

use app\BaseController;
use app\logic\GoodsLogic;
use app\model\SeckillActivity;
use app\model\SeckillGoods;
use app\model\SeckillOrder;
use app\service\PromotionService;
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
     * 促销服务类实例
     *
     * @var PromotionService
     */
    protected PromotionService $promotionService;

    /**
     * 控制器构造函数
     */
    public function __construct()
    {
        $this->goodsLogic       = new GoodsLogic();
        $this->promotionService = new PromotionService();
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
                    'msg'  => '商品不存在'
                ]);
            }

            // 假设第一个SKU即为请求的SKU
            $skuId = $goods['skus'][0]['id'] ?? 0;

            if (!$skuId) {
                return json([
                    'code' => 404,
                    'msg'  => '商品SKU不存在'
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
                'msg'  => '获取成功',
                'data' => $promotionInfo
            ]);
        } catch (\Exception $e) {
            Log::error('获取商品促销价格失败: ' . $e->getMessage());

            return json([
                'code' => 500,
                'msg'  => '获取商品促销价格失败: ' . $e->getMessage()
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

            // 验证基本数据
            if (empty($data['sku_id']) || !is_numeric($data['sku_id'])) {
                return json([
                    'code' => 400,
                    'msg'  => 'SKU ID不能为空'
                ]);
            }

            if (empty($data['start_time']) || !is_numeric(strtotime($data['start_time']))) {
                return json([
                    'code' => 400,
                    'msg'  => '开始时间不能为空'
                ]);
            }

            $data['start_time'] = strtotime($data['start_time']);

            if (empty($data['end_time']) || !is_numeric(strtotime($data['end_time']))) {
                return json([
                    'code' => 400,
                    'msg'  => '结束时间不能为空'
                ]);
            }

            $data['end_time'] = strtotime($data['end_time']);

            // 验证秒杀数据
            if (empty($data['seckill_price']) || !is_numeric($data['seckill_price']) || $data['seckill_price'] <= 0) {
                return json([
                    'code' => 400,
                    'msg'  => '秒杀价格必须大于0'
                ]);
            }

            if (empty($data['total_stock']) || !is_numeric($data['total_stock']) || $data['total_stock'] <= 0) {
                return json([
                    'code' => 400,
                    'msg'  => '总库存必须大于0'
                ]);
            }

            // 验证活动标题
            if (empty($data['title'])) {
                $data['title'] = '秒杀活动'; // 设置默认标题
            }

            // 设置默认值
            $data['max_buy_limit']  = $data['max_buy_limit'] ?? 1;
            $data['limit_per_user'] = $data['limit_per_user'] ?? 1;

            // 创建秒杀活动
            $result = $this->promotionService->createSeckill($data);

            // 获取创建的活动和商品信息
            $activityId = $this->promotionService->getLastActivityId();
            $activity   = null;
            $goods      = null;

            if ($activityId) {
                $activity = SeckillActivity::find($activityId);
                $goods    = SeckillGoods::where('activity_id', $activityId)->find();
            }

            return json([
                'code' => 201,
                'msg'  => '创建秒杀活动成功',
                'data' => [
                    'success'     => $result,
                    'activity_id' => $activityId,
                    'activity'    => $activity ? $activity->toArray() : null,
                    'goods'       => $goods ? $goods->toArray() : null
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('创建秒杀活动失败: ' . $e->getMessage());

            return json([
                'code' => 500,
                'msg'  => '创建秒杀活动失败: ' . $e->getMessage()
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
                    'msg'  => 'SKU ID不能为空'
                ]);
            }

            if (empty($data['user_id']) || !is_numeric($data['user_id'])) {
                return json([
                    'code' => 400,
                    'msg'  => '用户ID不能为空'
                ]);
            }

            $quantity = isset($data['quantity']) && is_numeric($data['quantity']) ? (int) $data['quantity'] : 1;

            // 参与秒杀活动
            $result = $this->promotionService->joinSeckill((int) $data['sku_id'], (int) $data['user_id'], $quantity);

            // 查询订单信息
            $orderSn = $result['order_sn'] ?? '';
            $order   = null;

            if ($orderSn) {
                $order = SeckillOrder::where('order_sn', $orderSn)->find();
            }

            return json([
                'code' => 200,
                'msg'  => '抢购成功',
                'data' => [
                    'order_sn' => $orderSn,
                    'message'  => $result['message'] ?? '抢购成功，订单正在处理中',
                    'order'    => $order?->toArray()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('参与秒杀活动失败: ' . $e->getMessage());

            return json([
                'code' => 500,
                'msg'  => $e->getMessage()
            ]);
        }
    }

    /**
     * 获取秒杀活动列表
     *
     * @return Response
     */
    public function getSeckillList(): Response
    {
        try {
            // 获取当前进行中的活动
            $currentActivities = SeckillActivity::getCurrentActivities();

            // 获取即将开始的活动
            $upcomingActivities = SeckillActivity::getUpcomingActivities();

            // 获取活动商品信息
            $result = [
                'current'  => [],
                'upcoming' => []
            ];

            // 处理当前活动
            foreach ($currentActivities as $activity) {
                $activityData          = $activity->toArray();
                $activityData['goods'] = [];

                // 获取活动商品
                $goods = SeckillGoods::where('activity_id', $activity->id)
                    ->where('status', SeckillGoods::STATUS_ONLINE)
                    ->order('sort_order', 'desc')
                    ->select();

                foreach ($goods as $item) {
                    $goodsData                  = $item->toArray();
                    $goodsData['discount_rate'] = $item->getDiscountRate();
                    $goodsData['saved_amount']  = $item->getSavedAmount();
                    $activityData['goods'][]    = $goodsData;
                }

                $result['current'][] = $activityData;
            }

            // 处理即将开始的活动
            foreach ($upcomingActivities as $activity) {
                $activityData          = $activity->toArray();
                $activityData['goods'] = [];

                // 获取活动商品
                $goods = SeckillGoods::where('activity_id', $activity->id)
                    ->where('status', SeckillGoods::STATUS_ONLINE)
                    ->order('sort_order', 'desc')
                    ->select();

                foreach ($goods as $item) {
                    $goodsData                  = $item->toArray();
                    $goodsData['discount_rate'] = $item->getDiscountRate();
                    $goodsData['saved_amount']  = $item->getSavedAmount();
                    $activityData['goods'][]    = $goodsData;
                }

                $result['upcoming'][] = $activityData;
            }

            return json([
                'code' => 200,
                'msg'  => '获取成功',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('获取秒杀活动列表失败: ' . $e->getMessage());

            return json([
                'code' => 500,
                'msg'  => '获取秒杀活动列表失败: ' . $e->getMessage()
            ]);
        }
    }
}