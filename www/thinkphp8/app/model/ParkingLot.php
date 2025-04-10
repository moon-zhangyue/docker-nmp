<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 停车场信息模型
 */
class ParkingLot extends Model
{
    /**
     * 表名
     * 
     * @var string
     */
    protected $name = 'parking_lot';

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
     * 获取当前可用车位数
     * 
     * @return int
     */
    public function getAvailableSpaces(): int
    {
        return max(0, $this->total_spaces - $this->occupied_spaces);
    }

    /**
     * 更新已占用车位数
     * 
     * @param int $count 当前已占用车位数
     * @return bool
     */
    public function updateOccupiedSpaces(int $count): bool
    {
        $this->occupied_spaces = min($count, $this->total_spaces);
        return $this->save();
    }

    /**
     * 增加已占用车位数
     * 
     * @return bool
     */
    public function incrementOccupiedSpaces(): bool
    {
        if ($this->occupied_spaces < $this->total_spaces) {
            $this->occupied_spaces += 1;
            return $this->save();
        }
        return false;
    }

    /**
     * 减少已占用车位数
     * 
     * @return bool
     */
    public function decrementOccupiedSpaces(): bool
    {
        if ($this->occupied_spaces > 0) {
            $this->occupied_spaces -= 1;
            return $this->save();
        }
        return false;
    }

    /**
     * 检查停车场是否已满
     * 
     * @return bool
     */
    public function isFull(): bool
    {
        return $this->occupied_spaces >= $this->total_spaces;
    }

    /**
     * 获取停车场占用率
     * 
     * @return float
     */
    public function getOccupancyRate(): float
    {
        if ($this->total_spaces <= 0) {
            return 0;
        }

        return round(($this->occupied_spaces / $this->total_spaces) * 100, 2);
    }
}