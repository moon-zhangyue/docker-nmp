<?php
declare(strict_types=1);

namespace app\controller\mongo;

use app\BaseController;
use app\service\mongo\AnalyticsService;
use think\facade\Log;
use think\Request;
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
     * Seeds sample order data for demonstration purposes.
     * GET /mongo/analytics/seed-orders?count=100
     */
    public function seedOrders(Request $request)
    {
        $count = $request->get('count', 50);
        Log::info('[MongoAnalyticsController] SeedOrders: 接收到创建样本订单请求，数量: {count}', ['count' => $count]);
        $success = $this->analyticsService->seedSampleOrders((int) $count);

        if ($success) {
            Log::info('[MongoAnalyticsController] SeedOrders: 样本订单创建成功');
            return json(['status' => 'success', 'message' => $count . ' 条样本订单创建成功']);
        } else {
            Log::error('[MongoAnalyticsController] SeedOrders: 样本订单创建失败');
            return json(['status' => 'error', 'message' => '样本订单创建失败'], 500);
        }
    }

    /**
     * Get product sales analytics using the aggregation framework.
     * GET /mongo/analytics/product-sales
     */
    public function productSales()
    {
        Log::info('[MongoAnalyticsController] ProductSales: 接收到产品销售分析请求');
        $analyticsData = $this->analyticsService->getProductSalesAnalytics();

        if (!empty($analyticsData)) {
            Log::info('[MongoAnalyticsController] ProductSales: 分析数据获取成功，数量: {count}', ['count' => count($analyticsData)]);
            return json(['status' => 'success', 'data' => $analyticsData]);
        } else {
            // It might be empty if no data matches or due to an error logged by the service
            Log::info('[MongoAnalyticsController] ProductSales: 无分析数据返回或发生错误');
            return json(['status' => 'success', 'message' => 'No analytics data available or an error occurred during aggregation.', 'data' => []]);
        }
    }

    /**
     * 记录用户行为
     *
     * @return Response
     */
    public function record(Request $request): Response
    {
        try {
            // 获取POST数据
            $data = $request->post();

            // 保存用户行为
            $result = $this->analyticsService->recordUserAction($data);

            return json(['code' => 200, 'message' => $result ? '记录成功' : '记录失败']);
        } catch (ValidateException $e) {
            return json(['code' => 400, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('记录用户行为接口异常: {message}', ['message' => $e->getMessage()]);
            return json(['code' => 500, 'message' => '服务器错误']);
        }
    }

    /**
     * 按时间段统计用户行为
     *
     * @return Response
     */
    public function timeStats(Request $request): Response
    {
        try {
            // 获取请求参数
            $actionType = $request->param('action_type', '');
            $startTime  = $request->param('start_time', '');
            $endTime    = $request->param('end_time', '');
            $timeUnit   = $request->param('time_unit', 'day');

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
            Log::error('按时间段统计用户行为接口异常: {message}', ['message' => json_encode($e->getMessage(), JSON_UNESCAPED_UNICODE)]);
            return json(['code' => 500, 'message' => '服务器错误']);
        }
    }

    /**
     * 获取活跃用户排行
     *
     * @return Response
     */
    public function activeUsers(Request $request): Response
    {
        try {
            // 获取请求参数
            $actionType = $request->param('action_type', '');
            $startTime  = $request->param('start_time', '');
            $endTime    = $request->param('end_time', '');
            $limit      = intval($request->param('limit', 10));

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
            Log::error('获取活跃用户排行接口异常: {message}', ['message' => $e->getMessage()]);
            return json(['code' => 500, 'message' => '服务器错误']);
        }
    }

    /**
     * 获取行为类型占比
     *
     * @return Response
     */
    public function typeDistribution(Request $request): Response
    {
        try {
            // 获取请求参数
            $startTime = $request->param('start_time', '');
            $endTime   = $request->param('end_time', '');

            // 查询行为类型占比
            $data = $this->analyticsService->getActionTypeDistribution(
                $startTime,
                $endTime
            );

            return json(['code' => 200, 'message' => '查询成功', 'data' => $data]);
        } catch (\Exception $e) {
            Log::error('获取行为类型占比接口异常: {message}', ['message' => $e->getMessage()]);
            return json(['code' => 500, 'message' => '服务器错误']);
        }
    }

    /**
     * 获取用户行为路径
     *
     * @param string $userId 用户ID
     * @return Response
     */
    public function userPath(Request $request): Response
    {
        try {
            // 获取请求参数
            $userId    = $request->param('user_id', '');
            $startTime = $request->param('start_time', '');
            $endTime   = $request->param('end_time', '');
            $limit     = intval($request->param('limit', 10));

            // 参数验证
            if (empty($userId)) {
                return json(['code' => 400, 'message' => '用户ID不能为空']);
            }

            // 查询用户行为路径
            $data = $this->analyticsService->getUserActionPath(
                $userId,
                $startTime,
                $endTime,
                $limit
            );

            return json(['code' => 200, 'message' => '查询成功', 'data' => $data]);
        } catch (\Exception $e) {
            Log::error('获取用户行为路径接口异常: 用户ID {user_id}, 错误信息 {message}', ['user_id' => $userId, 'message' => $e->getMessage()]);
            return json(['code' => 500, 'message' => '服务器错误']);
        }
    }
}
