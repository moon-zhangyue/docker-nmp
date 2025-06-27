<?php
declare(strict_types=1);

namespace app\controller\pg;

use app\BaseController;
use app\exception\BusinessException;
use app\service\pg\GoodsService;
use app\validate\pg\GoodsValidate;
use think\facade\Log;
use think\Response;

/**
 * 商品控制器
 */
class GoodsController extends BaseController
{
    /**
     * 商品服务
     *
     * @var GoodsService
     */
    protected $goodsService;

    /**
     * 构造函数
     */
    public function __construct(GoodsService $goodsService)
    {
        $this->goodsService = $goodsService;
    }

    /**
     * 获取商品列表
     *
     * @return Response
     */
    public function list()
    {
        $params = $this->request->get();

        // 验证数据
        try {
            validate(GoodsValidate::class)
                ->scene('list')
                ->check($params);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }

        $page  = isset($params['page']) ? (int) $params['page'] : 1;
        $limit = isset($params['limit']) ? (int) $params['limit'] : 10;

        try {
            // 获取商品列表
            $result = $this->goodsService->getGoodsList($params, $page, $limit);

            return $this->success('获取成功', $result);
        } catch (\Exception $e) {
            Log::error('获取商品列表异常：{error}', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString(), 'params' => $params]);
            return $this->error('获取商品列表失败');
        }
    }

    /**
     * 获取商品详情
     *
     * @param int $id 商品ID
     * @return Response
     */
    public function detail(int $id)
    {
        try {
            // 获取商品详情
            $goods = $this->goodsService->getGoodsDetail($id);

            return $this->success('获取成功', $goods->toArray());
        } catch (BusinessException $e) {
            return $this->error($e->getMessage());
        } catch (\Exception $e) {
            Log::error('获取商品详情异常：{error}', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString(), 'goods_id' => $id]);
            return $this->error('获取商品详情失败');
        }
    }

    /**
     * 获取商品SKU信息
     *
     * @param int $id SKU ID
     * @return Response
     */
    public function sku(int $id)
    {
        try {
            // 获取商品SKU信息
            $sku = $this->goodsService->getGoodsSku($id);

            return $this->success('获取成功', $sku->toArray());
        } catch (BusinessException $e) {
            return $this->error($e->getMessage());
        } catch (\Exception $e) {
            Log::error('获取商品SKU信息异常：{error}', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString(), 'sku_id' => $id]);
            return $this->error('获取商品规格信息失败');
        }
    }

    /**
     * 获取分类列表
     *
     * @return Response
     */
    public function categoryList()
    {
        $parentId = $this->request->param('parent_id', 0, 'intval');

        try {
            // 获取分类列表
            $categories = $this->goodsService->getCategoryList($parentId);

            return $this->success('获取成功', $categories);
        } catch (\Exception $e) {
            Log::error('获取分类列表异常：{error}', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString(), 'parent_id' => $parentId]);
            return $this->error('获取分类列表失败');
        }
    }

    /**
     * 获取品牌列表
     *
     * @return Response
     */
    public function brandList()
    {
        try {
            // 获取品牌列表
            $brands = $this->goodsService->getBrandList();

            return $this->success('获取成功', $brands);
        } catch (\Exception $e) {
            Log::error('获取品牌列表异常：{error}', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->error('获取品牌列表失败');
        }
    }
}