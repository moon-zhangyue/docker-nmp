<?php
declare(strict_types=1);

namespace app\middleware;

use think\Response;

/**
 * 跨域请求处理中间件
 */
class Cors
{
    /**
     * 处理跨域请求
     * 
     * @param \think\Request $request
     * @param \Closure $next
     * @return Response
     */
    public function handle($request, \Closure $next)
    {
        header('Access-Control-Allow-Origin: http://localhost:3000'); // 前端域名
        header('Access-Control-Allow-Headers: Authorization, Content-Type, If-Match, If-Modified-Since, If-None-Match, If-Unmodified-Since, X-CSRF-TOKEN, X-Requested-With');
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE');
        header('Access-Control-Allow-Credentials: true');
        
        if (strtoupper($request->method()) == "OPTIONS") {
            return response()->send();
        }
        
        return $next($request);
    }
} 