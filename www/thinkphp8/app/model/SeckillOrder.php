<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 秒杀订单模型
 * 
 * @property int $id 订单ID
 * @property string $order_sn 订单编号
 * @property int $user_id 用户ID
 * @property int $activity_id 秒杀活动ID
 * @property int $goods_id 秒杀商品ID
 * @property int $sku_id 商品SKU ID
 * @property int $quantity 购买数量
 * @property float $price 秒杀价格
 * @property float $total_amount 订单总金额
 * @property int $status 订单状态：0-待支付，1-已支付，2-已取消，3-已超时
 * @property int $payment_time 支付时间（Unix时间戳）
 * @property string $payment_method 支付方式：wechat-微信支付，alipay-支付宝
 * @property string $transaction_id 支付交易号
 * @property int $created_at 创建时间（Unix时间戳）
 * @property int $updated_at 更新时间（Unix时间戳）
 */
class SeckillOrder extends Model
{
    // 设置表名
    protected $name = 'seckill_order';

    // 自动写入时间戳
    protected $autoWriteTimestamp = true;

    // 定义时间戳字段名
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    // 类型转换
    protected $type = [
        'id'           => 'integer',
        'user_id'      => 'integer',
        'activity_id'  => 'integer',
        'goods_id'     => 'integer',
        'sku_id'       => 'integer',
        'quantity'     => 'integer',
        'price'        => 'float',
        'total_amount' => 'float',
        'status'       => 'integer',
        'payment_time' => 'integer',
        'created_at'   => 'integer',
        'updated_at'   => 'integer',
    ];

    /**
     * 订单状态常量
     */
    const STATUS_PENDING  = 0;  // 待支付
    const STATUS_PAID     = 1;  // 已支付
    const STATUS_CANCELED = 2;  // 已取消
    const STATUS_TIMEOUT  = 3;  // 已超时

    /**
     * 支付方式常量
     */
    const PAYMENT_WECHAT = 'wechat';  // 微信支付
    const PAYMENT_ALIPAY = 'alipay';  // 支付宝

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
     * 关联秒杀商品
     * 
     * @return \think\model\relation\BelongsTo
     */
    public function goods()
    {
        return $this->belongsTo(SeckillGoods::class, 'goods_id', 'id');
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
     * 关联用户
     * 
     * @return \think\model\relation\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * 获取状态文本
     * 
     * @param int|null $status 状态值，默认为null表示使用当前模型的状态
     * @return string 状态文本
     */
    public function getStatusText(?int $status = null): string
    {
        $status = $status ?? $this->status;

        $statusMap = [
            self::STATUS_PENDING  => '待支付',
            self::STATUS_PAID     => '已支付',
            self::STATUS_CANCELED => '已取消',
            self::STATUS_TIMEOUT  => '已超时',
        ];

        return $statusMap[$status] ?? '未知状态';
    }

    /**
     * 获取支付方式文本
     * 
     * @param string|null $method 支付方式，默认为null表示使用当前模型的支付方式
     * @return string 支付方式文本
     */
    public function getPaymentMethodText(?string $method = null): string
    {
        $method = $method ?? $this->payment_method;

        $methodMap = [
            self::PAYMENT_WECHAT => '微信支付',
            self::PAYMENT_ALIPAY => '支付宝',
        ];

        return $methodMap[$method] ?? '未知支付方式';
    }

    /**
     * 标记订单为已支付
     * 
     * @param string $paymentMethod 支付方式
     * @param string $transactionId 支付交易号
     * @return bool 是否成功
     */
    public function markAsPaid(string $paymentMethod, string $transactionId): bool
    {
        if ($this->status != self::STATUS_PENDING) {
            return false;
        }

        $this->status         = self::STATUS_PAID;
        $this->payment_method = $paymentMethod;
        $this->transaction_id = $transactionId;
        $this->payment_time   = time();

        return $this->save();
    }

    /**
     * 取消订单
     * 
     * @return bool 是否成功
     */
    public function cancel(): bool
    {
        if ($this->status != self::STATUS_PENDING) {
            return false;
        }

        $this->status = self::STATUS_CANCELED;

        // 恢复库存
        if ($this->save() && $this->goods) {
            return $this->goods->increaseStock($this->quantity);
        }

        return false;
    }

    /**
     * 标记订单为已超时
     * 
     * @return bool 是否成功
     */
    public function markAsTimeout(): bool
    {
        if ($this->status != self::STATUS_PENDING) {
            return false;
        }

        $this->status = self::STATUS_TIMEOUT;

        // 恢复库存
        if ($this->save() && $this->goods) {
            return $this->goods->increaseStock($this->quantity);
        }

        return false;
    }
}
