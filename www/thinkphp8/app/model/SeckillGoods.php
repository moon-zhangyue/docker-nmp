<?php
declare(strict_types=1);

namespace app\model;

use think\Model;
use think\facade\Cache;

/**
 * 秒杀商品模型
 * 
 * @property int $id 秒杀商品ID
 * @property int $activity_id 所属秒杀活动ID
 * @property int $sku_id 商品SKU ID
 * @property int $spu_id 商品SPU ID
 * @property float $original_price 商品原价
 * @property float $seckill_price 秒杀价格
 * @property int $total_stock 秒杀总库存
 * @property int $remain_stock 剩余库存
 * @property int $limit_per_user 每人限购数量
 * @property int $sort_order 排序权重，数值越大越靠前
 * @property int $status 状态：0-下架，1-上架
 * @property int $created_at 创建时间（Unix时间戳）
 * @property int $updated_at 更新时间（Unix时间戳）
 */
class SeckillGoods extends Model
{
    // 设置表名
    protected $name = 'seckill_goods';

    // 自动写入时间戳
    protected $autoWriteTimestamp = true;

    // 定义时间戳字段名
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    // 类型转换
    protected $type = [
        'id'             => 'integer',
        'activity_id'    => 'integer',
        'sku_id'         => 'integer',
        'spu_id'         => 'integer',
        'original_price' => 'float',
        'seckill_price'  => 'float',
        'total_stock'    => 'integer',
        'remain_stock'   => 'integer',
        'limit_per_user' => 'integer',
        'sort_order'     => 'integer',
        'status'         => 'integer',
        'created_at'     => 'integer',
        'updated_at'     => 'integer',
    ];

    /**
     * 状态常量
     */
    const STATUS_OFFLINE = 0; // 下架
    const STATUS_ONLINE  = 1;  // 上架

    /**
     * 关联秒杀活动
     * 
     * @return \think\model\relation\BelongsTo
     */
    public function activity()
    {
        return $this->belongsTo(SeckillActivity::class, 'activity_id', 'id');
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
     * 关联商品SPU
     * 
     * @return \think\model\relation\BelongsTo
     */
    public function spu()
    {
        return $this->belongsTo(GoodsSpu::class, 'spu_id', 'id');
    }

    /**
     * 关联秒杀订单
     * 
     * @return \think\model\relation\HasMany
     */
    public function orders()
    {
        return $this->hasMany(SeckillOrder::class, 'goods_id', 'id');
    }

    /**
     * 获取折扣率
     * 
     * @return float 折扣率（百分比）
     */
    public function getDiscountRate(): float
    {
        if ($this->original_price <= 0) {
            return 0;
        }

        return round(($this->seckill_price / $this->original_price) * 100, 2);
    }

    /**
     * 获取节省金额
     * 
     * @return float 节省金额
     */
    public function getSavedAmount(): float
    {
        return round($this->original_price - $this->seckill_price, 2);
    }

    /**
     * 检查库存是否充足
     * 
     * @param int $quantity 需要的数量
     * @return bool 是否充足
     */
    public function hasEnoughStock(int $quantity = 1): bool
    {
        return $this->remain_stock >= $quantity;
    }

    /**
     * 减少库存
     * 
     * @param int $quantity 减少的数量
     * @return bool 是否成功
     */
    public function decreaseStock(int $quantity = 1): bool
    {
        if (!$this->hasEnoughStock($quantity)) {
            return false;
        }

        $this->remain_stock -= $quantity;
        return $this->save();
    }

    /**
     * 恢复库存
     * 
     * @param int $quantity 恢复的数量
     * @return bool 是否成功
     */
    public function increaseStock(int $quantity = 1): bool
    {
        $this->remain_stock += $quantity;

        // 确保库存不超过总库存
        if ($this->remain_stock > $this->total_stock) {
            $this->remain_stock = $this->total_stock;
        }

        return $this->save();
    }

    /**
     * 同步Redis缓存中的库存到数据库
     * 
     * @return bool 是否成功
     */
    public function syncStockFromRedis(): bool
    {
        $seckillKey  = "seckill:goods:{$this->sku_id}";
        $remainStock = Cache::store('redis')->hGet($seckillKey, 'remain_stock');

        if ($remainStock !== false && is_numeric($remainStock)) {
            $this->remain_stock = (int) $remainStock;
            return $this->save();
        }

        return false;
    }
}
