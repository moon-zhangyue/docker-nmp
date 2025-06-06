<?php

namespace app\service\mongo;

use think\facade\Db;
use think\facade\Log;
use app\model\Product;
use think\Exception;

class ProductService
{
    private $connection = 'mongo'; // Default MongoDB connection
    private $collection = 'products';

    /**
     * Add a new product with dynamic attributes.
     * @param array $productData
     * @return bool|string Inserted ID or false on failure
     */
    public function addProduct(array $productData)
    {
        if (empty($productData)) {
            Log::warning('Attempted to add empty product data.');
            return false;
        }

        try {
            $insertedId = Db::connect($this->connection)->table($this->collection)->insertGetId($productData);
            if ($insertedId) {
                Log::info('New product added with ID: ' . $insertedId . ', Data: ' . json_encode($productData));
                return $insertedId;
            } else {
                Log::error('Failed to add product. Data: ' . json_encode($productData));
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Error adding product: ' . $e->getMessage() . ', Data: ' . json_encode($productData));
            return false;
        }
    }

    /**
     * Get all products.
     * @return array
     */
    public function getAllProducts(): array
    {
        try {
            $products = Db::connect($this->connection)->table($this->collection)->select();
            Log::info('Retrieved all products. Count: ' . count($products));
            return $products->all();
        } catch (\Exception $e) {
            Log::error('Error retrieving all products: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Find a product by its ID.
     * @param string $productId
     * @return array|null
     */
    public function findProductById(string $productId): ?array
    {
        try {
            // In MongoDB, _id is an ObjectId, but ThinkPHP's Db facade might handle string conversion.
            // If searching by string representation of ObjectId, ensure it's valid.
            // For simplicity, this example assumes $productId is the string representation.
            $product = Db::connect($this->connection)->table($this->collection)->where('_id', $productId)->find();
            if ($product) {
                Log::info('Product found with ID: ' . $productId . ', Data: ' . json_encode($product));
            } else {
                Log::info('Product not found with ID: ' . $productId);
            }
            return $product;
        } catch (\Exception $e) {
            Log::error('Error finding product by ID ' . $productId . ': ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 创建产品
     * 
     * @param array $data 产品数据
     * @return array
     * @throws \Exception
     */
    public function create(array $data): array
    {
        try {
            // 验证必要字段
            if (empty($data['name']) || empty($data['category'])) {
                throw new \Exception('产品名称和分类不能为空');
            }

            // 记录日志
            Log::info('创建产品: {name}, 分类: {category}', [
                'name'     => $data['name'],
                'category' => $data['category'],
                'data'     => $data
            ]);

            // 创建产品
            $product = Product::addProduct($data);

            return $product->toArray();
        } catch (\Exception $e) {
            Log::error('创建产品失败: {message}', ['data' => $data, 'message' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * 更新产品
     * 
     * @param mixed $id 产品ID
     * @param array $data 更新数据
     * @return bool
     * @throws \Exception
     */
    public function update($id, array $data): bool
    {
        try {
            // 检查产品是否存在
            $product = Product::find($id);
            if (!$product) {
                throw new Exception('产品不存在');
            }

            // 记录日志
            Log::info('更新产品', ['id' => $id, 'data' => $data]);

            // 更新产品
            return Product::updateProduct($id, $data);
        } catch (\Exception $e) {
            Log::error('更新产品失败', ['id' => $id, 'data' => $data, 'message' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * 根据动态条件查询产品
     * 
     * @param array $params 查询条件
     * @return array
     */
    public function search(array $params = []): array
    {
        try {
            // 构建查询条件
            $condition = [];

            // 动态添加查询条件 - 支持任意字段查询
            foreach ($params as $key => $value) {
                if (!empty($value)) {
                    $condition[$key] = $value;
                }
            }

            // 记录日志
            Log::info('查询产品: 条件数量 {condition_count}', [
                'condition'       => $condition,
                'condition_count' => count($condition)
            ]);

            // 查询产品
            return Product::findProducts($condition);
        } catch (\Exception $e) {
            Log::error('查询产品失败: {message}', ['params' => $params, 'message' => $e->getMessage()]);
            return [];
        }
    }
}

/*
 * =============================================================================
 *  Conceptual Testing Notes for ProductService
 * =============================================================================
 *
 * **Unit Tests:**
 * - Mock `think\facade\Db` to isolate service logic.
 * - Test `addProduct()`:
 *   - With valid data: Verify `Db::connect()->table()->insertGetId()` is called with correct data and returns an ID.
 *   - With empty data: Verify it returns false and logs a warning.
 *   - With DB exception (mock `insertGetId` to throw): Verify it catches, logs, and returns false.
 * - Test `getAllProducts()`:
 *   - Verify `Db::connect()->table()->select()` is called.
 *   - Mock `select()` to return a mock collection, then mock `all()` on that collection.
 *   - With DB exception (mock `select` to throw): Verify it catches, logs, and returns an empty array.
 * - Test `findProductById()`:
 *   - With existing ID: Verify `Db::connect()->table()->where()->find()` is called with correct ID.
 *   - With non-existing ID (mock `find` to return null): Verify it returns null and logs info.
 *   - With DB exception (mock `find` to throw): Verify it catches, logs, and returns null.
 *
 * **Integration Tests (via Controller or direct service instantiation with real DB):**
 * - These tests would involve actual MongoDB interaction (ensure a test database is configured).
 * - Test `addProduct()`:
 *   - Call with sample product data.
 *   - Query the database directly or use `findProductById()` to verify the product was inserted correctly.
 *   - Check if the returned ID is valid.
 * - Test `getAllProducts()`:
 *   - Add a few products.
 *   - Call `getAllProducts()` and verify the returned array contains the added products.
 * - Test `findProductById()`:
 *   - Add a product, get its ID.
 *   - Call `findProductById()` with this ID and verify correct data is returned.
 *   - Call `findProductById()` with a non-existent ID and verify null is returned.
 *
 * **Controller-Level Integration Tests (HTTP requests):**
 * - Test `app\controller\mongo\ProductController` actions.
 * - Example:
 *   - POST to `/mongo/product/add` with valid JSON data, check for 200/201 status and product ID in response.
 *   - GET from `/mongo/product/list`, check for 200 status and an array of products in response.
 *   - Add a product, then GET from `/mongo/product/get/{id}` using its ID, check for 200 status and product data.
 *   - GET from `/mongo/product/get/{non_existent_id}`, check for 404 status.
 */
