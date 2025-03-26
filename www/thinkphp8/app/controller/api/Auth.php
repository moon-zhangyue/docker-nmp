<?php
declare(strict_types=1);

namespace app\controller\api;

use app\BaseController;
use app\service\AuthService;
use think\facade\Log;
use think\Request;
use Jenssegers\Agent\Agent;

/**
 * API认证控制器
 */
class Auth extends BaseController
{
    /**
     * 用户登录
     * 
     * @param Request $request
     * @return \think\Response
     */
    public function login(Request $request)
    {
        // 验证参数
        $params   = $request->post();
        $validate = validate([
            'username' => 'require|min:3',
            'password' => 'require|min:6',
        ], [
            'username.require' => '用户名不能为空',
            'username.min'     => '用户名长度不能少于3个字符',
            'password.require' => '密码不能为空',
            'password.min'     => '密码长度不能少于6个字符',
        ]);
        Log::info('用户登录参数:' . json_encode($params, JSON_UNESCAPED_UNICODE));

        if (!$validate->check($params)) {
            return $this->error($validate->getError());
        }

        // 获取客户端信息
        $agent = new Agent();
        $agent->setUserAgent($request->header('User-Agent'));

        // 记录登录尝试
        Log::info('用户登录尝试:' . json_encode([
            'username'   => $params['username'],
            'ip'         => $request->ip(),
            'user_agent' => [
                'browser'   => $agent->browser(),
                'platform'  => $agent->platform(),
                'device'    => $agent->device(),
                'is_mobile' => $agent->isPhone(),
            ]
        ], JSON_UNESCAPED_UNICODE));

        // 调用认证服务
        $authService = new AuthService();
        $result      = $authService->login($params['username'], $params['password']);

        if (!$result) {
            // 登录失败
            Log::warning('用户登录失败:{username},{ip}', [
                'username' => $params['username'],
                'ip'       => $request->ip()
            ]);
            return $this->error('用户名或密码错误');
        }

        // 登录成功，返回令牌
        return $this->success('登录成功', [
            'token'         => $result['access_token'],
            'refresh_token' => $result['refresh_token'],
            'expire_time'   => $result['expire_time'],
            'user'          => [
                'id'         => $result['user']['id'],
                'username'   => $result['user']['username'],
                'email'      => $result['user']['email'],
                'role'       => $result['user']['role'],
                'role_label' => $result['user']['role_label'],
            ]
        ]);
    }

    /**
     * 用户注册
     * 
     * @param Request $request
     * @return \think\Response
     */
    public function register(Request $request)
    {
        // 验证参数
        $params   = $request->post();
        $validate = validate([
            'username'         => 'require|min:3|alphaDash',
            'password'         => 'require|min:6',
            'password_confirm' => 'require|confirm:password',
            'email'            => 'email',
        ], [
            'username.require'         => '用户名不能为空',
            'username.min'             => '用户名长度不能少于3个字符',
            'username.alphaDash'       => '用户名只能包含字母、数字、下划线和破折号',
            'password.require'         => '密码不能为空',
            'password.min'             => '密码长度不能少于6个字符',
            'password_confirm.require' => '确认密码不能为空',
            'password_confirm.confirm' => '两次输入的密码不一致',
            'email.email'              => '邮箱格式不正确',
        ]);

        if (!$validate->check($params)) {
            return $this->error($validate->getError());
        }

        // 调用认证服务注册
        $authService = new AuthService();
        $userData    = [
            'username' => $params['username'],
            'password' => $params['password'],
            'email'    => $params['email'] ?? '',
            'nickname' => $params['nickname'] ?? $params['username'],
            'role'     => 'viewer', // 默认注册为查看者角色
        ];

        // 记录注册尝试
        Log::info('用户注册尝试', [
            'username' => $userData['username'],
            'email'    => $userData['email'],
            'ip'       => $request->ip(),
        ]);

        $result = $authService->register($userData);

        if (!$result) {
            // 注册失败
            return $this->error('注册失败，用户名或邮箱已存在');
        }

        // 注册成功，返回令牌和用户信息
        return $this->success('注册成功', [
            'token'         => $result['token'],
            'refresh_token' => $result['refresh_token'],
            'expire_time'   => $result['expire_time'],
            'user'          => [
                'id'         => $result['user']['id'],
                'username'   => $result['user']['username'],
                'nickname'   => $result['user']['nickname'],
                'email'      => $result['user']['email'],
                'role'       => $result['user']['role'],
                'role_label' => $result['user']['role_label'],
            ]
        ]);
    }

    /**
     * 刷新令牌
     * 
     * @param Request $request
     * @return \think\Response
     */
    public function refresh(Request $request)
    {
        // 获取刷新令牌
        $refreshToken = $request->post('refresh_token');

        if (empty($refreshToken)) {
            return $this->error('刷新令牌不能为空');
        }

        // 调用认证服务刷新令牌
        $authService = new AuthService();
        $result      = $authService->refreshToken($refreshToken);

        if (!$result) {
            return $this->error('刷新令牌失败，令牌可能已过期或无效');
        }

        // 返回新令牌
        return $this->success('刷新令牌成功', [
            'token'         => $result['token'],
            'refresh_token' => $result['refresh_token'],
            'expire_time'   => $result['expire_time'],
        ]);
    }

    /**
     * 用户登出
     * 
     * @param Request $request
     * @return \think\Response
     */
    public function logout(Request $request)
    {
        // 获取令牌
        $token = $request->header('Authorization');

        if (empty($token)) {
            return $this->error('未提供令牌');
        }

        // 去除Bearer前缀
        if (strpos($token, 'Bearer ') === 0) {
            $token = substr($token, 7);
        }

        // 调用认证服务使令牌失效
        $authService = new AuthService();
        $result      = $authService->logout($token);

        if (!$result) {
            return $this->error('登出失败');
        }

        return $this->success('登出成功');
    }

    /**
     * 获取当前用户信息
     * 
     * @param Request $request
     * @return \think\Response
     */
    public function me(Request $request)
    {
        // 用户信息已在中间件中绑定到请求
        $user = $request->user;

        if (empty($user)) {
            return $this->error('获取用户信息失败');
        }

        return $this->success('获取用户信息成功', [
            'id'         => $user['id'],
            'username'   => $user['username'],
            'nickname'   => $user['nickname'] ?? $user['username'],
            'email'      => $user['email'] ?? '',
            'role'       => $user['role'],
            'role_label' => isset($user['role']) ? $this->getRoleLabel($user['role']) : '未知角色',
        ]);
    }

    /**
     * 获取角色标签
     * 
     * @param string $role 角色名
     * @return string 角色标签
     */
    protected function getRoleLabel(string $role): string
    {
        $roles = [
            'admin'    => '管理员',
            'operator' => '操作员',
            'viewer'   => '查看者',
        ];

        return $roles[$role] ?? '未知角色';
    }

    /**
     * 返回成功响应
     * 
     * @param string $message 成功信息
     * @param array $data 数据
     * @return \think\Response
     */
    protected function success(string $message, array $data = []): \think\Response
    {
        return json([
            'code'    => 0,
            'message' => $message,
            'data'    => $data
        ]);
    }

    /**
     * 返回错误响应
     * 
     * @param string $message 错误信息
     * @param int $code 错误码
     * @param array $data 数据
     * @return \think\Response
     */
    protected function error(string $message, int $code = 400, array $data = []): \think\Response
    {
        return json([
            'code'    => $code,
            'message' => $message,
            'data'    => $data
        ]);
    }
}