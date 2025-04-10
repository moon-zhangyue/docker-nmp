<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 停车场收费规则模型
 */
class ParkingFeeRule extends Model
{
    /**
     * 表名
     * 
     * @var string
     */
    protected $name = 'parking_fee_rule';

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
     * 规则类型：按小时收费
     */
    const TYPE_HOURLY = 1;

    /**
     * 规则类型：按次收费
     */
    const TYPE_FIXED = 2;

    /**
     * 规则类型：阶梯收费
     */
    const TYPE_TIERED = 3;

    /**
     * 规则状态：启用
     */
    const STATUS_ENABLED = 1;

    /**
     * 规则状态：禁用
     */
    const STATUS_DISABLED = 0;

    /**
     * 规则类型列表
     * 
     * @var array
     */
    public static $typeList = [
        self::TYPE_HOURLY => '按小时收费',
        self::TYPE_FIXED  => '按次收费',
        self::TYPE_TIERED => '阶梯收费'
    ];

    /**
     * 规则状态列表
     * 
     * @var array
     */
    public static $statusList = [
        self::STATUS_ENABLED  => '启用',
        self::STATUS_DISABLED => '禁用'
    ];

    /**
     * 获取规则类型文本
     * 
     * @param int $value 类型值
     * @param array $data 行数据
     * @return string
     */
    public function getTypeTextAttr($value, $data): string
    {
        return self::$typeList[$data['type']] ?? '未知类型';
    }

    /**
     * 获取规则状态文本
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
     * 计算停车费用
     * 
     * @param int $duration 停车时长（分钟）
     * @param int $vehicleType 车辆类型
     * @return float
     */
    public function calculateFee(int $duration, int $vehicleType = Vehicle::TYPE_NORMAL): float
    {
        // 如果是月租车或VIP车辆，则免费
        if ($vehicleType == Vehicle::TYPE_MONTHLY || $vehicleType == Vehicle::TYPE_VIP) {
            return 0;
        }

        // 如果规则被禁用，返回0
        if ($this->status == self::STATUS_DISABLED) {
            return 0;
        }

        // 根据规则类型计算费用
        switch ($this->type) {
            case self::TYPE_HOURLY:
                // 按小时收费
                $hours = ceil($duration / 60);
                return $hours * $this->fee_per_hour;

            case self::TYPE_FIXED:
                // 按次收费
                return $this->fixed_fee;

            case self::TYPE_TIERED:
                // 阶梯收费
                $rules = json_decode($this->tiered_rules, true);
                if (empty($rules)) {
                    return 0;
                }

                $fee = 0;
                $remainingMinutes = $duration;

                foreach ($rules as $rule) {
                    if ($remainingMinutes <= 0) {
                        break;
                    }

                    $minutes          = min($remainingMinutes, $rule['minutes']);
                    $fee += ($minutes / $rule['minutes']) * $rule['fee'];
                    $remainingMinutes -= $minutes;
                }

                return $fee;

            default:
                return 0;
        }
    }
}