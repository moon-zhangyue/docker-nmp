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
     * @param array|string $roles 允许的角色，如 'admin' 或 ['admin', 'operator']
     * @return Response
     */
    public function handle($request, \Closure $next, $roles = null)
    {
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
            $user = $authService->validateToken($token);
            
            if (empty($user)) {
                return $this->error('无效的授权令牌', 401);
            }

            // 检查角色权限
            if (!empty($roles)) {
                $roles = is_array($roles) ? $roles : explode(',', $roles);
                $userRole = $user['role'] ?? 'viewer';
                
                if (!in_array($userRole, $roles)) {
                    Log::warning('用户权限不足', [
                        'user_id' => $user['id'] ?? 0,
                        'username' => $user['username'] ?? '',
                        'required_roles' => $roles,
                        'user_role' => $userRole,
                        'path' => $request->url()
                    ]);
                    return $this->error('权限不足，需要 ' . implode(' 或 ', $roles) . ' 角色', 403);
                }
            }

            // 将用户信息存入请求对象，方便后续使用
            $request->user = $user;
            
            return $next($request);
        } catch (\Exception $e) {
            Log::error('JWT认证失败', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
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
            'code' => $code,
            'message' => $message,
            'data' => null
        ], 'json', $code);
    }
} 