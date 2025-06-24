<?php
declare(strict_types=1);

namespace app\model\pg;

use think\Model;
use think\model\concern\SoftDelete;

/**
 * 用户地址模型
 */
class UserAddress extends Model
{
    use SoftDelete;
    
    // 设置当前模型对应的数据库连接
    protected $connection = 'postgresql';
    
    // 设置当前模型对应的完整数据表名称
    protected $table = 'user_addresses';
    
    // 设置当前模型的数据库主键
    protected $pk = 'id';
    
    // 设置软删除字段
    protected $deleteTime = 'delete_time';
    
    // 自动写入时间戳
    protected $autoWriteTimestamp = true;
    
    // 类型转换
    protected $type = [
        'id'         => 'integer',
        'user_id'    => 'integer',
        'is_default' => 'boolean'
    ];
    
    /**
     * 获取完整地址
     *
     * @param mixed $value 值
     * @param array $data 数据
     * @return string
     */
    public function getFullAddressAttr($value, $data)
    {
        return $data['province'] . $data['city'] . $data['district'] . $data['detail'];
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
     * 设为默认地址
     *
     * @return bool
     */
    public function setDefault()
    {
        // 先将该用户的所有地址设为非默认
        self::where('user_id', $this->user_id)->update(['is_default' => false]);
        
        // 将当前地址设为默认
        $this->is_default = true;
        return $this->save();
    }
} 