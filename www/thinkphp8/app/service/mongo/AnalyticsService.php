<?php

namespace app\service\mongo;

use think\facade\Log;
use think\facade\Db;

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
            Log::info('[MongoAnalyticsService] Seeded ' . $count . ' sample orders.');
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

            // 使用自定义的mongoAggregate方法进行聚合查询
            $results = $this->mongoAggregate($pipeline);

            Log::info('[MongoAnalyticsService] Product sales analytics generated successfully. Result count: ' . count($results));
            return $results; // This will be an array of documents
        } catch (\Exception $e) {
            Log::error('[MongoAnalyticsService] Error performing product sales aggregation: {message}', ['message' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * 执行MongoDB聚合查询
     * 使用MongoDB原生客户端进行聚合查询
     *
     * @param array $pipeline 聚合管道
     * @return array
     */
    protected function mongoAggregate(array $pipeline): array
    {
        try {
            // 获取MongoDB配置
            $mongoConfig = config('database.connections.mongo');

            // 构建MongoDB连接URI
            $uri = $mongoConfig['dsn'] ?? sprintf(
                'mongodb://%s:%s@%s/%s?authSource=admin',
                $mongoConfig['username'],
                $mongoConfig['password'],
                $mongoConfig['hostname'],
                $mongoConfig['database']
            );

            // 创建MongoDB原生客户端
            $client     = new \MongoDB\Client($uri);
            $database   = $client->selectDatabase($mongoConfig['database']);
            $collection = $database->selectCollection($this->ordersCollection);

            // 执行聚合查询
            $result = $collection->aggregate($pipeline)->toArray();

            return $result;
        } catch (\Exception $e) {
            Log::error('[MongoAnalyticsService] MongoDB聚合查询失败: {message}', [
                'message'  => $e->getMessage(),
                'pipeline' => json_encode($pipeline)
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
