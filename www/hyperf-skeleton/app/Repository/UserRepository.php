<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\User;

class UserRepository
{
    /**
     * 根据用户ID查找用户
     *
     * @param int $userId 用户ID
     * @return User|null 用户对象
     */
    public function findById(int $userId): ?User
    {
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
        $user->balance = bcsub((string) $user->balance, (string) $amount, 2);
        $user->save();
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
        $user->balance = bcadd((string) $user->balance, (string) $amount, 2);
        $user->save();
        return $user;
    }
}