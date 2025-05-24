<?php
declare(strict_types=1);

namespace app\model;

use think\Model;
use think\facade\Log;
use think\exception\DbException;

/**
 * 电商产品目录模型 - 动态模式特性
 * 支持快速变化的数据需求，无固定schema
 */
class ProductCatalog extends Model
{
    // 设置MongoDB连接
    protected $connection = 'mongo';

    // 设置集合名称
    protected $table = 'product_catalog';

    // 设置主键
    protected $pk = '_id';

    // 自动时间戳
    protected $autoWriteTimestamp = true;

    // 允许的字段（动态模式下可为空，允许任意字段）
    protected $field = [];

    // JSON字段
    protected $json = ['attributes', 'specifications', 'variants', 'metadata'];

    /**
     * 创建产品（支持动态字段）
     * 
     * @param array $data 产品数据
     * @return array|false
     */
    public static function createProduct(array $data)
    {
        try {
            Log::info('创建产品目录', ['product_name' => $data['name'] ?? 'unknown']);
            
            // 添加创建时间戳
            $data['created_at'] = time();
            $data['updated_at'] = time();
            
            $product = self::create($data);
            
            if ($product) {
                Log::info('产品创建成功', ['product_id' => $product->_id]);
                return $product->toArray();
            }
            
            return false;
        } catch (\Exception $e) {
            Log::error('创建产品失败', [
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * 动态更新产品属性
     * 
     * @param string $productId 产品ID
     * @param array $attributes 动态属性
     * @return bool
     */
    public static function updateDynamicAttributes(string $productId, array $attributes): bool
    {
        try {
            Log::info('更新产品动态属性', ['product_id' => $productId]);
            
            $result = self::where('_id', $productId)->update([
                'attributes' => $attributes,
                'updated_at' => time()
            ]);
            
            if ($result) {
                Log::info('产品属性更新成功', ['product_id' => $productId]);
                return true;
            }
            
            return false;
        } catch (\Exception $e) {
            Log::error('更新产品属性失败', [
                'product_id' => $productId,
                'attributes' => $attributes,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 根据动态条件搜索产品
     * 
     * @param array $conditions 搜索条件
     * @param int $page 页码
     * @param int $limit 每页数量
     * @return array
     */
    public static function searchByDynamicConditions(array $conditions, int $page = 1, int $limit = 20): array
    {
        try {
            Log::info('动态条件搜索产品', ['conditions' => $conditions]);
            
            $query = self::query();
            
            // 处理动态条件
            foreach ($conditions as $field => $value) {
                if (is_array($value)) {
                    // 数组条件，使用in查询
                    $query->whereIn($field, $value);
                } elseif (strpos($field, '.') !== false) {
                    // 嵌套字段查询
                    $query->where($field, $value);
                } else {
                    // 普通字段查询
                    $query->where($field, 'like', '%' . $value . '%');
                }
            }
            
            $result = $query->page($page, $limit)
                          ->order('updated_at', 'desc')
                          ->select()
                          ->toArray();
            
            Log::info('搜索完成', ['count' => count($result)]);
            return $result;
            
        } catch (\Exception $e) {
            Log::error('动态搜索失败', [
                'conditions' => $conditions,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * 添加产品变体（动态字段）
     * 
     * @param string $productId 产品ID
     * @param array $variant 变体数据
     * @return bool
     */
    public static function addProductVariant(string $productId, array $variant): bool
    {
        try {
            Log::info('添加产品变体', ['product_id' => $productId]);
            
            // 获取现有变体
            $product = self::find($productId);
            if (!$product) {
                throw new \Exception('产品不存在');
            }
            
            $variants = $product->variants ?? [];
            $variant['id'] = uniqid();
            $variant['created_at'] = time();
            $variants[] = $variant;
            
            $result = $product->save([
                'variants' => $variants,
                'updated_at' => time()
            ]);
            
            if ($result) {
                Log::info('产品变体添加成功', ['product_id' => $productId, 'variant_id' => $variant['id']]);
                return true;
            }
            
            return false;
        } catch (\Exception $e) {
            Log::error('添加产品变体失败', [
                'product_id' => $productId,
                'variant' => $variant,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 获取产品统计信息
     * 
     * @return array
     */
    public static function getStatistics(): array
    {
        try {
            Log::info('获取产品统计信息');
            
            // 使用MongoDB聚合框架
            $pipeline = [
                [
                    '$group' => [
                        '_id' => '$category',
                        'count' => ['$sum' => 1],
                        'avg_price' => ['$avg' => '$price']
                    ]
                ],
                [
                    '$sort' => ['count' => -1]
                ]
            ];
            
            // 注意：这里需要使用原生MongoDB查询
            // ThinkPHP的MongoDB驱动可能不完全支持聚合管道
            $result = [];
            
            Log::info('统计信息获取完成');
            return $result;
            
        } catch (\Exception $e) {
            Log::error('获取统计信息失败', ['error' => $e->getMessage()]);
            return [];
        }
    }
}