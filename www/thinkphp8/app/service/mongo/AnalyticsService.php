<?php

namespace app\service\mongo;

use think\facade\Db;
use think\facade\Log;

class AnalyticsService
{
    private $connection = 'mongo';
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
            $data = [];
            $productIds = ['prod_A123', 'prod_B456', 'prod_C789', 'prod_D101'];
            $userIds = [1001, 1002, 1003, 1004, 1005];

            for ($i = 0; $i < $count; $i++) {
                $data[] = [
                    'product_id' => $productIds[array_rand($productIds)],
                    'user_id'    => $userIds[array_rand($userIds)],
                    'quantity'   => rand(1, 5),
                    'price'      => (float)rand(10, 200) + (rand(0, 99) / 100), // Price between 10.00 and 200.99
                    'order_date' => new \MongoDB\BSON\UTCDateTime((new \DateTime('-' . rand(0, 30) . ' days'))->getTimestamp() * 1000),
                    'status'     => ['pending', 'completed', 'shipped'][array_rand(['pending', 'completed', 'shipped'])]
                ];
            }
            Db::connect($this->connection)->table($this->ordersCollection)->insertAll($data);
            Log::info('[MongoAnalyticsService] Seeded ' . $count . ' sample orders.');
            return true;
        } catch (\Exception $e) {
            Log::error('[MongoAnalyticsService] Error seeding sample orders: ' . $e->getMessage());
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
                    '$match' => [ // Optional: Filter documents, e.g., only completed orders
                        'status' => 'completed'
                    ]
                ],
                [
                    '$group' => [
                        '_id'          => '$product_id', // Group by product_id
                        'total_quantity_sold' => ['$sum' => '$quantity'],
                        'total_revenue'       => ['$sum' => ['$multiply' => ['$quantity', '$price']]],
                        'order_count'         => ['$sum' => 1]
                    ],
                ],
                [
                    '$sort' => ['total_revenue' => -1], // Sort by total revenue descending
                ],
                [
                    '$project' => [ // Optional: Reshape the output documents
                        '_id' => 0, // Exclude the default _id field from the group stage
                        'product_id' => '$_id', // Rename _id to product_id
                        'total_quantity_sold' => 1,
                        'total_revenue' => ['$round' => ['$total_revenue', 2]], // Round revenue to 2 decimal places
                        'average_revenue_per_order' => [
                            '$cond' => [
                                'if' => ['$gt' => ['$order_count', 0]],
                                'then' => ['$round' => [['$divide' => ['$total_revenue', '$order_count']], 2]],
                                'else' => 0
                            ]
                        ],
                        'order_count' => 1
                    ]
                ]
            ];

            $results = Db::connect($this->connection)
                           ->table($this->ordersCollection)
                           ->aggregate($pipeline);

            Log::info('[MongoAnalyticsService] Product sales analytics generated successfully. Result count: ' . count($results));
            return $results; // This will be an array of documents
        } catch (\Exception $e) {
            Log::error('[MongoAnalyticsService] Error performing product sales aggregation: ' . $e->getMessage());
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
 *   - Verify `Db::connect()->table()->aggregate()` is called with the correct pipeline structure.
 *     Key stages to check in pipeline: `$match`, `$group` (correct fields and accumulators), `$sort`, `$project`.
 *   - Mock `aggregate()` to return a sample result set (array of documents).
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
