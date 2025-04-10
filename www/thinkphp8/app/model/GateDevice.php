<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 闸机设备模型
 */
class GateDevice extends Model
{
    /**
     * 表名
     * 
     * @var string
     */
    protected $name = 'gate_device';

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
     * 设备状态：在线
     */
    const STATUS_ONLINE = 1;

    /**
     * 设备状态：离线
     */
    const STATUS_OFFLINE = 0;

    /**
     * 设备类型：入口
     */
    const TYPE_ENTRANCE = 1;

    /**
     * 设备类型：出口
     */
    const TYPE_EXIT = 2;

    /**
     * 设备类型：双向
     */
    const TYPE_BOTH = 3;

    /**
     * 设备状态列表
     * 
     * @var array
     */
    public static $statusList = [
        self::STATUS_ONLINE  => '在线',
        self::STATUS_OFFLINE => '离线'
    ];

    /**
     * 设备类型列表
     * 
     * @var array
     */
    public static $typeList = [
        self::TYPE_ENTRANCE => '入口',
        self::TYPE_EXIT     => '出口',
        self::TYPE_BOTH     => '双向'
    ];

    /**
     * 获取设备状态文本
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
     * 获取设备类型文本
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
        return $this->hasMany(ParkingRecord::class, 'device_id', 'id');
    }
}