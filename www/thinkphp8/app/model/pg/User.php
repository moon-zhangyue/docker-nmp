<?php
declare(strict_types=1);

namespace app\model\pg;

use think\Model;
use think\model\concern\SoftDelete;

/**
 * 用户模型
 */
class User extends Model
{
    use SoftDelete;
    
    // 设置当前模型对应的数据库连接
    protected $connection = 'postgresql';
    
    // 设置当前模型对应的完整数据表名称
    protected $table = 'users';
    
    // 设置当前模型的数据库主键
    protected $pk = 'id';
    
    // 设置当前模型默认的查询条件
    protected $defaultScope = ['status' => 1];
    
    // 设置软删除字段
    protected $deleteTime = 'delete_time';
    
    // 自动写入时间戳
    protected $autoWriteTimestamp = true;
    
    // 只读字段
    protected $readonly = ['username', 'email'];
    
    // 隐藏属性
    protected $hidden = ['password', 'delete_time'];
    
    // 类型转换
    protected $type = [
        'id'         => 'integer',
        'gender'     => 'integer',
        'status'     => 'integer'
    ];
    
    // 属性类型转换
    protected $cast = [
        'last_login_time' => 'datetime',
        'create_time'     => 'datetime',
        'update_time'     => 'datetime',
        'status'          => 'boolean'
    ];
    
    /**
     * 搜索用户名
     *
     * @param \think\db\Query $query 查询对象
     * @param mixed $value 搜索值
     * @return void
     */
    public function searchUsernameAttr($query, $value)
    {
        if (!empty($value)) {
            $query->where('username', 'like', "%{$value}%");
        }
    }
    
    /**
     * 搜索邮箱
     *
     * @param \think\db\Query $query 查询对象
     * @param mixed $value 搜索值
     * @return void
     */
    public function searchEmailAttr($query, $value)
    {
        if (!empty($value)) {
            $query->where('email', 'like', "%{$value}%");
        }
    }
    
    /**
     * 搜索手机号
     *
     * @param \think\db\Query $query 查询对象
     * @param mixed $value 搜索值
     * @return void
     */
    public function searchMobileAttr($query, $value)
    {
        if (!empty($value)) {
            $query->where('mobile', 'like', "%{$value}%");
        }
    }
    
    /**
     * 搜索状态
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
     * 获取性别文字
     *
     * @param mixed $value 值
     * @param array $data 数据
     * @return string
     */
    public function getGenderTextAttr($value, $data)
    {
        $map = [0 => '未知', 1 => '男', 2 => '女'];
        return $map[$data['gender']] ?? '未知';
    }
    
    /**
     * 获取状态文字
     *
     * @param mixed $value 值
     * @param array $data 数据
     * @return string
     */
    public function getStatusTextAttr($value, $data)
    {
        $map = [0 => '禁用', 1 => '正常'];
        return $map[$data['status']] ?? '未知';
    }
    
    /**
     * 修改器-密码加密
     *
     * @param mixed $value 值
     * @return string
     */
    public function setPasswordAttr($value)
    {
        return $value ? password_hash($value, PASSWORD_DEFAULT) : '';
    }
    
    /**
     * 关联用户地址
     *
     * @return \think\model\relation\HasMany
     */
    public function addresses()
    {
        return $this->hasMany(UserAddress::class, 'user_id', 'id');
    }
    
    /**
     * 关联订单
     *
     * @return \think\model\relation\HasMany
     */
    public function orders()
    {
        return $this->hasMany(Order::class, 'user_id', 'id');
    }
    
    /**
     * 关联购物车
     *
     * @return \think\model\relation\HasMany
     */
    public function carts()
    {
        return $this->hasMany(Cart::class, 'user_id', 'id');
    }
    
    /**
     * 验证密码
     *
     * @param string $password 密码
     * @return bool
     */
    public function validatePassword($password)
    {
        return password_verify($password, $this->password);
    }
} 