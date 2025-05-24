<?php

namespace app\controller\mongo;

use app\BaseController;
use app\service\mongo\AnalyticsService;
use think\facade\Log;
use think\Request;

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
        Log::info('[MongoAnalyticsController] SeedOrders: Received request to seed ' . $count . ' orders.');
        $success = $this->analyticsService->seedSampleOrders((int)$count);

        if ($success) {
            Log::info('[MongoAnalyticsController] SeedOrders: Sample orders seeded successfully.');
            return json(['status' => 'success', 'message' => $count . ' sample orders seeded successfully.']);
        } else {
            Log::error('[MongoAnalyticsController] SeedOrders: Failed to seed sample orders.');
            return json(['status' => 'error', 'message' => 'Failed to seed sample orders.'], 500);
        }
    }

    /**
     * Get product sales analytics using the aggregation framework.
     * GET /mongo/analytics/product-sales
     */
    public function productSales()
    {
        Log::info('[MongoAnalyticsController] ProductSales: Received request for product sales analytics.');
        $analyticsData = $this->analyticsService->getProductSalesAnalytics();

        if (!empty($analyticsData)) {
            Log::info('[MongoAnalyticsController] ProductSales: Analytics data retrieved. Count: ' . count($analyticsData));
            return json(['status' => 'success', 'data' => $analyticsData]);
        } else {
            // It might be empty if no data matches or due to an error logged by the service
            Log::info('[MongoAnalyticsController] ProductSales: No analytics data returned or an error occurred.');
            return json(['status' => 'success', 'message' => 'No analytics data available or an error occurred during aggregation.', 'data' => []]);
        }
    }
}
