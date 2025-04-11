<?php
declare(strict_types=1);

namespace app\middleware;

use app\service\AuthService;
use think\facade\Log;
use think\Response;

/**
 * JWT认证中间件
 */
class JwtAuth
{
    /**
     * 处理请求
     * 
     * @param \think\Request $request
     * @param \Closure $next
     * @param array $params 中间件参数
     * @return Response
     */
    public function handle($request, \Closure $next, array $params = [])
    {
        // 不需要验证的接口列表
        $excludePaths = [
            'api/auth/login',
            'api/auth/register',
            'api/auth/refresh',
            'api/auth/logout',
            'api/auth/me',
            'auth/login',
            'auth/register',
            'auth/refresh',
            'auth/logout',
            'auth/me',
            'redpacket/index',
            'parking/lot',
            'parking/record',
            'parking/metrics/occupancy',
            'parking/metrics/device-status',
            'parking/metrics/plate-recognition',
            'parking/metrics/gate-operation',
            'parking/metrics/peak-hours',
            'parking/metrics/duration-stats',
            'parking/metrics/peak-analysis',
            'parking/metrics/occupancy-history',
            'parking/metrics/device-history',
        ];

        // 排除不需要验证的接口
        if (in_array($request->pathinfo(), $excludePaths)) {
            return $next($request);
        }

        // 获取令牌
        $token = $request->header('Authorization');
        if (empty($token)) {
            $token = $request->param('token');
        }

        if (empty($token)) {
            return $this->error('未提供授权令牌', 401);
        }

        // 去除Bearer前缀
        if (strpos($token, 'Bearer ') === 0) {
            $token = substr($token, 7);
        }

        try {
            // 验证令牌
            $authService = new AuthService();
            $payload     = $authService->validateToken($token);

            if (empty($payload)) {
                return $this->error('无效的授权令牌', 401);
            }

            // 检查角色权限
            if (!empty($params)) {
                $userRole = $payload['data']->role ?? 'viewer';

                if (!in_array($userRole, $params)) {
                    Log::warning('用户权限不足', [
                        'user_id'        => $payload['data']->id ?? 0,
                        'username'       => $payload['data']->username ?? '',
                        'required_roles' => $params,
                        'user_role'      => $userRole,
                        'path'           => $request->url()
                    ]);
                    return $this->error('权限不足，需要 ' . implode(' 或 ', $params) . ' 角色', 403);
                }
            }

            // 将用户信息存入请求对象，方便后续使用
            $request->user = $payload['data'];

            return $next($request);
        } catch (\Exception $e) {
            Log::error('JWT认证失败', [
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine()
            ]);
            return $this->error('令牌验证失败: ' . $e->getMessage(), 401);
        }
    }

    /**
     * 返回错误响应
     * 
     * @param string $message 错误消息
     * @param int $code HTTP状态码
     * @return Response
     */
    protected function error(string $message, int $code = 401): Response
    {
        return Response::create([
            'code'    => $code,
            'message' => $message,
            'data'    => null
        ], 'json', $code);
    }
}