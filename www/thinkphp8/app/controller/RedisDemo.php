<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\facade\Redis;
use think\facade\Cache;
use think\facade\View;
use think\facade\Db;
use app\model\User;
use think\Response;

/**
 * Redis演示控制器
 * 
 * 演示Redis各种数据类型的使用场景和防穿透、防雪崩等机制
 */
class RedisDemo extends BaseController
{
    /**
     * 首页
     */
    public function index()
    {
        return View::fetch('redis/index');
    }

    /**
     * 检查Redis连接
     */
    public function checkConnection()
    {
        try {
            $redis = Redis::getRedis();
            $pong = $redis->ping();
            
            return json([
                'code' => 0,
                'msg' => '连接成功',
                'data' => [
                    'pong' => $pong,
                    'info' => $redis->info(),
                ]
            ]);
        } catch (\Throwable $e) {
            return json([
                'code' => 1,
                'msg' => '连接失败：' . $e->getMessage(),
            ]);
        }
    }
    
    /**
     * 清空当前库
     */
    public function flushDB()
    {
        try {
            $redis = Redis::getRedis();
            $result = $redis->flushDB();
            
            return json([
                'code' => 0,
                'msg' => '清空成功',
                'data' => [
                    'result' => $result,
                ]
            ]);
        } catch (\Throwable $e) {
            return json([
                'code' => 1,
                'msg' => '清空失败：' . $e->getMessage(),
            ]);
        }
    }
    
    /**
     * 重写success方法，用于API响应
     *
     * @param string $message 消息
     * @param array $data 数据
     * @return Response
     */
    public function success(string $message = 'success', array $data = []): Response
    {
        if (func_num_args() > 0 && is_array(func_get_arg(0)) && func_num_args() == 1) {
            // 兼容旧的调用方式：success(array $data)
            $data = func_get_arg(0);
        }
        
        return json([
            'code' => 0,
            'msg' => $message,
            'data' => $data
        ]);
    }
    
    /**
     * 重写error方法，用于API响应
     *
     * @param string $message 错误消息
     * @param int $code 错误码
     * @param array $data 数据
     * @return Response
     */
    public function error(string $message = 'error', int $code = 400, array $data = []): Response
    {
        return json([
            'code' => $code,
            'msg' => $message,
            'data' => $data
        ]);
    }
} 