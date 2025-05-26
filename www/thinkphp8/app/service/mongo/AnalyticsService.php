<?php
declare(strict_types=1);

namespace app\service\mongo;

use think\facade\Log;
use think\facade\Db;
use app\model\Analytics;
use think\facade\Cache;

class AnalyticsService
{
    private $connection       = 'mongo';
    private $ordersCollection = 'orders'; // Example collection for aggregation

    /**
     * Seeds the orders collection with sample data for testing aggregation.
     * This is a helper method and might not be suitable for production use directly.
     * @param int $count Number of sample orders to create
     * @return bool Success or failure
     */
    public function seedSampleOrders(int $count = 50): bool
    {
        try {
            $data       = [];
            $productIds = ['prod_A123', 'prod_B456', 'prod_C789', 'prod_D101'];
            $userIds    = [1001, 1002, 1003, 1004, 1005];

            for ($i = 0; $i < $count; $i++) {
                $data[] = [
                    'product_id' => $productIds[array_rand($productIds)],
                    'user_id'    => $userIds[array_rand($userIds)],
                    'quantity'   => rand(1, 5),
                    'price'      => (float) rand(10, 200) + rand(0, 99) / 100, // Price between 10.00 and 200.99
                    'order_date' => (new \DateTime('-' . rand(0, 30) . ' days'))->getTimestamp(),
                    'status'     => ['pending', 'completed', 'shipped'][array_rand(['pending', 'completed', 'shipped'])]
                ];
            }


            Db::connect($this->connection)->table($this->ordersCollection)->insertAll($data);
            Log::info('[MongoAnalyticsService] 成功创建样本订单数据: {count} 条', ['count' => $count]);
            return true;
        } catch (\Exception $e) {
            Log::error('[MongoAnalyticsService] Error seeding sample orders: {message}', ['message' => $e->getMessage()]);
            return false;
        }
    }


    /**
     * Calculates total sales amount and quantity per product using the aggregation framework.
     * Assumes 'orders' collection has 'product_id', 'quantity', and 'price' fields.
     * @return array Aggregation results or empty array on failure.
     */
    public function getProductSalesAnalytics(): array
    {
        try {
            $pipeline = [
                [
                    '$match' => [
                        'status' => 'completed'// 匹配状态为completed的文档
                    ]
                ],
                [
                    '$group' => [
                        '_id'                 => '$product_id', // Group by product_id
                        'total_quantity_sold' => ['$sum' => '$quantity'],
                        'total_revenue'       => ['$sum' => ['$multiply' => ['$quantity', '$price']]],
                        'order_count'         => ['$sum' => 1]
                    ],
                ],
                [
                    '$sort' => ['total_revenue' => -1], // 按照total_revenue字段降序排序
                ],
                [
                    '$project' => [
                        '_id'                       => 0, // 不显示_id字段
                        'product_id'                => '$_id', // 将_id字段重命名为product_id
                        'total_quantity_sold'       => 1, // 显示total_quantity_sold字段
                        'total_revenue'             => ['$round' => ['$total_revenue', 2]], // 将total_revenue字段四舍五入到小数点后两位
                        // 计算平均每单收入，如果order_count大于0，则计算total_revenue除以order_count，否则返回0
                        'average_revenue_per_order' => [
                            '$cond' => [
                                'if'   => ['$gt' => ['$order_count', 0]],
                                'then' => ['$round' => [['$divide' => ['$total_revenue', '$order_count']], 2]],
                                'else' => 0
                            ]
                        ],
                        'order_count'               => 1// 显示order_count字段
                    ]
                ]
            ];

            $results = Analytics::analyzeProductSale($pipeline ,'orders');

            Log::info('[MongoAnalyticsService] 产品销售分析生成成功，结果数量: {count}', ['count' => count($results)]);
            return $results; // This will be an array of documents
        } catch (\Exception $e) {
            Log::error('[MongoAnalyticsService] Error performing product sales aggregation: {message}', ['message' => $e->getMessage()]);
            return [];
        }
    }



    /**
     * 记录用户行为
     *
     * @param array $data 用户行为数据
     * @return bool
     */
    public function recordUserAction(array $data): bool
    {
        try {
            // 验证必要字段
            if (empty($data['user_id']) || empty($data['action_type'])) {
                throw new \Exception('用户ID和行为类型不能为空');
            }

            // 确保有时间戳
            if (!isset($data['create_time'])) {
                $data['create_time'] = date('Y-m-d H:i:s');
            }

            // 记录日志
            Log::info('记录用户行为: {data}', ['data' => json_encode($data, JSON_UNESCAPED_UNICODE)]);

            // 保存用户行为
            return Analytics::saveUserAction($data);
        } catch (\Exception $e) {
            Log::error('记录用户行为失败: {message}, 数据: {data}', ['message' => $e->getMessage(), 'data' => json_encode($data, JSON_UNESCAPED_UNICODE)]);
            return false;
        }
    }

    /**
     * 按时间段统计用户行为
     *
     * @param string $actionType 行为类型
     * @param string $startTime 开始时间，默认7天前
     * @param string $endTime 结束时间，默认当前时间
     * @param string $timeUnit 时间单位(hour/day/week/month)
     * @return array
     */
    public function getActionStatsByTime(string $actionType, string $startTime = '', string $endTime = '', string $timeUnit = 'day'): array
    {
        try {
            // 设置默认时间范围
            if (empty($startTime)) {
                $startTime = date('Y-m-d H:i:s', strtotime('-7 days'));
            }

            if (empty($endTime)) {
                $endTime = date('Y-m-d H:i:s');
            }

            // 缓存键
            $cacheKey = "analytics:time:{$actionType}:{$startTime}:{$endTime}:{$timeUnit}";

            // 优先从缓存获取
            if (Cache::has($cacheKey)) {
                return Cache::get($cacheKey);
            }

            // 记录日志
            Log::info('按时间段统计用户行为: 行为类型 {action_type}, 时间范围 {time_range}, 时间单位 {time_unit}', [
                'action_type' => $actionType,
                'time_range'  => json_encode([$startTime, $endTime]),
                'time_unit'   => $timeUnit
            ]);

            // 查询统计数据
            $result = Analytics::aggregateByTime($actionType, $startTime, $endTime, $timeUnit);

            // 格式化结果
            $formattedResult = [];
            foreach ($result as $item) {
                $formattedResult[] = [
                    'time'  => $item['_id']['time_unit'],
                    'count' => $item['count']
                ];
            }

            // 缓存结果，30分钟过期
            Cache::set($cacheKey, $formattedResult, 1800);

            return $formattedResult;
        } catch (\Exception $e) {
            Log::error('按时间段统计用户行为失败: {message}, 行为类型: {action_type}, 时间范围: {time_range}', [
                'message'     => $e->getMessage(),
                'action_type' => $actionType,
                'time_range'  => json_encode([$startTime, $endTime])
            ]);
            return [];
        }
    }

    /**
     * 获取活跃用户排行
     *
     * @param string $actionType 行为类型
     * @param string $startTime 开始时间，默认7天前
     * @param string $endTime 结束时间，默认当前时间
     * @param int $limit 返回数量限制
     * @return array
     */
    public function getActiveUsers(string $actionType, string $startTime = '', string $endTime = '', int $limit = 10): array
    {
        try {
            // 设置默认时间范围
            if (empty($startTime)) {
                $startTime = date('Y-m-d H:i:s', strtotime('-7 days'));
            }

            if (empty($endTime)) {
                $endTime = date('Y-m-d H:i:s');
            }

            // 缓存键
            $cacheKey = "analytics:users:{$actionType}:{$startTime}:{$endTime}:{$limit}";

            // 优先从缓存获取
            if (Cache::has($cacheKey)) {
                return Cache::get($cacheKey);
            }

            // 记录日志
            Log::info('获取活跃用户排行: 行为类型 {action_type}, 时间范围 {time_range}, 限制数量 {limit}', [
                'action_type' => $actionType,
                'time_range'  => [$startTime, $endTime],
                'limit'       => $limit
            ]);

            // 查询统计数据
            $result = Analytics::aggregateByUser($actionType, $startTime, $endTime, $limit);

            // 格式化结果
            $formattedResult = [];
            foreach ($result as $item) {
                $formattedResult[] = [
                    'user_id'   => $item['_id'],
                    'count'     => $item['count'],
                    'last_time' => date('Y-m-d H:i:s', $item['last_time'])
                ];
            }

            // 缓存结果，10分钟过期
            Cache::set($cacheKey, $formattedResult, 600);

            return $formattedResult;
        } catch (\Exception $e) {
            Log::error('获取活跃用户排行失败: {message}, 行为类型: {action_type}, 时间范围: {time_range}', [
                'message'     => $e->getMessage(),
                'action_type' => $actionType,
                'time_range'  => [$startTime, $endTime]
            ]);
            return [];
        }
    }

    /**
     * 获取行为类型占比
     *
     * @param string $startTime 开始时间，默认7天前
     * @param string $endTime 结束时间，默认当前时间
     * @return array
     */
    public function getActionTypeDistribution(string $startTime = '', string $endTime = ''): array
    {
        try {
            // 设置默认时间范围
            if (empty($startTime)) {
                $startTime = date('Y-m-d H:i:s', strtotime('-7 days'));
            }

            if (empty($endTime)) {
                $endTime = date('Y-m-d H:i:s');
            }

            // 缓存键
            $cacheKey = "analytics:distribution:{$startTime}:{$endTime}";

            // 优先从缓存获取
            if (Cache::has($cacheKey)) {
                return Cache::get($cacheKey);
            }

            // 记录日志
            Log::info('获取行为类型占比: 时间范围 {time_range}', [
                'time_range' => [$startTime, $endTime]
            ]);

            // 查询统计数据
            $result = Analytics::aggregateActionTypes($startTime, $endTime);

            // 格式化结果
            $formattedResult = [];
            foreach ($result as $item) {
                $formattedResult[] = [
                    'action_type' => $item['_id'],
                    'count'       => $item['count'],
                    'percentage'  => $item['percentage']
                ];
            }

            // 缓存结果，1小时过期
            Cache::set($cacheKey, $formattedResult, 3600);

            return $formattedResult;
        } catch (\Exception $e) {
            Log::error('获取行为类型占比失败: {message}, 时间范围: {time_range}', [
                'message'    => $e->getMessage(),
                'time_range' => [$startTime, $endTime]
            ]);
            return [];
        }
    }

    /**
     * 获取用户行为路径
     *
     * @param string $userId 用户ID
     * @param string $startTime 开始时间，默认1天前
     * @param string $endTime 结束时间，默认当前时间
     * @param int $limit 路径长度限制
     * @return array
     */
    public function getUserActionPath(
        string $userId,
        string $startTime = '',
        string $endTime = '',
        int $limit = 10
    ): array {
        try {
            // 设置默认时间范围
            if (empty($startTime)) {
                $startTime = date('Y-m-d H:i:s', strtotime('-1 day'));
            }

            if (empty($endTime)) {
                $endTime = date('Y-m-d H:i:s');
            }

            // 缓存键
            $cacheKey = "analytics:path:{$userId}:{$startTime}:{$endTime}:{$limit}";

            // 优先从缓存获取
            if (Cache::has($cacheKey)) {
                return Cache::get($cacheKey);
            }

            // 记录日志
            Log::info('获取用户行为路径: 用户ID {user_id}, 时间范围 {time_range}, 限制数量 {limit}', [
                'user_id'    => $userId,
                'time_range' => [$startTime, $endTime],
                'limit'      => $limit
            ]);

            // 查询数据
            $result = Analytics::analyzeUserPath($userId, $startTime, $endTime, $limit);

            // 缓存结果，5分钟过期
            Cache::set($cacheKey, $result, 300);

            return $result;
        } catch (\Exception $e) {
            Log::error('获取用户行为路径失败: {message}, 用户ID: {user_id}, 时间范围: {time_range}', [
                'message'    => $e->getMessage(),
                'user_id'    => $userId,
                'time_range' => [$startTime, $endTime]
            ]);
            return [];
        }
    }
}

/*
 * =============================================================================
 *  Conceptual Testing Notes for AnalyticsService (Aggregation)
 * =============================================================================
 *
 * **Unit Tests:**
 * - Mock `think\facade\Db` and `think\facade\Log`.
 * - Test `seedSampleOrders()`:
 *   - Verify `Db::connect()->table()->insertAll()` is called with an array of the correct count.
 *   - With DB exception: Verify it catches, logs, and returns false.
 * - Test `getProductSalesAnalytics()`:
 *   - Verify `mongoAggregate()` is called with the correct pipeline structure.
 *     Key stages to check in pipeline: `$match`, `$group` (correct fields and accumulators), `$sort`, `$project`.
 *   - Mock `mongoAggregate()` to return a sample result set (array of documents).
 *   - With DB exception: Verify it catches, logs, and returns an empty array.
 *
 * **Integration Tests (Requires MongoDB with `orders` collection):**
 * - Test `seedSampleOrders()`:
 *   - Call `seedSampleOrders(X)`.
 *   - Query the `orders` collection directly to verify that X documents were inserted.
 *   - Check the structure of a few sample seeded documents.
 * - Test `getProductSalesAnalytics()`:
 *   - First, ensure the `orders` collection has known data (either by seeding or manual insertion).
 *     Include orders with 'completed' status and others, various products, quantities, and prices.
 *   - Call `getProductSalesAnalytics()`.
 *   - Verify the results:
 *     - Correct grouping by `product_id`.
 *     - Accurate calculation of `total_quantity_sold` and `total_revenue`.
 *     - Correct calculation of `average_revenue_per_order`.
 *     - Correct `order_count`.
 *     - Results are sorted by `total_revenue` descending.
 *     - Only 'completed' orders are included in the aggregation.
 *     - Revenue is rounded to 2 decimal places.
 *
 * **Controller-Level Integration Tests (HTTP requests):**
 * - Test `app\controller\mongo\AnalyticsController` actions.
 * - Example:
 *   - GET to `/mongo/analytics/seed-orders?count=10`. Check for 200 status and success message.
 *     Then verify in DB that 10 orders were added.
 *   - (After seeding or ensuring data exists) GET to `/mongo/analytics/product-sales`.
 *     Check for 200 status and an array of analytics data matching expected calculations.
 *   - If no 'completed' orders exist, `/mongo/analytics/product-sales` should return success with empty data or a specific message.
 */
