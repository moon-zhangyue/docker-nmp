<?php
declare(strict_types=1);

namespace app\model\pg;

use think\Model;

/**
 * 订单商品模型
 */
class OrderItem extends Model
{
    // 设置当前模型对应的数据库连接
    protected $connection = 'postgresql';
    
    // 设置当前模型对应的完整数据表名称
    protected $table = 'order_items';
    
    // 设置当前模型的数据库主键
    protected $pk = 'id';
    
    // 自动写入时间戳
    protected $autoWriteTimestamp = true;
    
    // 类型转换
    protected $type = [
        'id'         => 'integer',
        'order_id'   => 'integer',
        'goods_id'   => 'integer',
        'sku_id'     => 'integer',
        'price'      => 'float',
        'quantity'   => 'integer',
        'total_amount' => 'float',
        'specs'      => 'array'
    ];
    
    /**
     * 关联订单
     *
     * @return \think\model\relation\BelongsTo
     */
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }
    
    /**
     * 关联商品
     *
     * @return \think\model\relation\BelongsTo
     */
    public function goods()
    {
        return $this->belongsTo(Goods::class, 'goods_id', 'id');
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
} 