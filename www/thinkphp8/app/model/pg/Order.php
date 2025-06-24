<?php
declare(strict_types=1);

namespace app\model\pg;

use think\Model;
use think\model\concern\SoftDelete;

/**
 * 订单模型
 */
class Order extends Model
{
    use SoftDelete;

    // 设置当前模型对应的数据库连接
    protected $connection = 'postgresql';

    // 设置当前模型对应的完整数据表名称
    protected $table = 'orders';

    // 设置当前模型的数据库主键
    protected $pk = 'id';

    // 设置软删除字段
    protected $deleteTime = 'delete_time';

    // 自动写入时间戳
    protected $autoWriteTimestamp = true;

    // 类型转换
    protected $type = [
        'id'              => 'integer',
        'user_id'         => 'integer',
        'total_amount'    => 'float',
        'pay_amount'      => 'float',
        'discount_amount' => 'float',
        'freight_amount'  => 'float',
        'status'          => 'integer',
        'pay_status'      => 'integer',
        'pay_method'      => 'integer',
        'ship_status'     => 'integer'
    ];

    // 订单状态：待付款
    const STATUS_PENDING = 0;
    // 订单状态：待发货
    const STATUS_PROCESSING = 1;
    // 订单状态：待收货
    const STATUS_SHIPPED = 2;
    // 订单状态：已完成
    const STATUS_COMPLETED = 3;
    // 订单状态：已关闭
    const STATUS_CLOSED = 4;
    // 订单状态：已取消
    const STATUS_CANCELLED = 5;

    // 支付状态：未支付
    const PAY_STATUS_UNPAID = 0;
    // 支付状态：已支付
    const PAY_STATUS_PAID = 1;

    // 支付方式：支付宝
    const PAY_METHOD_ALIPAY = 1;
    // 支付方式：微信
    const PAY_METHOD_WECHAT = 2;
    // 支付方式：银行卡
    const PAY_METHOD_BANK = 3;

    // 发货状态：未发货
    const SHIP_STATUS_UNSHIPPED = 0;
    // 发货状态：已发货
    const SHIP_STATUS_SHIPPED = 1;
    // 发货状态：已收货
    const SHIP_STATUS_RECEIVED = 2;

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
     * 关联订单商品
     *
     * @return \think\model\relation\HasMany
     */
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class, 'order_id', 'id');
    }

    /**
     * 关联订单日志
     *
     * @return \think\model\relation\HasMany
     */
    public function orderLogs()
    {
        return $this->hasMany(OrderLog::class, 'order_id', 'id');
    }

    /**
     * 获取订单状态文字
     *
     * @param mixed $value 值
     * @param array $data 数据
     * @return string
     */
    public function getStatusTextAttr($value, $data)
    {
        $status    = $data['status'] ?? null;
        $statusMap = [
            self::STATUS_PENDING    => '待付款',
            self::STATUS_PROCESSING => '待发货',
            self::STATUS_SHIPPED    => '待收货',
            self::STATUS_COMPLETED  => '已完成',
            self::STATUS_CLOSED     => '已关闭',
            self::STATUS_CANCELLED  => '已取消',
        ];

        return $statusMap[$status] ?? '未知状态';
    }

    /**
     * 获取支付状态文字
     *
     * @param mixed $value 值
     * @param array $data 数据
     * @return string
     */
    public function getPayStatusTextAttr($value, $data)
    {
        $payStatus    = $data['pay_status'] ?? null;
        $payStatusMap = [
            self::PAY_STATUS_UNPAID => '未支付',
            self::PAY_STATUS_PAID   => '已支付',
        ];

        return $payStatusMap[$payStatus] ?? '未知状态';
    }

    /**
     * 获取支付方式文字
     *
     * @param mixed $value 值
     * @param array $data 数据
     * @return string
     */
    public function getPayMethodTextAttr($value, $data)
    {
        $payMethod    = $data['pay_method'] ?? null;
        $payMethodMap = [
            self::PAY_METHOD_ALIPAY => '支付宝',
            self::PAY_METHOD_WECHAT => '微信',
            self::PAY_METHOD_BANK   => '银行卡',
        ];

        return $payMethodMap[$payMethod] ?? '未知方式';
    }

    /**
     * 获取发货状态文字
     *
     * @param mixed $value 值
     * @param array $data 数据
     * @return string
     */
    public function getShipStatusTextAttr($value, $data)
    {
        $shipStatus    = $data['ship_status'] ?? null;
        $shipStatusMap = [
            self::SHIP_STATUS_UNSHIPPED => '未发货',
            self::SHIP_STATUS_SHIPPED   => '已发货',
            self::SHIP_STATUS_RECEIVED  => '已收货',
        ];

        return $shipStatusMap[$shipStatus] ?? '未知状态';
    }

    /**
     * 获取完整收货地址
     *
     * @param mixed $value 值
     * @param array $data 数据
     * @return string
     */
    public function getFullAddressAttr($value, $data)
    {
        return ($data['receiver_province'] ?? '')
            . ($data['receiver_city'] ?? '')
            . ($data['receiver_district'] ?? '')
            . ($data['receiver_address'] ?? '');
    }

    /**
     * 生成订单编号
     *
     * @return string
     */
    public static function generateOrderNo()
    {
        return date('YmdHis') . mt_rand(1000, 9999);
    }

    /**
     * 搜索订单编号
     *
     * @param \think\db\Query $query 查询对象
     * @param mixed $value 搜索值
     * @return void
     */
    public function searchOrderNoAttr($query, $value)
    {
        if (!empty($value)) {
            $query->where('order_no', 'like', "%{$value}%");
        }
    }

    /**
     * 搜索订单状态
     *
     * @param \think\db\Query $query 查询对象
     * @param mixed $value 搜索值
     * @return void
     */
    public function searchStatusAttr($query, $value)
    {
        if ($value !== '' && $value !== null) {
            $query->where('status', $value);
        }
    }

    /**
     * 搜索订单创建时间范围
     *
     * @param \think\db\Query $query 查询对象
     * @param mixed $value 搜索值 [start_time, end_time]
     * @return void
     */
    public function searchTimeRangeAttr($query, $value)
    {
        if (!empty($value) && is_array($value)) {
            if (!empty($value[0])) {
                $query->where('create_time', '>=', $value[0]);
            }

            if (!empty($value[1])) {
                $query->where('create_time', '<=', $value[1]);
            }
        }
    }
}