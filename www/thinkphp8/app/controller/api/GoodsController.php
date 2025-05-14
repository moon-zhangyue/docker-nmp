<?php
declare(strict_types=1);

namespace app\controller\api;

use app\BaseController;
use app\logic\GoodsLogic;
use app\validate\GoodsValidate;
use think\facade\Log;
use think\facade\Request;
use think\Response;
use think\exception\ValidateException;

class GoodsController extends BaseController
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
     * 获取商品列表
     *
     * @return Response
     */
    public function index(): Response
    {
        try {
            $params = Request::get();

            // 转换分页参数
            $params['page']  = isset($params['page']) ? (int) $params['page'] : 1;
            $params['limit'] = isset($params['limit']) ? (int) $params['limit'] : 10;

            // 转换布尔值-促銷
            $params['with_promotion'] = isset($params['with_promotion']) &&
                ($params['with_promotion'] === 'true' || $params['with_promotion'] === '1' || $params['with_promotion'] === true);

            $result = $this->goodsLogic->getGoodsList($params);

            return json([
                'code' => 200,
                'msg'  => '获取成功',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('获取商品列表失败: ' . $e->getMessage());

            return json([
                'code' => 500,
                'msg'  => '获取商品列表失败: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * 获取商品详情
     *
     * @param int $id 商品ID
     * @return Response
     */
    public function read(int $id): Response
    {
        try {
            $goods = $this->goodsLogic->getGoodsDetail($id);

            if (!$goods) {
                return json([
                    'code' => 404,
                    'msg'  => '商品不存在'
                ]);
            }

            return json([
                'code' => 200,
                'msg'  => '获取成功',
                'data' => $goods
            ]);
        } catch (\Exception $e) {
            Log::error('获取商品详情失败: ' . $e->getMessage());

            return json([
                'code' => 500,
                'msg'  => '获取商品详情失败: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * 创建商品
     *
     * @return Response
     */
    public function save(): Response
    {
        try {
            // 获取输入数据
            $data = Request::post();

            // 处理SKUs数组
            if (isset($data['skus'])) {
                $data['skus'] = $this->processSkusData($data['skus']);
            }

            // 验证数据
            try {
                validate(GoodsValidate::class)->check($data);
            } catch (ValidateException $e) {
                return json([
                    'code' => 400,
                    'msg'  => $e->getMessage()
                ]);
            }

            // 创建商品
            $goodsId = $this->goodsLogic->createGoods($data);

            return json([
                'code' => 201,
                'msg'  => '创建成功',
                'data' => [
                    'id' => $goodsId
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('创建商品失败: ' . $e->getMessage() . "\n" . $e->getTraceAsString());

            return json([
                'code' => 500,
                'msg'  => '创建商品失败: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * 更新商品
     *
     * @param int $id 商品ID
     * @return Response
     */
    public function update(): Response
    {
        try {
            $id = (int)Request::post('id');

            // 获取输入数据
            $data = Request::post();

            // 处理SKUs数组
            if (isset($data['skus'])) {
                $data['skus'] = $this->processSkusData($data['skus']);
            }

            // 验证数据
            try {
                validate(GoodsValidate::class)->scene('update')->check($data);
            } catch (ValidateException $e) {
                return json([
                    'code' => 400,
                    'msg'  => $e->getMessage()
                ]);
            }

            // 更新商品
            $result = $this->goodsLogic->updateGoods($id, $data);

            if (!$result) {
                return json([
                    'code' => 404,
                    'msg'  => '商品不存在或更新失败'
                ]);
            }

            return json([
                'code' => 200,
                'msg'  => '更新成功'
            ]);
        } catch (\Exception $e) {
            Log::error('更新商品失败: ' . $e->getMessage() . "\n" . $e->getTraceAsString());

            return json([
                'code' => 500,
                'msg'  => '更新商品失败: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * 处理SKUs数据，确保格式正确
     *
     * @param mixed $skus 原始SKUs数据
     * @return array 处理后的SKUs数组
     */
    protected function processSkusData($skus): array
    {
        // 如果skus是字符串，尝试解析为JSON
        if (is_string($skus)) {
            $decodedSkus = json_decode($skus, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $skus = $decodedSkus;
            } else {
                Log::warning('SKUs JSON解析失败: ' . json_last_error_msg());
                return []; // 返回空数组，让验证器处理错误
            }
        }

        // 确保skus是数组
        if (!is_array($skus)) {
            Log::warning('SKUs不是有效的数组格式');
            return []; // 返回空数组，让验证器处理错误
        }

        // 处理每个SKU
        foreach ($skus as &$sku) {
            // 确保数值字段是正确的类型
            if (isset($sku['price'])) {
                // 确保价格是有效的数字
                if (is_numeric($sku['price'])) {
                    $sku['price'] = (float) $sku['price'];
                } else {
                    // 如果价格不是数字，设置为null，让验证器处理错误
                    Log::warning('SKU价格不是有效的数字: ' . $sku['price']);
                    $sku['price'] = null;
                }
            }

            if (isset($sku['stock'])) {
                // 确保库存是有效的数字
                if (is_numeric($sku['stock'])) {
                    $sku['stock'] = (int) $sku['stock'];
                } else {
                    // 如果库存不是数字，设置为null，让验证器处理错误
                    Log::warning('SKU库存不是有效的数字: ' . $sku['stock']);
                    $sku['stock'] = null;
                }
            }

            if (isset($sku['status'])) {
                $sku['status'] = (int) $sku['status'];
            }

            if (isset($sku['id'])) {
                $sku['id'] = (int) $sku['id'];
            }
        }

        return $skus;
    }

    /**
     * 删除商品
     *
     * @param int $id 商品ID
     * @return Response
     */
    public function delete(int $id): Response
    {
        try {
            $result = $this->goodsLogic->deleteGoods($id);

            if (!$result) {
                return json([
                    'code' => 404,
                    'msg'  => '商品不存在或删除失败'
                ]);
            }

            return json([
                'code' => 200,
                'msg'  => '删除成功'
            ]);
        } catch (\Exception $e) {
            Log::error('删除商品失败: ' . $e->getMessage());

            return json([
                'code' => 500,
                'msg'  => '删除商品失败: ' . $e->getMessage()
            ]);
        }
    }
}