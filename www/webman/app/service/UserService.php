<?php

namespace app\service;

use app\model\User;
use app\queue\redis\UserRegisterNotify;
use support\Log;
use support\Request;
use Webman\RedisQueue\Client;
use support\Db;

class UserService
{
    /**
     * 用户注册
     *
     * @param array $data
     * @return array
     */
    public function register(array $data): array
    {
        try {
            // 验证数据
            if (empty($data['username']) || empty($data['password']) || empty($data['email']) || empty($data['nickname'])) {
                return ['code' => 400, 'msg' => '用户名、密码、昵称和邮箱不能为空'];
            }

            // 检查用户名是否已存在
            if (User::where('username', $data['username'])->exists()) {
                return ['code' => 400, 'msg' => '用户名已存在'];
            }

            // 检查邮箱是否已存在
            if (User::where('email', $data['email'])->exists()) {
                return ['code' => 400, 'msg' => '邮箱已存在'];
            }

            // 开始事务
            Db::beginTransaction();

            // 创建用户
            $user           = new User();
            $user->username = $data['username'];
            $user->nickname = $data['nickname'];
            $user->email    = $data['email'];
            $user->password = $data['password']; // 模型中会自动哈希处理
            $user->phone    = $data['phone'] ?? '';
            $user->status   = 1; // 1-正常 0-禁用
            $user->save();

            // 记录日志
            Log::info('用户注册', ['user_id' => $user->id, 'username' => $user->username, 'nickname' => $user->nickname, 'email' => $user->email]);

            // 提交事务
            Db::commit();

            // 发送注册通知到队列
            $this->sendRegisterNotification($user);

            return ['code' => 200, 'msg' => '注册成功', 'data' => ['user_id' => $user->id]];
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollBack();

            // 记录错误日志
            Log::error('用户注册失败', ['error' => $e->getMessage(), 'data' => $data]);

            return ['code' => 500, 'msg' => '注册失败：' . $e->getMessage()];
        }
    }

    /**
     * 用户登录
     *
     * @param string $username 用户名或邮箱
     * @param string $password 密码
     * @param Request $request 请求对象
     * @return array
     */
    public function login(string $username, string $password, Request $request): array
    {
        try {
            // 查找用户（支持用户名或邮箱登录）
            $user = User::where('username', $username)
                ->orWhere('email', $username)
                ->first();

            if (!$user) {
                return ['code' => 400, 'msg' => '用户不存在'];
            }

            // 验证密码
            if (!$user->validatePassword($password)) {
                // 记录失败日志
                Log::warning('用户登录失败', ['username' => $username, 'ip' => $request->getRealIp()]);
                return ['code' => 400, 'msg' => '密码错误'];
            }

            // 更新登录信息
            $user->last_time = date('Y-m-d H:i:s');
            $user->last_ip   = $request->getRealIp();
            $user->save();

            // 生成会话数据
            $session = $request->session();
            $session->set('user_id', $user->id);
            $session->set('username', $user->username);

            // 记录登录日志
            Log::info('用户登录成功', [
                'user_id'  => $user->id,
                'username' => $user->username,
                'ip'       => $request->getRealIp()
            ]);

            return [
                'code' => 200,
                'msg'  => '登录成功',
                'data' => [
                    'user_id'  => $user->id,
                    'username' => $user->username,
                    'email'    => $user->email
                ]
            ];
        } catch (\Exception $e) {
            // 记录错误日志
            Log::error('用户登录异常', ['error' => $e->getMessage(), 'username' => $username]);

            return ['code' => 500, 'msg' => '登录失败：' . $e->getMessage()];
        }
    }

    /**
     * 获取用户信息
     *
     * @param int $userId
     * @return array
     */
    public function getUserInfo(int $userId): array
    {
        try {
            $user = User::find($userId);

            if (!$user) {
                return ['code' => 404, 'msg' => '用户不存在'];
            }

            return [
                'code' => 200,
                'msg'  => '获取成功',
                'data' => [
                    'user_id'       => $user->id,
                    'username'      => $user->username,
                    'email'         => $user->email,
                    'phone'         => $user->phone,
                    'avatar'        => $user->avatar,
                    'status'        => $user->status,
                    'created_at'    => $user->created_at,
                    'last_login_at' => $user->last_login_at
                ]
            ];
        } catch (\Exception $e) {
            // 记录错误日志
            Log::error('获取用户信息失败', ['error' => $e->getMessage(), 'user_id' => $userId]);

            return ['code' => 500, 'msg' => '获取用户信息失败：' . $e->getMessage()];
        }
    }

    /**
     * 用户退出登录
     *
     * @param Request $request
     * @return array
     */
    public function logout(Request $request): array
    {
        try {
            $userId   = $request->session()->get('user_id');
            $username = $request->session()->get('username');

            // 清除会话
            $request->session()->delete('user_id');
            $request->session()->delete('username');
            $request->session()->flush();

            // 记录日志
            if ($userId) {
                Log::info('用户退出登录', ['user_id' => $userId, 'username' => $username]);
            }

            return ['code' => 200, 'msg' => '退出成功'];
        } catch (\Exception $e) {
            // 记录错误日志
            Log::error('用户退出登录失败', ['error' => $e->getMessage()]);

            return ['code' => 500, 'msg' => '退出失败：' . $e->getMessage()];
        }
    }

    /**
     * 发送注册通知到队列
     *
     * @param User $user
     * @return void
     */
    private function sendRegisterNotification(User $user): void
    {
        try {
            // 发送到Redis队列
            Client::send('user-register-notify', [
                'user_id'  => $user->id,
                'username' => $user->username,
                'email'    => $user->email,
                'time'     => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            // 记录错误日志
            Log::error('发送注册通知失败', ['error' => $e->getMessage(), 'user_id' => $user->id]);
        }
    }
}