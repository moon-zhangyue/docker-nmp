<?php

namespace app\service\mongo;

use think\facade\Db;
use think\facade\Log;

class UserService
{
    // This uses the 'mongo' connection, which should be configured for a replica set
    // in config/database.php. The replica set name, read preference (e.g., 'secondaryPreferred'),
    // and write concern (e.g., 'majority') are handled by the driver based on that configuration.
    private $connection = 'mongo';
    private $collection = 'users';

    /**
     * Create a new user.
     * Demonstrates writing to a replica set (primary node by default or as per write concern).
     * @param array $userData e.g., ['name' => 'Jane Doe', 'email' => 'jane.doe@example.com']
     * @return bool|string Inserted ID or false on failure
     */
    public function createUser(array $userData)
    {
        if (empty($userData) || !isset($userData['email'])) {
            Log::warning('[MongoUserService] Attempted to create user with invalid or missing email.');
            return false;
        }

        // Add created_at timestamp
        $userData['created_at'] = new \MongoDB\BSON\UTCDateTime();

        try {
            $insertedId = Db::connect($this->connection)->table($this->collection)->insertGetId($userData);
            if ($insertedId) {
                Log::info('[MongoUserService] User created successfully. ID: ' . $insertedId . ', Data: ' . json_encode($userData));
                return $insertedId;
            } else {
                Log::error('[MongoUserService] Failed to create user. Data: ' . json_encode($userData));
                return false;
            }
        } catch (\Exception $e) {
            Log::error('[MongoUserService] Error creating user: ' . $e->getMessage() . ', Data: ' . json_encode($userData));
            return false;
        }
    }

    /**
     * Get a user by their ID.
     * Demonstrates reading from a replica set (potentially a secondary based on readPreference).
     * @param string $userId
     * @return array|null
     */
    public function getUserById(string $userId): ?array
    {
        try {
            $user = Db::connect($this->connection)->table($this->collection)->where('_id', $userId)->find();
            if ($user) {
                Log::info('[MongoUserService] User found by ID: ' . $userId . ', Data: ' . json_encode($user));
            } else {
                Log::info('[MongoUserService] User not found by ID: ' . $userId);
            }
            return $user;
        } catch (\Exception $e) {
            Log::error('[MongoUserService] Error finding user by ID ' . $userId . ': ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get all users.
     * Demonstrates reading from a replica set.
     * @param int $limit
     * @return array
     */
    public function getAllUsers(int $limit = 20): array
    {
        try {
            $users = Db::connect($this->connection)
                        ->table($this->collection)
                        ->limit($limit)
                        ->order('created_at', 'desc')
                        ->select();
            Log::info('[MongoUserService] Retrieved ' . count($users) . ' users.');
            return $users->all();
        } catch (\Exception $e) {
            Log::error('[MongoUserService] Error retrieving all users: ' . $e->getMessage());
            return [];
        }
    }
}

/*
 * =============================================================================
 *  Conceptual Testing Notes for UserService (Replica Set)
 * =============================================================================
 *
 * **Important Considerations for Replica Sets:**
 * - Unit tests are largely the same as for a standalone instance (mocking DB facade).
 * - Integration tests should be run against a MongoDB replica set to ensure
 *   read preferences (e.g., reading from secondaries) and write concerns (e.g., 'majority')
 *   are behaving as expected. This is usually configured at the connection string level
 *   in `config/database.php` for the 'mongo' connection.
 * - Testing failover scenarios (e.g., primary stepping down) is an advanced integration
 *   test, often done manually or with specialized infrastructure.
 *
 * **Unit Tests:**
 * - Mock `think\facade\Db` and `think\facade\Log`.
 * - Test `createUser()`:
 *   - With valid data: Verify `Db::connect()->table()->insertGetId()` is called.
 *     Ensure `created_at` (as \MongoDB\BSON\UTCDateTime) is added.
 *   - With invalid data (missing 'email'): Verify returns false and logs warning.
 *   - With DB exception: Verify catches, logs, and returns false.
 * - Test `getUserById()`:
 *   - With existing ID: Verify `Db::connect()->table()->where()->find()` is called.
 *   - With non-existing ID: Verify returns null.
 *   - With DB exception: Verify catches, logs, and returns null.
 * - Test `getAllUsers()`:
 *   - Verify `Db::connect()->table()->limit()->order()->select()` is called.
 *   - Mock `select()` to return a mock collection, then `all()` on it.
 *   - With DB exception: Verify catches, logs, and returns an empty array.
 *
 * **Integration Tests (Requires MongoDB Replica Set):**
 * - Configure 'mongo' connection in `config/database.php` for a replica set,
 *   potentially with specific readPreference and writeConcern for testing.
 * - Test `createUser()`:
 *   - Call with sample user data.
 *   - Use `getUserById()` or direct DB query to verify user was written (to primary, propagated to secondaries).
 *   - Verify `created_at` field.
 * - Test `getUserById()`:
 *   - Create a user.
 *   - Call `getUserById()` with the ID. If readPreference is 'secondary' or 'secondaryPreferred',
 *     this read might go to a secondary. Verify correct data is returned.
 * - Test `getAllUsers()`:
 *   - Create a few users.
 *   - Call `getAllUsers()`. This read might also go to a secondary. Verify all users are returned and ordered correctly.
 *
 * **Controller-Level Integration Tests (HTTP requests):**
 * - Test `app\controller\mongo\UserController` actions.
 * - Example:
 *   - POST to `/mongo/user/add` with valid JSON (e.g., `{"name": "Test User", "email": "test@example.com"}`).
 *     Check for 200 status and user ID.
 *   - Add a user, then GET from `/mongo/user/get/{id}`. Check for 200 and user data.
 *   - GET from `/mongo/user/list`. Check for 200 and an array of users.
 *   - POST to `/mongo/user/add` with missing email, check for 400.
 *   - GET from `/mongo/user/get/{non_existent_id}`, check for 404.
 */
