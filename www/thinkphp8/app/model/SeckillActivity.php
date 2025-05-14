<?php
declare(strict_types=1);

namespace app\model;

use think\Model;
use think\model\concern\SoftDelete;

/**
 * 秒杀活动模型
 * 
 * @property int $id 活动ID
 * @property string $title 活动标题
 * @property string $description 活动描述
 * @property int $start_time 活动开始时间（Unix时间戳）
 * @property int $end_time 活动结束时间（Unix时间戳）
 * @property int $status 活动状态：0-未开始，1-进行中，2-已结束，3-已取消
 * @property string $rules 活动规则
 * @property int $max_buy_limit 每人最大购买数量限制
 * @property bool $is_featured 是否推荐活动
 * @property string $banner_image 活动banner图片URL
 * @property int $created_at 创建时间（Unix时间戳）
 * @property int $updated_at 更新时间（Unix时间戳）
 */
class SeckillActivity extends Model
{
    // 设置表名
    protected $name = 'seckill_activity';

    // 自动写入时间戳
    protected $autoWriteTimestamp = true;

    // 定义时间戳字段名
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    // 类型转换
    protected $type = [
        'id'            => 'integer',
        'start_time'    => 'integer',
        'end_time'      => 'integer',
        'status'        => 'integer',
        'max_buy_limit' => 'integer',
        'is_featured'   => 'boolean',
        'created_at'    => 'integer',
        'updated_at'    => 'integer',
    ];

    /**
     * 状态常量
     */
    const STATUS_NOT_STARTED = 0; // 未开始
    const STATUS_IN_PROGRESS = 1; // 进行中
    const STATUS_ENDED       = 2;       // 已结束
    const STATUS_CANCELED    = 3;    // 已取消

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
            self::STATUS_NOT_STARTED => '未开始',
            self::STATUS_IN_PROGRESS => '进行中',
            self::STATUS_ENDED       => '已结束',
            self::STATUS_CANCELED    => '已取消',
        ];

        return $statusMap[$status] ?? '未知状态';
    }

    /**
     * 关联秒杀商品
     * 
     * @return \think\model\relation\HasMany
     */
    public function goods()
    {
        return $this->hasMany(SeckillGoods::class, 'activity_id', 'id');
    }

    /**
     * 关联秒杀订单
     * 
     * @return \think\model\relation\HasMany
     */
    public function orders()
    {
        return $this->hasMany(SeckillOrder::class, 'activity_id', 'id');
    }

    /**
     * 获取当前进行中的活动
     * 
     * @return array 活动列表
     */
    public static function getCurrentActivities()
    {
        $now = time();

        return self::where('status', self::STATUS_IN_PROGRESS)
            ->where('start_time', '<=', $now)
            ->where('end_time', '>=', $now)
            ->order('start_time', 'asc')
            ->select();
    }

    /**
     * 获取即将开始的活动
     * 
     * @param int $limit 限制数量
     * @return array 活动列表
     */
    public static function getUpcomingActivities(int $limit = 5)
    {
        $now = time();

        return self::where('status', self::STATUS_NOT_STARTED)
            ->where('start_time', '>', $now)
            ->order('start_time', 'asc')
            ->limit($limit)
            ->select();
    }

    /**
     * 更新活动状态
     * 
     * @return bool 是否更新成功
     */
    public function updateStatus(): bool
    {
        $now = time();

        if ($this->status == self::STATUS_CANCELED) {
            // 已取消的活动不更新状态
            return true;
        }

        if ($now < $this->start_time) {
            $newStatus = self::STATUS_NOT_STARTED;
        } elseif ($now >= $this->start_time && $now <= $this->end_time) {
            $newStatus = self::STATUS_IN_PROGRESS;
        } else {
            $newStatus = self::STATUS_ENDED;
        }

        if ($newStatus != $this->status) {
            $this->status = $newStatus;
            return $this->save();
        }

        return true;
    }
}
