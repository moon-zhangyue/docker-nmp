<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 车辆信息模型
 */
class Vehicle extends Model
{
    /**
     * 表名
     * 
     * @var string
     */
    protected $name = 'vehicle';

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
     * 车辆类型：普通车辆
     */
    const TYPE_NORMAL = 1;

    /**
     * 车辆类型：月租车辆
     */
    const TYPE_MONTHLY = 2;

    /**
     * 车辆类型：VIP车辆
     */
    const TYPE_VIP = 3;

    /**
     * 车辆类型：黑名单车辆
     */
    const TYPE_BLACKLIST = 4;

    /**
     * 车辆类型列表
     * 
     * @var array
     */
    public static $typeList = [
        self::TYPE_NORMAL    => '普通车辆',
        self::TYPE_MONTHLY   => '月租车辆',
        self::TYPE_VIP       => 'VIP车辆',
        self::TYPE_BLACKLIST => '黑名单车辆'
    ];

    /**
     * 获取车辆类型文本
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
     * 关联停车记录
     */
    public function parkingRecords()
    {
        return $this->hasMany(ParkingRecord::class, 'plate_number', 'plate_number');
    }

    /**
     * 关联月租信息
     */
    public function monthlyPass()
    {
        return $this->hasOne(MonthlyPass::class, 'plate_number', 'plate_number');
    }

    /**
     * 检查车辆是否有效的月租
     * 
     * @return bool
     */
    public function hasValidMonthlyPass(): bool
    {
        if ($this->type != self::TYPE_MONTHLY) {
            return false;
        }

        $pass = $this->monthlyPass;
        if (!$pass) {
            return false;
        }

        return $pass->isValid();
    }
}