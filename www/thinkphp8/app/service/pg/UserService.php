<?php
declare(strict_types=1);

namespace app\service\pg;

use app\model\pg\User;
use app\model\pg\UserAddress;
use app\exception\BusinessException;
use think\facade\Log;
use think\facade\Db;

/**
 * 用户服务类
 */
class UserService
{
    /**
     * 用户注册
     *
     * @param array $data 用户数据，包含用户名、密码、邮箱等信息
     * @return User 注册成功的用户对象
     * @throws BusinessException 当用户名、邮箱或手机号已存在，或注册过程中发生异常时抛出
     */
    public function register(array $data)
    {
        // 使用预加载和唯一性检查优化查询
        $existingUser = User::where(function ($query) use ($data) {
            $query->where('username', $data['username'])
                ->orWhere('email', $data['email']);

            if (!empty($data['mobile'])) {
                $query->orWhere('mobile', $data['mobile']);
            }
        })->find();

        if ($existingUser) {
            if ($existingUser->username === $data['username']) {
                Log::error('用户注册失败：用户名已存在');
                throw new BusinessException('用户名已存在');
            }

            if ($existingUser->email === $data['email']) {
                Log::error('用户注册失败：邮箱已存在');
                throw new BusinessException('邮箱已存在');
            }

            if (!empty($data['mobile']) && $existingUser->mobile === $data['mobile']) {
                Log::error('用户注册失败：手机号已存在');
                throw new BusinessException('手机号已存在');
            }
        }

        try {
            // 创建用户并设置初始属性
            $user = User::create([
                'username' => $data['username'],
                'password' => $data['password'], // 密码会在模型中自动加密
                'email'    => $data['email'],
                'mobile'   => $data['mobile'] ?? null,
                'nickname' => $data['nickname'] ?? $data['username'],
                'status'   => 1,
            ]);

            Log::info('用户注册成功 {user_id} {username}', ['user_id' => $user->id, 'username' => $user->username]);

            // 确保返回类型正确
            return User::find($user->id);
        } catch (\Exception $e) {
            // 处理注册异常，记录错误日志并抛出业务异常
            Log::error('用户注册异常 {error} {data}', ['error' => $e->getMessage(), 'data' => $data]);
            throw new BusinessException('用户注册失败：' . $e->getMessage());
        }
    }

    /**
     * 用户登录
     *
     * @param string $username 用户名/邮箱/手机号
     * @param string $password 密码
     * @return User
     * @throws BusinessException
     */
    public function login(string $username, string $password)
    {
        // 根据用户名/邮箱/手机号查询用户
        $user = User::where(function ($query) use ($username) {
            $query->where('username', $username)
                ->whereOr('email', $username)
                ->whereOr('mobile', $username);
        })->find();

        // 用户不存在
        if (!$user) {
            Log::warning('用户登录失败：用户不存在 {username}', ['username' => $username]);
            throw new BusinessException('用户名或密码错误');
        }

        // 用户被禁用
        if ($user->status != 1) {
            Log::warning('用户登录失败：用户已被禁用 {user_id} {username}', ['user_id' => $user->id, 'username' => $user->username]);
            throw new BusinessException('账号已被禁用，请联系管理员');
        }

        // 验证密码
        if (!$user->validatePassword($password)) {
            Log::warning('用户登录失败：密码错误 {user_id} {username}', ['user_id' => $user->id, 'username' => $user->username]);
            throw new BusinessException('用户名或密码错误');
        }

        // 更新登录信息
        $user->last_login_ip   = request()->ip();
        $user->last_login_time = date('Y-m-d H:i:s');
        $user->save();

        Log::info('用户登录成功 {user_id} {username}', ['user_id' => $user->id, 'username' => $user->username]);

        return $user;
    }

    /**
     * 获取用户信息
     *
     * @param int $userId 用户ID
     * @return User
     * @throws BusinessException
     */
    public function getUserInfo(int $userId)
    {
        $user = User::find($userId);

        if (!$user) {
            Log::warning('获取用户信息失败：用户不存在 {user_id}', ['user_id' => $userId]);
            throw new BusinessException('用户不存在');
        }

        return $user;
    }

    /**
     * 更新用户信息
     *
     * @param int $userId 用户ID
     * @param array $data 更新数据
     * @return User
     * @throws BusinessException
     */
    public function updateUserInfo(int $userId, array $data)
    {
        $user = User::find($userId);

        if (!$user) {
            Log::warning('更新用户信息失败：用户不存在 {user_id}', ['user_id' => $userId]);
            throw new BusinessException('用户不存在');
        }

        // 手机号验证
        if (isset($data['mobile']) && !empty($data['mobile']) && $data['mobile'] != $user->mobile) {
            if (User::where('mobile', $data['mobile'])->where('id', '<>', $userId)->find()) {
                Log::warning('更新用户信息失败：手机号已存在 {user_id} {mobile}', ['user_id' => $userId, 'mobile' => $data['mobile']]);
                throw new BusinessException('手机号已被使用');
            }
        }

        try {
            $user->allowField(['nickname', 'mobile', 'avatar', 'gender'])->save($data);

            Log::info('更新用户信息成功 {user_id}', ['user_id' => $userId]);

            return $user;
        } catch (\Exception $e) {
            Log::error('更新用户信息异常 {error} {user_id} {data}', ['error' => $e->getMessage(), 'user_id' => $userId, 'data' => $data]);
            throw new BusinessException('更新用户信息失败：' . $e->getMessage());
        }
    }

    /**
     * 修改密码
     *
     * @param int $userId 用户ID
     * @param string $oldPassword 旧密码
     * @param string $newPassword 新密码
     * @return bool
     * @throws BusinessException
     */
    public function changePassword(int $userId, string $oldPassword, string $newPassword)
    {
        $user = User::find($userId);

        if (!$user) {
            Log::warning('修改密码失败：用户不存在 {user_id}', ['user_id' => $userId]);
            throw new BusinessException('用户不存在');
        }

        // 验证旧密码
        if (!$user->validatePassword($oldPassword)) {
            Log::warning('修改密码失败：旧密码错误 {user_id}', ['user_id' => $userId]);
            throw new BusinessException('旧密码错误');
        }

        try {
            $user->password = $newPassword;
            $result         = $user->save();

            Log::info('修改密码成功 {user_id}', ['user_id' => $userId]);

            return $result;
        } catch (\Exception $e) {
            Log::error('修改密码异常 {error} {user_id}', ['error' => $e->getMessage(), 'user_id' => $userId]);
            throw new BusinessException('修改密码失败：' . $e->getMessage());
        }
    }

    /**
     * 添加用户地址
     *
     * @param int $userId 用户ID
     * @param array $data 地址数据
     * @return UserAddress
     * @throws BusinessException
     */
    public function addAddress(int $userId, array $data)
    {
        // 检查用户是否存在
        if (!User::find($userId)) {
            Log::warning('添加用户地址失败：用户不存在 {user_id}', ['user_id' => $userId]);
            throw new BusinessException('用户不存在');
        }

        // 开启事务
        Db::startTrans();
        try {
            $data['user_id'] = $userId;

            // 如果是默认地址，先将该用户的所有地址设为非默认
            if (isset($data['is_default']) && $data['is_default']) {
                UserAddress::where('user_id', $userId)->update(['is_default' => false]);
            }

            // 添加地址
            $address = UserAddress::create($data);

            // 如果没有其他地址，将当前地址设为默认
            $count = UserAddress::where('user_id', $userId)->count();
            if ($count == 1) {
                UserAddress::update(['is_default' => true], ['id' => $address->id]);
            }

            Db::commit();

            Log::info('添加用户地址成功 {user_id} {address_id}', ['user_id' => $userId, 'address_id' => $address->id]);

            return UserAddress::find($address->id);
        } catch (\Exception $e) {
            Db::rollback();
            Log::error('添加用户地址异常 {error} {user_id} {data}', ['error' => $e->getMessage(), 'user_id' => $userId, 'data' => $data]);
            throw new BusinessException('添加地址失败：' . $e->getMessage());
        }
    }

    /**
     * 更新用户地址
     *
     * @param int $userId 用户ID
     * @param int $addressId 地址ID
     * @param array $data 地址数据
     * @return UserAddress
     * @throws BusinessException
     */
    public function updateAddress(int $userId, int $addressId, array $data)
    {
        // 检查地址是否存在且属于该用户
        $address = UserAddress::where('id', $addressId)->where('user_id', $userId)->find();

        if (!$address) {
            Log::warning('更新用户地址失败：地址不存在或不属于该用户 {user_id} {address_id}', ['user_id' => $userId, 'address_id' => $addressId]);
            throw new BusinessException('地址不存在或不属于您');
        }

        // 开启事务
        Db::startTrans();
        try {
            // 如果是默认地址，先将该用户的所有地址设为非默认
            if (isset($data['is_default']) && $data['is_default'] && !$address->is_default) {
                UserAddress::where('user_id', $userId)->update(['is_default' => false]);
            }

            // 更新地址
            $address->allowField(['name', 'mobile', 'province', 'city', 'district', 'detail', 'is_default'])->save($data);

            Db::commit();

            Log::info('更新用户地址成功 {user_id} {address_id}', ['user_id' => $userId, 'address_id' => $addressId]);

            return $address;
        } catch (\Exception $e) {
            Db::rollback();
            Log::error('更新用户地址异常 {error} {user_id} {address_id} {data}', ['error' => $e->getMessage(), 'user_id' => $userId, 'address_id' => $addressId, 'data' => $data]);
            throw new BusinessException('更新地址失败：' . $e->getMessage());
        }
    }

    /**
     * 删除用户地址
     *
     * @param int $userId 用户ID
     * @param int $addressId 地址ID
     * @return bool
     * @throws BusinessException
     */
    public function deleteAddress(int $userId, int $addressId)
    {
        // 检查地址是否存在且属于该用户
        $address = UserAddress::where('id', $addressId)->where('user_id', $userId)->find();

        if (!$address) {
            Log::warning('删除用户地址失败：地址不存在或不属于该用户 {user_id} {address_id}', ['user_id' => $userId, 'address_id' => $addressId]);
            throw new BusinessException('地址不存在或不属于您');
        }

        // 开启事务
        Db::startTrans();
        try {
            // 删除地址
            $result = $address->delete();

            // 如果删除的是默认地址，且用户还有其他地址，则将最新的地址设为默认
            if ($address->is_default) {
                $newDefault = UserAddress::where('user_id', $userId)->order('id', 'desc')->find();
                if ($newDefault) {
                    $newDefault->is_default = true;
                    $newDefault->save();
                }
            }

            Db::commit();

            Log::info('删除用户地址成功 {user_id} {address_id}', ['user_id' => $userId, 'address_id' => $addressId]);

            return $result;
        } catch (\Exception $e) {
            Db::rollback();
            Log::error('删除用户地址异常 {error} {user_id} {address_id}', ['error' => $e->getMessage(), 'user_id' => $userId, 'address_id' => $addressId]);
            throw new BusinessException('删除地址失败：' . $e->getMessage());
        }
    }

    /**
     * 设置默认地址
     *
     * @param int $userId 用户ID
     * @param int $addressId 地址ID
     * @return bool
     * @throws BusinessException
     */
    public function setDefaultAddress(int $userId, int $addressId)
    {
        // 检查地址是否存在且属于该用户
        $address = UserAddress::where('id', $addressId)->where('user_id', $userId)->find();

        if (!$address) {
            Log::warning('设置默认地址失败：地址不存在或不属于该用户 {user_id} {address_id}', ['user_id' => $userId, 'address_id' => $addressId]);
            throw new BusinessException('地址不存在或不属于您');
        }

        try {
            // 设置默认地址
            $result = $address->setDefault();

            Log::info('设置默认地址成功 {user_id} {address_id}', ['user_id' => $userId, 'address_id' => $addressId]);

            return $result;
        } catch (\Exception $e) {
            Log::error('设置默认地址异常 {error} {user_id} {address_id}', ['error' => $e->getMessage(), 'user_id' => $userId, 'address_id' => $addressId]);
            throw new BusinessException('设置默认地址失败：' . $e->getMessage());
        }
    }
}