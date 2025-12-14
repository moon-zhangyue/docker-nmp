<?php

namespace app\event;

use app\model\User;

/**
 * 用户注册事件
 */
class UserRegistered
{
    /**
     * 事件名称
     */
    const NAME = 'user.registered';
    
    /**
     * 用户实例
     * 
     * @var User
     */
    public $user;
    
    /**
     * 构造函数
     * 
     * @param User $user
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }
}