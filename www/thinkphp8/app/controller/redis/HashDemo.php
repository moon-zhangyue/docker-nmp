<?php
declare(strict_types=1);

namespace app\controller\redis;

use app\controller\RedisDemo;
use app\facade\Redis;
use think\facade\Db;
use think\facade\View;

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
        return View::fetch('redis/hash/index');
    }

    /**
     * 基本用法示例
     */
    public function basic()
    {
        try {
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
            $redis->hSet($key, 'counter', 10);
            $incrValue = $redis->hIncrBy($key, 'counter', 5);

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
            return $this->error('Hash基本用法演示失败：' . $e->getMessage());
        }
    }

    /**
     * 缓存用户信息示例
     */
    public function cacheUser()
    {
        try {
            $redis  = Redis::hash();
            $userId = $this->request->param('id', 1, 'intval');
            $key    = 'user:profile';

            // 使用remember方法自动防止缓存穿透
            $user = $redis->hRemember($key, "user:{$userId}", function () use ($userId) {
                // 模拟从数据库获取用户信息
                // 注：这里只是演示，实际应用中应该使用User模型查询
                $user = Db::name('user')->where('id', $userId)->find();
                return $user;
            }, true);

            if ($user) {
                return $this->success('获取用户信息成功', [
                    'user'       => $user,
                    'from_cache' => true
                ]);
            } else {
                // 查看缓存中的所有用户信息
                $allUsers = $redis->hGetAll($key);

                return $this->success('用户不存在', [
                    'message'          => '用户不存在或缓存已过期',
                    'all_cached_users' => $allUsers
                ]);
            }
        } catch (\Throwable $e) {
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
                    } else {
                        $message = "参数错误";
                    }
                    break;

                case 'update':
                    if ($productId > 0 && $quantity >= 0) {
                        if ($quantity > 0) {
                            $redis->hSet($cartKey, (string) $productId, $quantity);
                            $message = "已更新商品(ID: {$productId})数量为 {$quantity}";
                        } else {
                            $redis->hDel($cartKey, (string) $productId);
                            $message = "已从购物车移除商品(ID: {$productId})";
                        }
                    } else {
                        $message = "参数错误";
                    }
                    break;

                case 'remove':
                    if ($productId > 0) {
                        $redis->hDel($cartKey, (string) $productId);
                        $message = "已从购物车移除商品(ID: {$productId})";
                    } else {
                        $message = "参数错误";
                    }
                    break;

                case 'clear':
                    // 获取所有商品ID
                    $productIds = $redis->hKeys($cartKey);
                    if (!empty($productIds)) {
                        $redis->hDel($cartKey, $productIds);
                    }
                    $message = "已清空购物车";
                    break;

                default:
                    $message = "查看购物车";
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

            $settingsKey = "user:settings:{$userId}";

            // 执行配置操作
            switch ($action) {
                case 'set':
                    if (!empty($key)) {
                        $redis->hSet($settingsKey, $key, $value);
                        $message = "配置项 {$key} 已设置为 {$value}";
                    } else {
                        $message = "参数错误";
                    }
                    break;

                case 'delete':
                    if (!empty($key)) {
                        $redis->hDel($settingsKey, $key);
                        $message = "配置项 {$key} 已删除";
                    } else {
                        $message = "参数错误";
                    }
                    break;

                default: // get
                    if (!empty($key)) {
                        $value   = $redis->hGet($settingsKey, $key);
                        $message = "获取配置项 {$key}";
                    } else {
                        $message = "获取所有配置";
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
            }

            return $this->success($message, [
                'user_id'      => $userId,
                'action'       => $action,
                'key'          => $key,
                'value'        => $value,
                'all_settings' => $allSettings,
            ]);
        } catch (\Throwable $e) {
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

            $inventoryKey = "inventory:products";

            // 如果商品不存在，初始化库存
            if (!$redis->hExists($inventoryKey, (string) $productId)) {
                $initialStock = rand(10, 100);
                $redis->hSet($inventoryKey, (string) $productId, $initialStock);
                $message = "商品 {$productId} 库存初始化为 {$initialStock}";
            }

            // 执行库存操作
            switch ($action) {
                case 'increment':
                    $newStock = $redis->hIncrBy($inventoryKey, (string) $productId, $quantity);
                    $message = "商品 {$productId} 库存增加 {$quantity}，当前库存为 {$newStock}";
                    break;

                case 'decrement':
                    // 先检查库存是否充足
                    $currentStock = (int) $redis->hGet($inventoryKey, (string) $productId);

                    if ($currentStock >= $quantity) {
                        $newStock = $redis->hIncrBy($inventoryKey, (string) $productId, -$quantity);
                        $message  = "商品 {$productId} 库存减少 {$quantity}，当前库存为 {$newStock}";
                    } else {
                        $message = "商品 {$productId} 库存不足，当前库存为 {$currentStock}，需要 {$quantity}";
                    }
                    break;

                default: // check
                    $currentStock = (int) $redis->hGet($inventoryKey, (string) $productId);
                    $message = "商品 {$productId} 当前库存为 {$currentStock}";
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
            return $this->error('库存操作失败：' . $e->getMessage());
        }
    }
}