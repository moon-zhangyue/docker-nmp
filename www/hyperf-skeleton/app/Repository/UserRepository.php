<?php

// 声明严格类型，确保所有变量类型都严格匹配
declare(strict_types=1);

// 定义命名空间，用于组织代码和避免类名冲突
namespace App\Repository;

// 引入User模型类，用于操作用户数据
use App\Model\User;

// 定义UserRepository类，用于处理用户相关的数据操作
class UserRepository
{
    /**
     * 根据用户ID查找用户
     *
     * @param int $userId 用户ID
     * @return User|null 用户对象，如果找不到则返回null
     */
    public function findById(int $userId): ?User
    {
        // 使用User模型的查询构造器，根据ID查找用户
        return User::query()->where('id', $userId)->first();
    }

    /**
     * 减少用户余额
     *
     * @param User $user 用户对象
     * @param float $amount 减少金额
     * @return User 更新后的用户对象
     */
    public function decreaseBalance(User $user, float $amount): User
    {
        // 使用bcsub函数进行精确的浮点数减法运算，确保余额计算的准确性
        $user->balance = bcsub((string) $user->balance, (string) $amount, 2);
        // 保存更新后的用户对象到数据库
        $user->save();
        // 返回更新后的用户对象
        return $user;
    }

    /**
     * 增加用户余额
     *
     * @param User $user 用户对象
     * @param float $amount 增加金额
     * @return User 更新后的用户对象
     */
    public function increaseBalance(User $user, float $amount): User
    {
        // 使用bcadd函数进行精确的浮点数加法运算，确保余额计算的准确性
        $user->balance = bcadd((string) $user->balance, (string) $amount, 2);
        // 保存更新后的用户对象到数据库
        $user->save();
        // 返回更新后的用户对象
        return $user;
    }
}