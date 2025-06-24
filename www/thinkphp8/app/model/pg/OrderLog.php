<?php
declare(strict_types=1);

namespace app\model\pg;

use think\Model;

/**
 * 订单日志模型
 */
class OrderLog extends Model
{
    // 设置当前模型对应的数据库连接
    protected $connection = 'postgresql';
    
    // 设置当前模型对应的完整数据表名称
    protected $table = 'order_logs';
    
    // 设置当前模型的数据库主键
    protected $pk = 'id';
    
    // 自动写入时间戳
    protected $autoWriteTimestamp = true;
    
    // 类型转换
    protected $type = [
        'id'       => 'integer',
        'order_id' => 'integer',
        'user_id'  => 'integer',
        'type'     => 'integer'
    ];
    
    // 日志类型：系统
    const TYPE_SYSTEM = 0;
    // 日志类型：用户
    const TYPE_USER = 1;
    
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
     * 关联用户
     *
     * @return \think\model\relation\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
    
    /**
     * 获取日志类型文字
     *
     * @param mixed $value 值
     * @param array $data 数据
     * @return string
     */
    public function getTypeTextAttr($value, $data)
    {
        $type = $data['type'] ?? null;
        $typeMap = [
            self::TYPE_SYSTEM => '系统',
            self::TYPE_USER => '用户',
        ];
        
        return $typeMap[$type] ?? '未知类型';
    }
    
    /**
     * 记录订单日志
     *
     * @param int $orderId 订单ID
     * @param string $orderNo 订单编号
     * @param int $userId 用户ID，0表示系统操作
     * @param string $action 操作行为
     * @param string $content 日志内容
     * @param int $type 日志类型，0表示系统，1表示用户
     * @param string $ip IP地址
     * @return OrderLog
     */
    public static function record(int $orderId, string $orderNo, int $userId, string $action, string $content, int $type = self::TYPE_SYSTEM, string $ip = '')
    {
        $log = new self;
        $log->order_id = $orderId;
        $log->order_no = $orderNo;
        $log->user_id = $userId;
        $log->action = $action;
        $log->content = $content;
        $log->type = $type;
        $log->ip = $ip ?: request()->ip();
        $log->save();
        
        return $log;
    }
} 