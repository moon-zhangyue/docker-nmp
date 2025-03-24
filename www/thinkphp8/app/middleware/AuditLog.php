<?php
declare(strict_types=1);

namespace app\middleware;

use think\facade\Log;
use think\Response;
use Jenssegers\Agent\Agent;

/**
 * 操作审计日志中间件
 */
class AuditLog
{
    /**
     * 需要记录的操作类型
     * 
     * @var array
     */
    protected $logMethods = ['POST', 'PUT', 'DELETE'];
    
    /**
     * 不记录审计日志的路径
     * 
     * @var array
     */
    protected $excludePaths = [
        '/api/auth/login',  // 登录页面不记录，在Auth控制器中单独记录
        '/api/auth/refresh',  // 刷新令牌不记录
        '/api/monitoring/health',  // 健康检查不记录
    ];
    
    /**
     * 敏感字段，在审计日志中会被过滤
     * 
     * @var array
     */
    protected $sensitiveFields = [
        'password', 'password_confirm', 'old_password', 'token', 'secret', 'private_key'
    ];

    /**
     * 处理请求
     * 
     * @param \think\Request $request
     * @param \Closure $next
     * @return Response
     */
    public function handle($request, \Closure $next)
    {
        // 获取请求路径和方法
        $path = $request->url();
        $method = $request->method(true);
        
        // 获取请求开始时间
        $startTime = microtime(true);
        
        // 处理请求
        $response = $next($request);
        
        // 计算请求执行时间
        $executionTime = microtime(true) - $startTime;
        
        // 判断是否需要记录审计日志
        if ($this->shouldLog($method, $path)) {
            // 获取用户信息
            $user = $request->user ?? ['id' => 0, 'username' => 'anonymous'];
            
            // 获取客户端信息
            $agent = new Agent();
            $agent->setUserAgent($request->header('User-Agent'));
            
            // 获取请求参数（过滤敏感信息）
            $params = $this->filterSensitiveData($request->param());
            
            // 获取响应状态码
            $statusCode = $response->getCode();
            
            // 记录审计日志
            Log::channel('audit')->info('操作审计日志', [
                'user_id' => $user['id'] ?? 0,
                'username' => $user['username'] ?? 'anonymous',
                'path' => $path,
                'method' => $method,
                'params' => $params,
                'ip' => $request->ip(),
                'user_agent' => [
                    'browser' => $agent->browser(),
                    'platform' => $agent->platform(),
                    'device' => $agent->device(),
                    'is_mobile' => $agent->isPhone(),
                ],
                'status_code' => $statusCode,
                'execution_time' => round($executionTime * 1000, 2), // 转换为毫秒
                'timestamp' => date('Y-m-d H:i:s'),
            ]);
        }
        
        return $response;
    }
    
    /**
     * 判断是否需要记录审计日志
     * 
     * @param string $method 请求方法
     * @param string $path 请求路径
     * @return bool
     */
    protected function shouldLog(string $method, string $path): bool
    {
        // 检查请求方法是否需要记录
        if (!in_array($method, $this->logMethods)) {
            return false;
        }
        
        // 检查路径是否在排除列表中
        foreach ($this->excludePaths as $excludePath) {
            if (strpos($path, $excludePath) !== false) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * 过滤请求参数中的敏感信息
     * 
     * @param array $data 请求参数
     * @return array 过滤后的数据
     */
    protected function filterSensitiveData(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array($key, $this->sensitiveFields)) {
                $data[$key] = '******';
            } elseif (is_array($value)) {
                $data[$key] = $this->filterSensitiveData($value);
            }
        }
        
        return $data;
    }
} 