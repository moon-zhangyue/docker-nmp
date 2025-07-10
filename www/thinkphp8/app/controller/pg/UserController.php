<?php
declare(strict_types=1);

namespace app\controller\pg;

use app\BaseController;
use app\exception\BusinessException;
use app\service\pg\UserService;
use app\validate\pg\UserValidate;
use app\validate\pg\AddressValidate;
use think\facade\Log;
use think\Response;
use think\Request;

/**
 * 用户控制器
 */
class UserController extends BaseController
{
    /**
     * 用户服务
     *
     * @var UserService
     */
    protected $userService;

    /**
     * 构造函数
     */
    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * 用户注册
     *
     * @return Response
     */
    public function register(Request $request): Response
    {
        $data = $request->post();

        // 验证数据
        try {
            validate(UserValidate::class)
                ->scene('register')
                ->check($data);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }

        try {
            // 注册用户
            $user = $this->userService->register($data);

            return $this->success('注册成功', [
                'user_id'  => $user->id,
                'username' => $user->username
            ]);
        } catch (BusinessException $e) {
            return $this->error($e->getMessage());
        } catch (\Exception $e) {
            Log::error('用户注册系统异常：{error}', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->error('系统异常，请稍后重试');
        }
    }

    /**
     * 用户登录
     *
     * @return Response
     */
    public function login(Request $request)
    {
        $data = $request->post();

        // 验证数据
        try {
            validate(UserValidate::class)
                ->scene('login')
                ->check($data);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }

        try {
            // 登录
            $user = $this->userService->login($data['username'], $data['password']);

            // 生成登录凭证（JWT Token）
            $token = $this->createToken($user->id);

            return $this->success('登录成功', [
                'token'     => $token,
                'user_info' => [
                    'id'       => $user->id,
                    'username' => $user->username,
                    'nickname' => $user->nickname,
                    'avatar'   => $user->avatar,
                    'mobile'   => $user->mobile,
                    'email'    => $user->email,
                ]
            ]);
        } catch (BusinessException $e) {
            return $this->error($e->getMessage());
        } catch (\Exception $e) {
            Log::error('用户登录系统异常：{error}', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->error('系统异常，请稍后重试');
        }
    }

    /**
     * 获取用户信息
     *
     * @return Response
     */
    public function info()
    {
        try {
            // 获取当前登录用户ID
            $userId = $this->getUserId();

            // 获取用户信息
            $user = $this->userService->getUserInfo($userId);

            return $this->success('获取成功', [
                'id'              => $user->id,
                'username'        => $user->username,
                'nickname'        => $user->nickname,
                'avatar'          => $user->avatar,
                'mobile'          => $user->mobile,
                'email'           => $user->email,
                'gender'          => $user->gender,
                'gender_text'     => $user->gender_text,
                'last_login_time' => $user->last_login_time,
                'last_login_ip'   => $user->last_login_ip,
                'create_time'     => $user->create_time,
            ]);
        } catch (BusinessException $e) {
            return $this->error($e->getMessage());
        } catch (\Exception $e) {
            Log::error('获取用户信息系统异常：{error}', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->error('系统异常，请稍后重试');
        }
    }

    /**
     * 更新用户信息
     *
     * @return Response
     */
    public function update()
    {
        $data = $this->request->post();

        // 验证数据
        try {
            validate(UserValidate::class)
                ->scene('update')
                ->check($data);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }

        try {
            // 获取当前登录用户ID
            $userId = $this->getUserId();

            // 更新用户信息
            $user = $this->userService->updateUserInfo($userId, $data);

            return $this->success('更新成功', [
                'id'          => $user->id,
                'nickname'    => $user->nickname,
                'avatar'      => $user->avatar,
                'mobile'      => $user->mobile,
                'gender'      => $user->gender,
                'gender_text' => $user->gender_text,
            ]);
        } catch (BusinessException $e) {
            return $this->error($e->getMessage());
        } catch (\Exception $e) {
            Log::error('更新用户信息系统异常：{error}', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->error('系统异常，请稍后重试');
        }
    }

    /**
     * 修改密码
     *
     * @return Response
     */
    public function changePassword()
    {
        $data = $this->request->post();

        // 验证数据
        try {
            validate(UserValidate::class)
                ->scene('change_password')
                ->check($data);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }

        try {
            // 获取当前登录用户ID
            $userId = $this->getUserId();

            // 修改密码
            $this->userService->changePassword($userId, $data['old_password'], $data['new_password']);

            return $this->success('密码修改成功');
        } catch (BusinessException $e) {
            return $this->error($e->getMessage());
        } catch (\Exception $e) {
            Log::error('修改密码系统异常：{error}', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->error('系统异常，请稍后重试');
        }
    }

    /**
     * 获取用户地址列表
     *
     * @return Response
     */
    public function addressList()
    {
        try {
            // 获取当前登录用户ID
            $userId = $this->getUserId();

            // 获取用户地址列表
            $addresses = \app\model\pg\UserAddress::where('user_id', $userId)->select();

            return $this->success('获取成功', $addresses->toArray());
        } catch (\Exception $e) {
            Log::error('获取用户地址列表系统异常：{error}', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->error('系统异常，请稍后重试');
        }
    }

    /**
     * 添加用户地址
     *
     * @return Response
     */
    public function addAddress()
    {
        $data = $this->request->post();

        // 验证数据
        try {
            validate(AddressValidate::class)
                ->scene('add')
                ->check($data);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }

        try {
            // 获取当前登录用户ID
            $userId = $this->getUserId();

            // 添加地址
            $address = $this->userService->addAddress($userId, $data);

            return $this->success('添加成功', $address->toArray());
        } catch (BusinessException $e) {
            return $this->error($e->getMessage());
        } catch (\Exception $e) {
            Log::error('添加用户地址系统异常', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->error('系统异常，请稍后重试');
        }
    }

    /**
     * 更新用户地址
     *
     * @param int $id 地址ID
     * @return Response
     */
    public function updateAddress(int $id)
    {
        $data = $this->request->post();

        // 验证数据
        try {
            validate(AddressValidate::class)
                ->scene('update')
                ->check($data);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }

        try {
            // 获取当前登录用户ID
            $userId = $this->getUserId();

            // 更新地址
            $address = $this->userService->updateAddress($userId, $id, $data);

            return $this->success('更新成功', $address->toArray());
        } catch (BusinessException $e) {
            return $this->error($e->getMessage());
        } catch (\Exception $e) {
            Log::error('更新用户地址系统异常', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->error('系统异常，请稍后重试');
        }
    }

    /**
     * 删除用户地址
     *
     * @param int $id 地址ID
     * @return Response
     */
    public function deleteAddress(int $id)
    {
        try {
            // 获取当前登录用户ID
            $userId = $this->getUserId();

            // 删除地址
            $this->userService->deleteAddress($userId, $id);

            return $this->success('删除成功');
        } catch (BusinessException $e) {
            return $this->error($e->getMessage());
        } catch (\Exception $e) {
            Log::error('删除用户地址系统异常', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->error('系统异常，请稍后重试');
        }
    }

    /**
     * 设置默认地址
     *
     * @param int $id 地址ID
     * @return Response
     */
    public function setDefaultAddress(int $id)
    {
        try {
            // 获取当前登录用户ID
            $userId = $this->getUserId();

            // 设置默认地址
            $this->userService->setDefaultAddress($userId, $id);

            return $this->success('设置成功');
        } catch (BusinessException $e) {
            return $this->error($e->getMessage());
        } catch (\Exception $e) {
            Log::error('设置默认地址系统异常', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->error('系统异常，请稍后重试');
        }
    }

    /**
     * 创建Token
     *
     * @param int $userId 用户ID
     * @return string
     */
    protected function createToken(int $userId)
    {
        // 这里简单模拟，实际应使用JWT库生成Token
        return md5($userId . time() . uniqid());
    }

    /**
     * 获取当前登录用户ID
     *
     * @return int
     * @throws BusinessException
     */
    protected function getUserId()
    {
        // 这里简单模拟，实际应从JWT Token中获取用户ID
        $userId = $this->request->header('X-User-Id');

        if (!$userId) {
            throw new BusinessException('未登录或登录已过期', 401);
        }

        return (int) $userId;
    }
}