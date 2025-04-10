<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 月租通行证模型
 */
class MonthlyPass extends Model
{
    /**
     * 表名
     * 
     * @var string
     */
    protected $name = 'monthly_pass';

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
     * 关联车辆信息
     */
    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'plate_number', 'plate_number');
    }

    /**
     * 检查通行证是否有效
     * 
     * @return bool
     */
    public function isValid(): bool
    {
        $now       = time();
        $startTime = strtotime($this->start_date);
        $endTime   = strtotime($this->end_date);

        return $now >= $startTime && $now <= $endTime && $this->status == 1;
    }

    /**
     * 获取剩余天数
     * 
     * @return int
     */
    public function getRemainingDays(): int
    {
        $now     = time();
        $endTime = strtotime($this->end_date);

        if ($now > $endTime) {
            return 0;
        }

        return (int)ceil(($endTime - $now) / 86400);
    }

    /**
     * 续费月租
     * 
     * @param int $months 续费月数
     * @return bool
     */
    public function renew(int $months): bool
    {
        $endDate        = date('Y-m-d', strtotime("+{$months} months", strtotime($this->end_date)));
        $this->end_date = $endDate;
        $this->status   = 1;

        return $this->save();
    }
}