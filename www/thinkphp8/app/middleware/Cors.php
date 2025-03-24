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
        $origin = $request->header('Origin');
        $allowOrigin = '*';
        
        // 可以在此处根据配置文件检查是否允许特定域名跨域访问
        // $allowedOrigins = config('cors.allowed_origins', ['*']);
        // if (in_array($origin, $allowedOrigins) || in_array('*', $allowedOrigins)) {
        //     $allowOrigin = $origin;
        // }
        
        // 如果是OPTIONS请求，直接返回200状态码
        if ($request->method(true) == 'OPTIONS') {
            $response = Response::create('', 'html', 200);
        } else {
            $response = $next($request);
        }

        // 设置跨域响应头
        return $response
            ->header([
                'Access-Control-Allow-Origin' => $allowOrigin,
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' => 'Authorization, Content-Type, Accept, Origin, X-Requested-With, X-Auth-Token',
                'Access-Control-Allow-Credentials' => 'true',
                'Access-Control-Max-Age' => '86400',
            ]);
    }
} 