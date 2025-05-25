<?php

namespace app\service\mongo;

use think\facade\Db;
use think\facade\Log;

class LogService
{
    // Ensure this connection name 'mongo_sharded' exists in your config/database.php
    // and is configured to connect to your MongoDB sharded cluster (mongos instances).
    private $connection = 'mongo_sharded';
    private $collection = 'application_logs'; // Example collection for logs

    /**
     * Add a log entry.
     * This method demonstrates writing to a potentially sharded MongoDB collection.
     * The sharding strategy itself (e.g., shard key) is defined at the MongoDB level.
     *
     * @param array $logData Data to be logged (e.g., ['level' => 'info', 'message' => 'User logged in', 'context' => []])
     * @return bool|string Inserted ID or false on failure
     */
    public function addLog(array $logData)
    {
        if (empty($logData) || !isset($logData['message'])) {
            Log::warning('[MongoLogService] Attempted to add empty or invalid log data.');
            return false;
        }

        // Add a timestamp if not present
        if (!isset($logData['timestamp'])) {
            $logData['timestamp'] = date('Y-m-d H:i:s');
        }

        try {
            // The 'mongo_sharded' connection should point to mongos router(s)
            $insertedId = Db::connect($this->connection)
                            ->table($this->collection)
                            ->insertGetId($logData);

            if ($insertedId) {
                // Logging to the standard logger, not this service itself to avoid loops
                Log::info('[MongoLogService] Log entry added to sharded collection. ID: ' . $insertedId);
                return $insertedId;
            } else {
                Log::error('[MongoLogService] Failed to add log entry to sharded collection. Data: ' . json_encode($logData));
                return false;
            }
        } catch (\Exception $e) {
            Log::error('[MongoLogService] Error adding log entry to sharded collection: ' . $e->getMessage() . ', Data: ' . json_encode($logData));
            return false;
        }
    }

    /**
     * Example: Retrieve recent logs.
     * Note: Querying sharded environments efficiently depends on the shard key.
     * Avoid scatter-gather queries if possible for performance-sensitive applications.
     *
     * @param int $limit Number of logs to retrieve
     * @return array
     */
    public function getRecentLogs(int $limit = 20): array
    {
        try {
            $logs = Db::connect($this->connection)
                        ->table($this->collection)
                        ->order('timestamp', 'desc')
                        ->limit($limit)
                        ->select();
            Log::info('[MongoLogService] Retrieved ' . count($logs) . ' recent logs from sharded collection.');
            return $logs->all();
        } catch (\Exception $e) {
            Log::error('[MongoLogService] Error retrieving recent logs from sharded collection: ' . $e->getMessage());
            return [];
        }
    }
}

/*
 * =============================================================================
 *  Conceptual Testing Notes for LogService (Sharded Environment)
 * =============================================================================
 *
 * **Important Considerations for Sharded Environments:**
 * - Unit tests remain similar, mocking the DB facade.
 * - Integration tests are more complex as they should ideally verify behavior against
 *   a sharded MongoDB setup. This might be simplified by testing against a single mongos
 *   instance connected to a sharded cluster, or a non-sharded instance if full sharding
 *   tests are too complex for the CI environment.
 * - Performance testing of queries, especially those that might cause scatter-gather,
 *   becomes more critical in a sharded environment.
 *
 * **Unit Tests:**
 * - Mock `think\facade\Db` and `think\facade\Log` (for verifying internal logging).
 * - Test `addLog()`:
 *   - With valid data: Verify `Db::connect('mongo_sharded')->table()->insertGetId()` is called.
 *     Ensure 'timestamp' is added if not present.
 *   - With empty/invalid data (e.g., missing 'message'): Verify it returns false and logs a warning.
 *   - With DB exception: Verify it catches, logs an error, and returns false.
 * - Test `getRecentLogs()`:
 *   - Verify `Db::connect('mongo_sharded')->table()->order()->limit()->select()` is called.
 *   - Mock `select()` to return a mock collection, then mock `all()` on it.
 *   - With DB exception: Verify it catches, logs, and returns an empty array.
 *
 * **Integration Tests (Potentially complex due to sharding):**
 * - Configure the 'mongo_sharded' connection to a test MongoDB (ideally sharded, or a standalone for basic tests).
 * - Test `addLog()`:
 *   - Call with sample log data.
 *   - Query the 'application_logs' collection directly (or use `getRecentLogs`) to verify insertion.
 *     If sharded, verifying which shard the data lands on is an advanced test.
 * - Test `getRecentLogs()`:
 *   - Add several log entries with varying timestamps.
 *   - Call `getRecentLogs()` and verify:
 *     - The correct number of logs are returned (respecting limit).
 *     - Logs are ordered by timestamp descending.
 *   - If testing against a sharded cluster, consider if the query is shard-key targeted or scatter-gather.
 *
 * **Controller-Level Integration Tests (HTTP requests):**
 * - Test `app\controller\mongo\LogController` actions.
 * - Example:
 *   - POST to `/mongo/log/add` with valid JSON log data (e.g., `{"message": "Test error log", "level": "error"}`).
 *     Check for 200 status and log ID in response.
 *   - Add some logs, then GET from `/mongo/log/recent?limit=5`. Check for 200 status and an array of log entries.
 *   - POST to `/mongo/log/add` with invalid data (e.g., empty JSON), check for 400 status.
 */
