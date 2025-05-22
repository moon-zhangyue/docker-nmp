<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\service\AnalyticsService;
use think\facade\Log;
use think\Response;
use think\exception\ValidateException;

class AnalyticsController extends BaseController
{
    protected $analyticsService;
    
    public function __construct(AnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }
    
    /**
     * 记录用户行为
     * 
     * @return Response
     */
    public function record(): Response
    {
        try {
            // 获取POST数据
            $data = $this->request->post();
            
            // 保存用户行为
            $result = $this->analyticsService->recordUserAction($data);
            
            return json(['code' => 200, 'message' => $result ? '记录成功' : '记录失败']);
        } catch (ValidateException $e) {
            return json(['code' => 400, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('记录用户行为接口异常', ['message' => $e->getMessage()]);
            return json(['code' => 500, 'message' => '服务器错误：' . $e->getMessage()]);
        }
    }
    
    /**
     * 按时间段统计用户行为
     * 
     * @return Response
     */
    public function timeStats(): Response
    {
        try {
            // 获取请求参数
            $actionType = $this->request->param('action_type', '');
            $startTime = $this->request->param('start_time', '');
            $endTime = $this->request->param('end_time', '');
            $timeUnit = $this->request->param('time_unit', 'day');
            
            // 参数验证
            if (empty($actionType)) {
                return json(['code' => 400, 'message' => '行为类型不能为空']);
            }
            
            // 查询统计数据
            $data = $this->analyticsService->getActionStatsByTime(
                $actionType,
                $startTime,
                $endTime,
                $timeUnit
            );
            
            return json(['code' => 200, 'message' => '查询成功', 'data' => $data]);
        } catch (\Exception $e) {
            Log::error('按时间段统计用户行为接口异常', ['message' => $e->getMessage()]);
            return json(['code' => 500, 'message' => '服务器错误：' . $e->getMessage()]);
        }
    }
    
    /**
     * 获取活跃用户排行
     * 
     * @return Response
     */
    public function activeUsers(): Response
    {
        try {
            // 获取请求参数
            $actionType = $this->request->param('action_type', '');
            $startTime = $this->request->param('start_time', '');
            $endTime = $this->request->param('end_time', '');
            $limit = intval($this->request->param('limit', 10));
            
            // 参数验证
            if (empty($actionType)) {
                return json(['code' => 400, 'message' => '行为类型不能为空']);
            }
            
            // 查询活跃用户
            $data = $this->analyticsService->getActiveUsers(
                $actionType,
                $startTime,
                $endTime,
                $limit
            );
            
            return json(['code' => 200, 'message' => '查询成功', 'data' => $data]);
        } catch (\Exception $e) {
            Log::error('获取活跃用户排行接口异常', ['message' => $e->getMessage()]);
            return json(['code' => 500, 'message' => '服务器错误：' . $e->getMessage()]);
        }
    }
    
    /**
     * 获取行为类型占比
     * 
     * @return Response
     */
    public function typeDistribution(): Response
    {
        try {
            // 获取请求参数
            $startTime = $this->request->param('start_time', '');
            $endTime = $this->request->param('end_time', '');
            
            // 查询行为类型占比
            $data = $this->analyticsService->getActionTypeDistribution(
                $startTime,
                $endTime
            );
            
            return json(['code' => 200, 'message' => '查询成功', 'data' => $data]);
        } catch (\Exception $e) {
            Log::error('获取行为类型占比接口异常', ['message' => $e->getMessage()]);
            return json(['code' => 500, 'message' => '服务器错误：' . $e->getMessage()]);
        }
    }
    
    /**
     * 获取用户行为路径
     * 
     * @param string $userId 用户ID
     * @return Response
     */
    public function userPath(string $userId): Response
    {
        try {
            // 参数验证
            if (empty($userId)) {
                return json(['code' => 400, 'message' => '用户ID不能为空']);
            }
            
            // 获取请求参数
            $startTime = $this->request->param('start_time', '');
            $endTime = $this->request->param('end_time', '');
            $limit = intval($this->request->param('limit', 10));
            
            // 查询用户行为路径
            $data = $this->analyticsService->getUserActionPath(
                $userId,
                $startTime,
                $endTime,
                $limit
            );
            
            return json(['code' => 200, 'message' => '查询成功', 'data' => $data]);
        } catch (\Exception $e) {
            Log::error('获取用户行为路径接口异常', ['user_id' => $userId, 'message' => $e->getMessage()]);
            return json(['code' => 500, 'message' => '服务器错误：' . $e->getMessage()]);
        }
    }
} 