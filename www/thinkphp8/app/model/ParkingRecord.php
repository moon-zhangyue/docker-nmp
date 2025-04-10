<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 停车记录模型
 */
class ParkingRecord extends Model
{
    /**
     * 表名
     * 
     * @var string
     */
    protected $name = 'parking_record';

    /**
     * 自动写入时间戳
     * 
     * @var bool
     */
    protected $autoWriteTimestamp = true;

    /**
     * 创建时间字段
     * 
     * @var string
     */
    protected $createTime = 'create_time';

    /**
     * 更新时间字段
     * 
     * @var string
     */
    protected $updateTime = 'update_time';

    /**
     * 记录状态：进场
     */
    const STATUS_ENTRY = 1;

    /**
     * 记录状态：出场
     */
    const STATUS_EXIT = 2;

    /**
     * 记录状态：已完成
     */
    const STATUS_COMPLETED = 3;

    /**
     * 记录状态：异常
     */
    const STATUS_EXCEPTION = 4;

    /**
     * 支付状态：未支付
     */
    const PAYMENT_UNPAID = 0;

    /**
     * 支付状态：已支付
     */
    const PAYMENT_PAID = 1;

    /**
     * 支付状态：免费
     */
    const PAYMENT_FREE = 2;

    /**
     * 记录状态列表
     * 
     * @var array
     */
    public static $statusList = [
        self::STATUS_ENTRY     => '进场',
        self::STATUS_EXIT      => '出场',
        self::STATUS_COMPLETED => '已完成',
        self::STATUS_EXCEPTION => '异常'
    ];

    /**
     * 支付状态列表
     * 
     * @var array
     */
    public static $paymentStatusList = [
        self::PAYMENT_UNPAID => '未支付',
        self::PAYMENT_PAID   => '已支付',
        self::PAYMENT_FREE   => '免费'
    ];

    /**
     * 关联车辆信息
     */
    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'plate_number', 'plate_number');
    }

    /**
     * 关联入场设备
     */
    public function entryDevice()
    {
        return $this->belongsTo(GateDevice::class, 'entry_device_id', 'id');
    }

    /**
     * 关联出场设备
     */
    public function exitDevice()
    {
        return $this->belongsTo(GateDevice::class, 'exit_device_id', 'id');
    }

    /**
     * 获取记录状态文本
     * 
     * @param int $value 状态值
     * @param array $data 行数据
     * @return string
     */
    public function getStatusTextAttr($value, $data): string
    {
        return self::$statusList[$data['status']] ?? '未知状态';
    }

    /**
     * 获取支付状态文本
     * 
     * @param int $value 状态值
     * @param array $data 行数据
     * @return string
     */
    public function getPaymentStatusTextAttr($value, $data): string
    {
        return self::$paymentStatusList[$data['payment_status']] ?? '未知状态';
    }

    /**
     * 计算停车时长（分钟）
     * 
     * @return int
     */
    public function calculateDuration(): int
    {
        if (empty($this->entry_time) || empty($this->exit_time)) {
            return 0;
        }

        $entryTime = strtotime($this->entry_time);
        $exitTime  = strtotime($this->exit_time);

        return max(0, ceil(($exitTime - $entryTime) / 60));
    }

    /**
     * 计算停车费用
     * 
     * @param array $feeRules 收费规则
     * @return float
     */
    public function calculateFee(array $feeRules = []): float
    {
        // 如果是月租车或VIP车辆，则免费
        if ($this->vehicle && ($this->vehicle->type == Vehicle::TYPE_MONTHLY || $this->vehicle->type == Vehicle::TYPE_VIP)) {
            $this->payment_status = self::PAYMENT_FREE;
            $this->fee            = 0;
            return 0;
        }

        // 计算停车时长
        $duration = $this->calculateDuration();

        // 如果没有提供收费规则，使用默认规则
        if (empty($feeRules)) {
            // 默认收费规则：前1小时10元，之后每小时5元
            $fee = 0;
            if ($duration <= 60) {
                $fee = 10;
            } else {
                $fee = 10 + ceil(($duration - 60) / 60) * 5;
            }

            $this->fee = $fee;
            return $fee;
        }

        // 使用提供的收费规则计算
        // 这里可以根据实际需求实现更复杂的收费规则

        return 0;
    }

    /**
     * 完成支付
     * 
     * @param string $paymentMethod 支付方式
     * @return bool
     */
    public function completePayment(string $paymentMethod): bool
    {
        $this->payment_status = self::PAYMENT_PAID;
        $this->payment_method = $paymentMethod;
        $this->payment_time   = date('Y-m-d H:i:s');
        $this->status         = self::STATUS_COMPLETED;

        return $this->save();
    }
}