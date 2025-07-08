<?php
declare(strict_types=1);

namespace app\service\pg;

use app\model\pg\Goods;
use app\model\pg\GoodsSku;
use app\model\pg\Category;
use app\model\pg\Brand;
use app\exception\BusinessException;
use think\facade\Log;
use think\facade\Db;
use think\facade\Cache;

/**
 * 商品服务类
 */
class GoodsService
{
    /**
     * 缓存前缀
     */
    const CACHE_PREFIX = 'goods:';

    /**
     * 缓存时间（秒）
     */
    const CACHE_TIME = 3600;

    /**
     * 获取商品列表
     *
     * @param array $params 查询参数
     * @param int $page 页码
     * @param int $limit 每页数量
     * @return array
     */
    public function getGoodsList(array $params = [], int $page = 1, int $limit = 10)
    {
        // 构建查询条件
        $where = [];

        // 上架状态
        $where['on_sale'] = isset($params['on_sale']) ? $params['on_sale'] : true;

        // 分类条件
        if (!empty($params['category_id'])) {
            $categoryIds          = Category::getAllChildrenIds($params['category_id']);
            $where['category_id'] = $categoryIds;
        }

        // 品牌条件
        if (!empty($params['brand_id'])) {
            $where['brand_id'] = $params['brand_id'];
        }

        // 关键词搜索
        if (!empty($params['keyword'])) {
            $where[] = ['name|sub_title', 'like', "%{$params['keyword']}%"];
        }

        // 查询商品列表
        $goodsQuery = Goods::where($where);

        // 排序
        $sort  = $params['sort'] ?? '';
        $order = $params['order'] ?? 'desc';
        switch ($sort) {
            case 'price':
                // 按价格排序需要关联SKU表
                $goodsQuery->withJoin([
                    'skus' => function ($query) use ($order) {
                        return $query->order('price', $order);
                    }
                ], 'left');
                break;
            case 'sales':
                $goodsQuery->order('sales', $order);
                break;
            case 'new':
                $goodsQuery->order('id', $order);
                break;
            default:
                // 默认按热度和推荐排序
                $goodsQuery->order('is_recommend', 'desc')
                    ->order('is_hot', 'desc')
                    ->order('sort', 'asc')
                    ->order('id', 'desc');
                break;
        }

        // 查询商品总数
        $total = $goodsQuery->count();

        // 分页查询
        $goods = $goodsQuery->with(['category', 'brand'])
            ->hidden(['delete_time'])
            ->page($page, $limit)
            ->select();

        // 处理商品价格区间
        foreach ($goods as $item) {
            $item->min_price; // 触发获取属性
            $item->max_price; // 触发获取属性
        }

        // 返回结果
        return [
            'total'        => $total,
            'per_page'     => $limit,
            'current_page' => $page,
            'last_page'    => ceil($total / $limit),
            'data'         => $goods
        ];
    }

    /**
     * 获取商品详情
     *
     * @param int $id 商品ID
     * @return Goods
     * @throws BusinessException
     */
    public function getGoodsDetail(int $id)
    {
        // 尝试从缓存获取
        $cacheKey = self::CACHE_PREFIX . 'detail:' . $id;
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        // 查询商品
        $goods = Goods::with(['category', 'brand', 'skus'])
            ->find($id);

        if (!$goods) {
            throw new BusinessException('商品不存在');
        }

        if (!$goods->on_sale) {
            throw new BusinessException('商品已下架');
        }

        // 缓存商品详情
        Cache::set($cacheKey, $goods, self::CACHE_TIME);

        return $goods;
    }

    /**
     * 获取商品SKU信息
     *
     * @param int $skuId SKU ID
     * @return GoodsSku
     * @throws BusinessException
     */
    public function getGoodsSku(int $skuId)
    {
        // 查询SKU
        $sku = GoodsSku::find($skuId);

        if (!$sku) {
            throw new BusinessException('商品规格不存在');
        }

        // 检查SKU状态
        if (!$sku->status) {
            throw new BusinessException('商品规格已下架');
        }

        // 检查商品状态
        $goods = Goods::find($sku->goods_id);
        if (!$goods || !$goods->on_sale) {
            throw new BusinessException('商品已下架');
        }

        return $sku;
    }

    /**
     * 检查库存
     *
     * @param int $skuId SKU ID
     * @param int $quantity 数量
     * @return bool
     * @throws BusinessException
     */
    public function checkStock(int $skuId, int $quantity)
    {
        // 查询SKU
        $sku = $this->getGoodsSku($skuId);

        // 检查库存
        if ($sku->stock < $quantity) {
            throw new BusinessException('商品库存不足');
        }

        return true;
    }

    /**
     * 减少库存
     *
     * @param int $skuId SKU ID
     * @param int $quantity 数量
     * @return bool
     * @throws BusinessException
     */
    public function decreaseStock(int $skuId, int $quantity)
    {
        // 查询SKU
        $sku = $this->getGoodsSku($skuId);

        // 减少库存
        $result = $sku->decreaseStock($quantity);

        if (!$result) {
            throw new BusinessException('库存不足');
        }

        // 清除缓存
        $this->clearGoodsCache($sku->goods_id);

        return true;
    }

    /**
     * 增加库存
     *
     * @param int $skuId SKU ID
     * @param int $quantity 数量
     * @return bool
     * @throws BusinessException
     */
    public function increaseStock(int $skuId, int $quantity)
    {
        // 查询SKU
        $sku = GoodsSku::find($skuId);

        if (!$sku) {
            throw new BusinessException('商品规格不存在');
        }

        // 增加库存
        $result = $sku->increaseStock($quantity);

        // 清除缓存
        $this->clearGoodsCache($sku->goods_id);

        return $result;
    }

    /**
     * 获取分类列表
     *
     * @param int $parentId 父级分类ID，0表示顶级分类
     * @return array
     */
    public function getCategoryList(int $parentId = 0)
    {
        // 缓存键
        $cacheKey = self::CACHE_PREFIX . 'category:' . $parentId;

        // 尝试从缓存获取
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        // 查询分类树
        $tree = Category::getTree($parentId);

        // 缓存结果
        Cache::set($cacheKey, $tree, self::CACHE_TIME);

        return $tree;
    }

    /**
     * 获取品牌列表
     *
     * @param array $params 查询参数
     * @return array
     */
    public function getBrandList(array $params = [])
    {
        // 缓存键
        $cacheKey = self::CACHE_PREFIX . 'brand:list';

        // 尝试从缓存获取
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        // 查询品牌列表
        $brands = Brand::where('status', true)
            ->order('sort', 'asc')
            ->select();

        // 缓存结果
        Cache::set($cacheKey, $brands, self::CACHE_TIME);

        return $brands;
    }

    /**
     * 清除商品缓存
     *
     * @param int $goodsId 商品ID
     * @return bool
     */
    public function clearGoodsCache(int $goodsId)
    {
        // 清除商品详情缓存
        Cache::delete(self::CACHE_PREFIX . 'detail:' . $goodsId);

        // 清除商品列表缓存
        Cache::delete(self::CACHE_PREFIX . 'list');

        // 记录日志
        Log::info('清除商品缓存', ['goods_id' => $goodsId]);

        return true;
    }

    /**
     * 清除分类缓存
     *
     * @return bool
     */
    public function clearCategoryCache()
    {
        // 清除所有分类缓存
        $keys = Cache::getCacheKey(self::CACHE_PREFIX . 'category:*');
        foreach ($keys as $key) {
            Cache::delete($key);
        }

        return true;
    }

    /**
     * 清除品牌缓存
     *
     * @return bool
     */
    public function clearBrandCache()
    {
        // 清除品牌列表缓存
        Cache::delete(self::CACHE_PREFIX . 'brand:list');

        return true;
    }
}