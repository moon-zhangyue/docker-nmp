<?php

namespace app\middleware;

use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;

/**
 * 用户认证中间件
 *
 * 只挂载在 config/route.php 中「需要登录」的路由组上，不再作为全局中间件。
 * 因此能执行到这里就说明该接口必须登录，无需再维护路径白名单。
 * 注意：默认所有路由都是公开的，新增敏感接口必须显式挂本中间件。
 */
class AuthCheck implements MiddlewareInterface
{
    /**
     * 处理请求
     *
     * @param Request $request
     * @param callable $handler 下一个中间件
     * @return Response
     */
    public function process(Request $request, callable $handler): Response
    {
        $userId = $request->session()->get('user_id');
        if (!$userId) {
            // 未登录，返回业务 401（HTTP 状态仍为 200，与原有行为一致）
            return json([
                'code' => 401,
                'msg'  => '未登录或登录已过期',
            ]);
        }

        return $handler($request);
    }
}
