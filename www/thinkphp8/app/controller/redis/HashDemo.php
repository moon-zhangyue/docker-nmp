<?php
declare(strict_types=1);

namespace app\controller\redis;

use app\controller\RedisDemo;
use app\facade\Redis;
use think\facade\Db;
use think\facade\View;
use think\facade\Log;

/**
 * Redis Hash类型演示控制器
 * 
 * 演示Redis Hash类型的常见应用场景
 */
class HashDemo extends RedisDemo
{
    /**
     * 演示页面
     */
    public function index()
    {
        Log::info('访问Redis Hash演示页面');
        return View::fetch('redis/hash/index');
    }

    /**
     * 基本用法示例
     */
    public function basic()
    {
        try {
            Log::info('开始执行Redis Hash基本用法示例');
            $redis = Redis::hash();
            $key   = 'hash_demo_basic';

            // 设置哈希表字段
            $redis->hSet($key, 'field1', 'Value 1');
            $redis->hSet($key, 'field2', 'Value 2');

            // 获取哈希表字段
            $value1 = $redis->hGet($key, 'field1');
            $value2 = $redis->hGet($key, 'field2');

            // 批量设置
            $redis->hMSet($key, [
                'field3' => 'Value 3',
                'field4' => 'Value 4',
            ]);

            // 批量获取
            $values = $redis->hMGet($key, ['field1', 'field2', 'field3', 'field4']);

            // 获取所有字段和值
            $all = $redis->hGetAll($key);

            // 获取所有字段名
            $fields = $redis->hKeys($key);

            // 获取所有字段值
            $fieldValues = $redis->hVals($key);

            // 字段是否存在
            $exists = $redis->hExists($key, 'field1');

            // 获取字段数量
            $count = $redis->hLen($key);

            // 删除一个字段
            $redis->hDel($key, 'field4');
            $afterDelete = $redis->hGetAll($key);

            // 递增操作
            $redis->hSet($key, 'counter', value: 10);
            $incrValue = $redis->hIncrBy($key, 'counter', 5);

            Log::info('Redis Hash基本用法演示完成，键名：{key}，字段数量：{field_count}', ['key' => $key, 'field_count' => $count]);
            return $this->success('Hash基本用法演示', [
                'single_values'    => [
                    'field1' => $value1,
                    'field2' => $value2,
                ],
                'multi_values'     => $values,
                'all_values'       => $all,
                'fields'           => $fields,
                'field_values'     => $fieldValues,
                'field1_exists'    => $exists,
                'field_count'      => $count,
                'after_delete'     => $afterDelete,
                'increment_result' => $incrValue,
            ]);
        } catch (\Throwable $e) {
            Log::error('Redis Hash基本用法演示失败：{error}', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->error('Hash基本用法演示失败：' . $e->getMessage());
        }
    }

    /**
     * 缓存用户信息示例
     */
    public function cacheUser()
    {
        try {
            $userId = $this->request->param('id', 1, 'intval');
            Log::info('开始执行Redis Hash缓存用户信息示例，用户ID：{user_id}', ['user_id' => $userId]);

            $redis = Redis::hash();
            $key   = 'user:profile';

            // 使用remember方法自动防止缓存穿透
            $user = $redis->hRemember($key, "user:{$userId}", function () use ($userId) {
                // 模拟从数据库获取用户信息
                // 注：这里只是演示，实际应用中应该使用User模型查询
                Log::info('从数据库获取用户信息，用户ID：{user_id}', ['user_id' => $userId]);
                $user = Db::name('user')->where('id', $userId)->find();
                return $user;
            }, true);

            if (!$user) {
                Log::info('获取用户信息成功，用户ID：{user_id}，来源：{from_cache}', ['user_id' => $userId, 'from_cache' => '缓存']);
                return $this->success('获取用户信息成功', [
                    'user'       => $user,
                    'from_cache' => true
                ]);
            } else {
                // 查看缓存中的所有用户信息
                $allUsers = $redis->hGetAll($key);
                Log::warning('用户不存在或缓存已过期，用户ID：{user_id}', ['user_id' => $userId]);

                return $this->success('用户不存在', [
                    'message'          => '用户不存在或缓存已过期',
                    'all_cached_users' => $allUsers
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('缓存用户信息示例失败：{error}', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->error('缓存用户信息示例失败：' . $e->getMessage());
        }
    }

    /**
     * 购物车示例
     */
    public function cart()
    {
        try {
            $redis     = Redis::hash();
            $userId    = $this->request->param('user_id', 1, 'intval');
            $productId = $this->request->param('product_id', 0, 'intval');
            $quantity  = $this->request->param('quantity', 0, 'intval');
            $action    = $this->request->param('action', ''); // add, remove, clear, list

            Log::info('购物车操作，用户ID：{user_id}，商品ID：{product_id}，数量：{quantity}，操作：{action}', [
                'user_id'    => $userId,
                'product_id' => $productId,
                'quantity'   => $quantity,
                'action'     => $action
            ]);

            $cartKey = "cart:{$userId}";

            // 执行购物车操作
            switch ($action) {
                case 'add':
                    if ($productId > 0 && $quantity > 0) {
                        // 检查商品是否已在购物车中
                        $currentQty = (int) $redis->hGet($cartKey, (string) $productId);
                        $newQty     = $currentQty + $quantity;
                        $redis->hSet($cartKey, (string) $productId, $newQty);
                        $message = "已添加 {$quantity} 个商品(ID: {$productId})到购物车";
                        Log::info('添加商品到购物车，用户ID：{user_id}，商品ID：{product_id}，数量：{quantity}，新数量：{new_qty}', [
                            'user_id'    => $userId,
                            'product_id' => $productId,
                            'quantity'   => $quantity,
                            'new_qty'    => $newQty
                        ]);
                    } else {
                        $message = "参数错误";
                        Log::warning('添加商品参数错误，用户ID：{user_id}，商品ID：{product_id}，数量：{quantity}', [
                            'user_id'    => $userId,
                            'product_id' => $productId,
                            'quantity'   => $quantity
                        ]);
                    }
                    break;

                case 'update':
                    if ($productId > 0 && $quantity >= 0) {
                        if ($quantity > 0) {
                            $redis->hSet($cartKey, (string) $productId, $quantity);
                            $message = "已更新商品(ID: {$productId})数量为 {$quantity}";
                            Log::info('更新购物车商品数量，用户ID：{user_id}，商品ID：{product_id}，数量：{quantity}', [
                                'user_id'    => $userId,
                                'product_id' => $productId,
                                'quantity'   => $quantity
                            ]);
                        } else {
                            $redis->hDel($cartKey, (string) $productId);
                            $message = "已从购物车移除商品(ID: {$productId})";
                            Log::info('从购物车移除商品(数量为0)，用户ID：{user_id}，商品ID：{product_id}', [
                                'user_id'    => $userId,
                                'product_id' => $productId
                            ]);
                        }
                    } else {
                        $message = "参数错误";
                        Log::warning('更新购物车参数错误，用户ID：{user_id}，商品ID：{product_id}，数量：{quantity}', [
                            'user_id'    => $userId,
                            'product_id' => $productId,
                            'quantity'   => $quantity
                        ]);
                    }
                    break;

                case 'remove':
                    if ($productId > 0) {
                        $redis->hDel($cartKey, (string) $productId);
                        $message = "已从购物车移除商品(ID: {$productId})";
                        Log::info('从购物车移除商品，用户ID：{user_id}，商品ID：{product_id}', [
                            'user_id'    => $userId,
                            'product_id' => $productId
                        ]);
                    } else {
                        $message = "参数错误";
                        Log::warning('移除商品参数错误，用户ID：{user_id}，商品ID：{product_id}', [
                            'user_id'    => $userId,
                            'product_id' => $productId
                        ]);
                    }
                    break;

                case 'clear':
                    // 获取所有商品ID
                    $productIds = $redis->hKeys($cartKey);
                    if (!empty($productIds)) {
                        $redis->hDel($cartKey, $productIds);
                        Log::info('清空购物车，用户ID：{user_id}，商品数量：{item_count}', [
                            'user_id'    => $userId,
                            'item_count' => count($productIds)
                        ]);
                    }
                    $message = "已清空购物车";
                    break;

                default:
                    $message = "查看购物车";
                    Log::info('查看购物车，用户ID：{user_id}', ['user_id' => $userId]);
                    break;
            }

            // 获取购物车内容
            $cartItems     = $redis->hGetAll($cartKey);
            $formattedCart = [];

            if (!empty($cartItems)) {
                foreach ($cartItems as $pid => $qty) {
                    // 在实际应用中，这里可以从数据库或另一个缓存获取商品详情
                    $formattedCart[] = [
                        'product_id'   => $pid,
                        'quantity'     => $qty,
                        // 模拟商品名称和价格
                        'product_name' => "商品 {$pid}",
                        'price'        => round(mt_rand(1000, 10000) / 100, 2),
                    ];
                }
            }

            return $this->success($message, [
                'user_id'          => $userId,
                'cart_items'       => $formattedCart,
                'cart_count'       => count($formattedCart),
                'action_performed' => $action,
            ]);
        } catch (\Throwable $e) {
            Log::error('购物车操作失败：{error}', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->error('购物车操作失败：' . $e->getMessage());
        }
    }

    /**
     * 用户配置管理示例
     */
    public function userSettings()
    {
        try {
            $redis  = Redis::hash();
            $userId = $this->request->param('user_id', 1, 'intval');
            $key    = $this->request->param('key', '');
            $value  = $this->request->param('value', '');
            $action = $this->request->param('action', 'get'); // get, set, delete

            Log::info('用户配置操作，用户ID：{user_id}，键名：{key}，值：{value}，操作：{action}', [
                'user_id' => $userId,
                'key'     => $key,
                'value'   => $value,
                'action'  => $action
            ]);

            $settingsKey = "user:settings:{$userId}";

            // 执行配置操作
            switch ($action) {
                case 'set':
                    if (!empty($key)) {
                        $redis->hSet($settingsKey, $key, $value);
                        $message = "配置项 {$key} 已设置为 {$value}";
                        Log::info('设置用户配置，用户ID：{user_id}，键名：{key}，值：{value}', [
                            'user_id' => $userId,
                            'key'     => $key,
                            'value'   => $value
                        ]);
                    } else {
                        $message = "参数错误";
                        Log::warning('设置用户配置参数错误，用户ID：{user_id}，键名：{key}', [
                            'user_id' => $userId,
                            'key'     => $key
                        ]);
                    }
                    break;

                case 'delete':
                    if (!empty($key)) {
                        $redis->hDel($settingsKey, $key);
                        $message = "配置项 {$key} 已删除";
                        Log::info('删除用户配置，用户ID：{user_id}，键名：{key}', [
                            'user_id' => $userId,
                            'key'     => $key
                        ]);
                    } else {
                        $message = "参数错误";
                        Log::warning('删除用户配置参数错误，用户ID：{user_id}，键名：{key}', [
                            'user_id' => $userId,
                            'key'     => $key
                        ]);
                    }
                    break;

                default: // get
                    if (!empty($key)) {
                        $value   = $redis->hGet($settingsKey, $key);
                        $message = "获取配置项 {$key}";
                        Log::info('获取用户配置项，用户ID：{user_id}，键名：{key}，值：{value}', [
                            'user_id' => $userId,
                            'key'     => $key,
                            'value'   => $value
                        ]);
                    } else {
                        $message = "获取所有配置";
                        Log::info('获取用户所有配置，用户ID：{user_id}', ['user_id' => $userId]);
                    }
                    break;
            }

            // 获取所有配置
            $allSettings = $redis->hGetAll($settingsKey);

            // 如果配置为空，设置一些默认值
            if (empty($allSettings) && $action === 'get') {
                $defaultSettings = [
                    'theme'              => 'light',
                    'language'           => 'zh-CN',
                    'notifications'      => 'on',
                    'email_subscription' => 'off',
                ];
                $redis->hMSet($settingsKey, $defaultSettings);
                $allSettings = $defaultSettings;
                $message     = "已设置默认配置";
                Log::info('设置用户默认配置，用户ID：{user_id}', [
                    'user_id'  => $userId,
                    'settings' => $defaultSettings
                ]);
            }

            return $this->success($message, [
                'user_id'      => $userId,
                'action'       => $action,
                'key'          => $key,
                'value'        => $value,
                'all_settings' => $allSettings,
            ]);
        } catch (\Throwable $e) {
            Log::error('用户配置操作失败：{error}', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->error('用户配置操作失败：' . $e->getMessage());
        }
    }

    /**
     * 商品库存示例
     */
    public function inventory()
    {
        try {
            $redis     = Redis::hash();
            $productId = $this->request->param('product_id', rand(1, 10), 'intval');
            $quantity  = $this->request->param('quantity', 1, 'intval');
            $action    = $this->request->param('action', 'check'); // check, increment, decrement

            Log::info('库存操作，商品ID：{product_id}，数量：{quantity}，操作：{action}', [
                'product_id' => $productId,
                'quantity'   => $quantity,
                'action'     => $action
            ]);

            $inventoryKey = "inventory:products";

            // 如果商品不存在，初始化库存
            if (!$redis->hExists($inventoryKey, (string) $productId)) {
                $initialStock = rand(10, 100);
                $redis->hSet($inventoryKey, (string) $productId, $initialStock);
                $message = "商品 {$productId} 库存初始化为 {$initialStock}";
                Log::info('商品库存初始化，商品ID：{product_id}，初始库存：{initial_stock}', [
                    'product_id'    => $productId,
                    'initial_stock' => $initialStock
                ]);
            }

            // 执行库存操作
            switch ($action) {
                case 'increment':
                    $newStock = $redis->hIncrBy($inventoryKey, (string) $productId, $quantity);
                    $message = "商品 {$productId} 库存增加 {$quantity}，当前库存为 {$newStock}";
                    Log::info('增加商品库存，商品ID：{product_id}，增加数量：{increment}，新库存：{new_stock}', [
                        'product_id' => $productId,
                        'increment'  => $quantity,
                        'new_stock'  => $newStock
                    ]);
                    break;

                case 'decrement':
                    // 先检查库存是否充足
                    $currentStock = (int) $redis->hGet($inventoryKey, (string) $productId);

                    if ($currentStock >= $quantity) {
                        $newStock = $redis->hIncrBy($inventoryKey, (string) $productId, -$quantity);
                        $message  = "商品 {$productId} 库存减少 {$quantity}，当前库存为 {$newStock}";
                        Log::info('减少商品库存，商品ID：{product_id}，减少数量：{decrement}，新库存：{new_stock}', [
                            'product_id' => $productId,
                            'decrement'  => $quantity,
                            'new_stock'  => $newStock
                        ]);
                    } else {
                        $message = "商品 {$productId} 库存不足，当前库存为 {$currentStock}，需要 {$quantity}";
                        Log::warning('商品库存不足，商品ID：{product_id}，当前库存：{current_stock}，请求数量：{requested}', [
                            'product_id'    => $productId,
                            'current_stock' => $currentStock,
                            'requested'     => $quantity
                        ]);
                    }
                    break;

                default: // check
                    $currentStock = (int) $redis->hGet($inventoryKey, (string) $productId);
                    $message = "商品 {$productId} 当前库存为 {$currentStock}";
                    Log::info('查询商品库存，商品ID：{product_id}，当前库存：{current_stock}', [
                        'product_id'    => $productId,
                        'current_stock' => $currentStock
                    ]);
                    break;
            }

            // 获取所有商品库存
            $allInventory = $redis->hGetAll($inventoryKey);

            return $this->success($message, [
                'product_id'    => $productId,
                'action'        => $action,
                'quantity'      => $quantity,
                'current_stock' => (int) $redis->hGet($inventoryKey, (string) $productId),
                'all_inventory' => $allInventory,
            ]);
        } catch (\Throwable $e) {
            Log::error('库存操作失败：{error}', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->error('库存操作失败：' . $e->getMessage());
        }
    }
}