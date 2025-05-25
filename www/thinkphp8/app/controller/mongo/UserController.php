<?php

namespace app\controller\mongo;

use app\BaseController;
use app\service\mongo\UserService;
use think\facade\Log;
use think\Request;

class UserController extends BaseController
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Add a new user.
     * Example POST data:
     * {"name": "John Doe", "email": "john.doe@example.com", "status": "active"}
     */
    public function add(Request $request)
    {
        $data = $request->post();
        if (empty($data) || !isset($data['email'])) {
            Log::warning('[MongoUserController] Add: Invalid user data, email is required.');
            return json(['status' => 'error', 'message' => 'User email is required'], 400);
        }

        Log::info('[MongoUserController] Add: Received request to add user. Data: ' . json_encode($data));
        $result = $this->userService->createUser($data);

        if ($result) {
            Log::info('[MongoUserController] Add: User added successfully. ID: ' . $result);
            return json(['status' => 'success', 'message' => 'User added successfully', 'id' => $result]);
        } else {
            Log::error('[MongoUserController] Add: Failed to add user.');
            return json(['status' => 'error', 'message' => 'Failed to add user'], 500);
        }
    }

    /**
     * Get a specific user by ID.
     * @param string $id
     */
    public function get(string $id)
    {
        if (empty($id)) {
            Log::warning('[MongoUserController] Get: Empty user ID.');
            return json(['status' => 'error', 'message' => 'User ID cannot be empty'], 400);
        }
        Log::info('[MongoUserController] Get: Received request to get user by ID: ' . $id);
        $user = $this->userService->getUserById($id);

        if ($user) {
            Log::info('[MongoUserController] Get: User found. ID: ' . $id);
            return json(['status' => 'success', 'data' => $user]);
        } else {
            Log::warning('[MongoUserController] Get: User not found. ID: ' . $id);
            return json(['status' => 'error', 'message' => 'User not found'], 404);
        }
    }

    /**
     * List all users.
     */
    public function list(Request $request)
    {
        $limit = $request->get('limit', 20);
        Log::info('[MongoUserController] List: Received request to list users. Limit: ' . $limit);
        $users = $this->userService->getAllUsers((int)$limit);
        Log::info('[MongoUserController] List: Responding with ' . count($users) . ' users.');
        return json(['status' => 'success', 'data' => $users]);
    }
}
