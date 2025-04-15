<?php

namespace app\middleware;

use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;

/**
 * 用户认证中间件
 * 
 * 用于验证用户是否已登录，保护需要登录才能访问的接口
 */
class AuthCheck implements MiddlewareInterface
{
    /**
     * 不需要登录验证的路由
     * 
     * @var array
     */
    protected $except = [
        '/user/register',
        '/user/login',
    ];

    /**
     * 处理请求
     *
     * @param Request $request
     * @param callable $handler 下一个中间件
     * @return Response
     */
    public function process(Request $request, callable $handler): Response
    {
        // 获取当前路径
        $path = $request->path();

        // 如果是不需要登录验证的路由，直接通过
        if (in_array($path, $this->except)) {
            return $handler($request);
        }

        // 检查用户是否已登录
        $userId = $request->session()->get('user_id');
        if (!$userId) {
            // 未登录，返回401错误
            return json([
                'code' => 401,
                'msg'  => '未登录或登录已过期',
            ]);
        }

        // 已登录，继续处理请求
        return $handler($request);
    }
}