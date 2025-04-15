<?php

namespace app\model;

use support\Model;

class User extends Model
{
    /**
     * 与模型关联的表名
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * 与表关联的主键
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * 是否自动维护时间戳
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * 可批量赋值的属性
     *
     * @var array
     */
    protected $fillable = [
        'username',
        'email',
        'password',
        'phone',
        'avatar',
        'status',
        'last_login_at',
        'last_login_ip'
    ];

    /**
     * 隐藏的属性
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * 日期属性
     *
     * @var array
     */
    protected $dates = [
        'last_login_at',
    ];

    /**
     * 验证用户密码
     *
     * @param string $password
     * @return bool
     */
    public function validatePassword(string $password): bool
    {
        return password_verify($password, $this->password);
    }

    /**
     * 设置密码（自动哈希）
     *
     * @param string $password
     * @return void
     */
    public function setPasswordAttribute($password)
    {
        $this->attributes['password'] = password_hash($password, PASSWORD_DEFAULT);
    }
}