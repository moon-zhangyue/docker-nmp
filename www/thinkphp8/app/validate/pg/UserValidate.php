<?php
declare(strict_types=1);

namespace app\validate\pg;

use think\Validate;

/**
 * 用户验证器
 */
class UserValidate extends Validate
{
    /**
     * 验证规则
     *
     * @var array
     */
    protected $rule = [
        'username'      => 'require|alphaNum|length:4,20|unique:users,username',
        'password'      => 'require|length:6,20',
        'confirm_password' => 'require|confirm:password',
        'email'         => 'require|email|unique:users,email',
        'mobile'        => 'mobile|unique:users,mobile',
        'nickname'      => 'length:2,50',
        'avatar'        => 'url',
        'gender'        => 'in:0,1,2',
        'old_password'  => 'require|length:6,20',
        'new_password'  => 'require|length:6,20|different:old_password',
        'confirm_new_password' => 'require|confirm:new_password',
    ];

    /**
     * 错误消息
     *
     * @var array
     */
    protected $message = [
        'username.require'      => '用户名不能为空',
        'username.alphaNum'     => '用户名只能是字母和数字',
        'username.length'       => '用户名长度必须在4-20个字符之间',
        'username.unique'       => '用户名已存在',
        'password.require'      => '密码不能为空',
        'password.length'       => '密码长度必须在6-20个字符之间',
        'confirm_password.require' => '确认密码不能为空',
        'confirm_password.confirm' => '两次输入的密码不一致',
        'email.require'         => '邮箱不能为空',
        'email.email'           => '邮箱格式不正确',
        'email.unique'          => '邮箱已存在',
        'mobile.mobile'         => '手机号格式不正确',
        'mobile.unique'         => '手机号已存在',
        'nickname.length'       => '昵称长度必须在2-50个字符之间',
        'avatar.url'            => '头像必须是有效的URL地址',
        'gender.in'             => '性别选择不正确',
        'old_password.require'  => '旧密码不能为空',
        'old_password.length'   => '旧密码长度必须在6-20个字符之间',
        'new_password.require'  => '新密码不能为空',
        'new_password.length'   => '新密码长度必须在6-20个字符之间',
        'new_password.different' => '新密码不能与旧密码相同',
        'confirm_new_password.require' => '确认新密码不能为空',
        'confirm_new_password.confirm' => '两次输入的新密码不一致',
    ];

    /**
     * 验证场景
     *
     * @var array
     */
    protected $scene = [
        'register' => ['username', 'password', 'confirm_password', 'email', 'mobile', 'nickname'],
        'login'    => ['username', 'password'],
        'update'   => ['nickname', 'mobile', 'avatar', 'gender'],
        'change_password' => ['old_password', 'new_password', 'confirm_new_password'],
    ];
    
    /**
     * 构造函数
     */
    public function __construct()
    {
        parent::__construct();
        
        // 设置数据表连接
        $this->db('postgresql');
    }
} 