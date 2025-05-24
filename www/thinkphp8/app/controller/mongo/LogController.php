<?php

namespace app\controller\mongo;

use app\BaseController;
use app\service\mongo\LogService;
use think\facade\Log;
use think\Request;

class LogController extends BaseController
{
    protected $logService;

    public function __construct(LogService $logService)
    {
        $this->logService = $logService;
    }

    /**
     * Add a log entry to the sharded collection.
     * Example POST data:
     * {"level": "error", "message": "Payment gateway timeout", "context": {"order_id": 12345}}
     * {"message": "User viewed product page", "context": {"product_id": "abc"}}
     */
    public function add(Request $request)
    {
        $data = $request->post();
        if (empty($data) || !isset($data['message'])) {
            Log::warning('[MongoLogController] Add: Empty or invalid log data in request.');
            return json(['status' => 'error', 'message' => 'Log message is required'], 400);
        }

        // Automatically add a level if not provided for this example
        if (!isset($data['level'])) {
            $data['level'] = 'info';
        }

        Log::info('[MongoLogController] Add: Received request to add log. Data: ' . json_encode($data));
        $result = $this->logService->addLog($data);

        if ($result) {
            Log::info('[MongoLogController] Add: Log entry added successfully to sharded collection. ID: ' . $result);
            return json(['status' => 'success', 'message' => 'Log entry added successfully', 'id' => $result]);
        } else {
            Log::error('[MongoLogController] Add: Failed to add log entry to sharded collection.');
            return json(['status' => 'error', 'message' => 'Failed to add log entry'], 500);
        }
    }

    /**
     * Get recent logs from the sharded collection.
     */
    public function recent(Request $request)
    {
        $limit = $request->get('limit', 20);
        Log::info('[MongoLogController] Recent: Received request to list recent logs from sharded collection. Limit: ' . $limit);
        $logs = $this->logService->getRecentLogs((int)$limit);
        Log::info('[MongoLogController] Recent: Responding with ' . count($logs) . ' logs.');
        return json(['status' => 'success', 'data' => $logs]);
    }
}
