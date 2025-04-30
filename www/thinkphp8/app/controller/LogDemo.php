<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use think\facade\Log;
use think\Response;

/**
 * Elasticsearch日志演示控制器
 */
class LogDemo extends BaseController
{
    /**
     * 测试基本日志
     * 
     * @return Response
     */
    public function index(): Response
    {
        // 记录不同级别的日志
        Log::info('这是一条信息日志');
        Log::debug('这是一条调试日志');
        Log::notice('这是一条通知日志');
        Log::warning('这是一条警告日志');
        Log::error('这是一条错误日志');
        
        return json([
            'code' => 0,
            'msg' => '日志记录成功',
            'data' => null
        ]);
    }

    /**
     * 测试带上下文的日志
     * 
     * @return Response
     */
    public function withContext(): Response
    {
        // 记录带上下文的日志
        $user = [
            'id' => 1,
            'name' => '测试用户',
            'email' => 'test@example.com'
        ];
        
        Log::info('用户登录成功', $user);
        
        // 记录异常信息
        try {
            throw new \Exception('模拟异常测试');
        } catch (\Exception $e) {
            Log::error('捕获到异常: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
        }
        
        return json([
            'code' => 0,
            'msg' => '带上下文的日志记录成功',
            'data' => null
        ]);
    }

    /**
     * 测试指定通道
     * 
     * @return Response
     */
    public function specificChannel(): Response
    {
        // 仅记录到文件
        Log::channel('file')->info('仅记录到文件的日志');
        
        // 仅记录到Elasticsearch
        Log::channel('elasticsearch')->info('仅记录到Elasticsearch的日志');
        
        // 记录到多个通道
        Log::channel(['file', 'elasticsearch'])->error('同时记录到文件和Elasticsearch的错误');
        
        return json([
            'code' => 0,
            'msg' => '指定通道日志记录成功',
            'data' => null
        ]);
    }

    /**
     * 测试批量日志
     * 
     * @return Response
     */
    public function batch(): Response
    {
        // 模拟批量记录操作日志
        $operations = [
            ['action' => '登录', 'user_id' => 1, 'ip' => '192.168.1.1', 'time' => time()],
            ['action' => '查看用户列表', 'user_id' => 1, 'ip' => '192.168.1.1', 'time' => time() + 30],
            ['action' => '修改用户信息', 'user_id' => 2, 'ip' => '192.168.1.2', 'time' => time() + 60],
            ['action' => '删除用户', 'user_id' => 3, 'ip' => '192.168.1.3', 'time' => time() + 90],
            ['action' => '退出登录', 'user_id' => 1, 'ip' => '192.168.1.1', 'time' => time() + 120],
        ];
        
        foreach ($operations as $op) {
            Log::channel('elasticsearch')->info("用户操作: {$op['action']}", $op);
        }
        
        return json([
            'code' => 0,
            'msg' => '批量日志记录成功',
            'data' => null
        ]);
    }
    
    /**
     * 测试各种错误级别
     * 
     * @return Response
     */
    public function allLevels(): Response
    {
        $message = '这是一条测试日志消息';
        
        Log::emergency($message . ' - EMERGENCY');
        Log::alert($message . ' - ALERT');
        Log::critical($message . ' - CRITICAL');
        Log::error($message . ' - ERROR');
        Log::warning($message . ' - WARNING');
        Log::notice($message . ' - NOTICE');
        Log::info($message . ' - INFO');
        Log::debug($message . ' - DEBUG');
        
        return json([
            'code' => 0,
            'msg' => '所有级别的日志记录成功',
            'data' => null
        ]);
    }
} 