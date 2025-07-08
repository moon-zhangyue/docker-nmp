<?php
declare(strict_types=1);

namespace app\model\pg;

use think\Model;

/**
 * 购物车模型
 */
class Cart extends Model
{
    // 设置当前模型对应的数据库连接
    protected $connection = 'postgresql';

    // 设置当前模型对应的完整数据表名称
    protected $table = 'carts';

    // 设置当前模型的数据库主键
    protected $pk = 'id';

    // 自动写入时间戳
    protected $autoWriteTimestamp = true;

    // 类型转换
    protected $type = [
        'id'       => 'integer',
        'user_id'  => 'integer',
        'sku_id'   => 'integer',
        'quantity' => 'integer',
        'selected' => 'boolean',
    ];

    /**
     * 关联用户
     *
     * @return \think\model\relation\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * 关联商品SKU
     *
     * @return \think\model\relation\BelongsTo
     */
    public function sku()
    {
        return $this->belongsTo(GoodsSku::class, 'sku_id', 'id');
    }

    /**
     * 获取购物车列表
     *
     * @param int $userId 用户ID
     * @return \think\Collection
     */
    public static function getCartList(int $userId)
    {
        return self::with([
            'sku' => function ($query) {
                $query->with([
                    'goods' => function ($query) {
                        $query->field('id,name,cover');
                    }
                ]);
            }
        ])->where('user_id', $userId)
            ->order('id', 'desc')
            ->select();
    }

    /**
     * 获取购物车已选商品
     *
     * @param int $userId 用户ID
     * @return \think\Collection
     */
    public static function getSelectedItems(int $userId)
    {
        return self::with([
            'sku' => function ($query) {
                $query->with([
                    'goods' => function ($query) {
                        $query->field('id,name,cover');
                    }
                ]);
            }
        ])->where('user_id', $userId)
            ->where('selected', true)
            ->order('id', 'desc')
            ->select();
    }

    /**
     * 添加商品到购物车
     *
     * @param int $userId 用户ID
     * @param int $skuId SKU ID
     * @param int $quantity 数量
     * @return Cart
     */
    public static function addToCart(int $userId, int $skuId, int $quantity = 1)
    {
        // 查询购物车中是否已存在该商品
        $cart = self::where('user_id', $userId)
            ->where('sku_id', $skuId)
            ->find();

        if ($cart) {
            // 如果已存在，增加数量
            $cart->quantity += $quantity;
            $cart->selected = true;
            $cart->save();
        } else {
            // 如果不存在，创建新购物车项
            $cart           = new self;
            $cart->user_id  = $userId;
            $cart->sku_id   = $skuId;
            $cart->quantity = $quantity;
            $cart->selected = true;
            $cart->save();
        }

        return $cart;
    }

    /**
     * 更新购物车商品数量
     *
     * @param int $id 购物车ID
     * @param int $userId 用户ID
     * @param int $quantity 数量
     * @return bool
     */
    public static function updateQuantity(int $id, int $userId, int $quantity)
    {
        return self::where('id', $id)
            ->where('user_id', $userId)
            ->update(['quantity' => $quantity]);
    }

    /**
     * 更新购物车商品选中状态
     *
     * @param int $id 购物车ID
     * @param int $userId 用户ID
     * @param bool $selected 是否选中
     * @return bool
     */
    public static function updateSelected(int $id, int $userId, bool $selected)
    {
        return self::where('id', $id)
            ->where('user_id', $userId)
            ->update(['selected' => $selected]);
    }

    /**
     * 全选/全不选
     *
     * @param int $userId 用户ID
     * @param bool $selected 是否选中
     * @return bool
     */
    public static function selectAll(int $userId, bool $selected)
    {
        return self::where('user_id', $userId)
            ->update(['selected' => $selected]);
    }

    /**
     * 删除购物车商品
     *
     * @param int $id 购物车ID
     * @param int $userId 用户ID
     * @return bool
     */
    public static function removeFromCart(int $id, int $userId)
    {
        return self::where('id', $id)
            ->where('user_id', $userId)
            ->delete();
    }

    /**
     * 清空购物车
     *
     * @param int $userId 用户ID
     * @return bool
     */
    public static function clearCart(int $userId)
    {
        return self::where('user_id', $userId)
            ->delete();
    }

    /**
     * 获取购物车商品总数
     *
     * @param int $userId 用户ID
     * @return int
     */
    public static function getCartCount(int $userId)
    {
        return self::where('user_id', $userId)
            ->sum('quantity');
    }

    /**
     * 获取购物车商品总价
     *
     * @param int $userId 用户ID
     * @param bool $onlySelected 是否只计算选中的商品
     * @return float
     */
    public static function getCartTotal(int $userId, bool $onlySelected = true)
    {
        $query = self::alias('c')
            ->join('goods_skus s', 'c.sku_id = s.id')
            ->where('c.user_id', $userId);

        if ($onlySelected) {
            $query->where('c.selected', true);
        }

        $total = $query->sum('c.quantity * s.price');

        return $total ?: 0;
    }
}