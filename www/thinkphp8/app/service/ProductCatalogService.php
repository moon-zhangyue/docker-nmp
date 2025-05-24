<?php
declare(strict_types=1);

namespace app\service;

use app\model\ProductCatalog;
use think\facade\Log;
use think\facade\Cache;

/**
 * 产品目录服务 - 动态模式特性
 * 处理电商产品目录的业务逻辑
 */
class ProductCatalogService
{
    /**
     * 创建产品
     * 
     * @param array $productData 产品数据
     * @return array
     */
    public function createProduct(array $productData): array
    {
        try {
            Log::info('产品目录服务：创建产品', ['product_name' => $productData['name'] ?? 'unknown']);
            
            // 数据验证
            $this->validateProductData($productData);
            
            // 处理动态属性
            $processedData = $this->processProductData($productData);
            
            // 创建产品
            $product = ProductCatalog::createProduct($processedData);
            
            if ($product) {
                // 清除相关缓存
                $this->clearProductCache($product['category'] ?? '');
                
                Log::info('产品创建成功', ['product_id' => $product['_id']]);
                return [
                    'success' => true,
                    'data' => $product,
                    'message' => '产品创建成功'
                ];
            }
            
            return [
                'success' => false,
                'data' => null,
                'message' => '产品创建失败'
            ];
            
        } catch (\Exception $e) {
            Log::error('产品创建失败', [
                'product_data' => $productData,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'data' => null,
                'message' => '产品创建失败：' . $e->getMessage()
            ];
        }
    }

    /**
     * 搜索产品
     * 
     * @param array $searchParams 搜索参数
     * @return array
     */
    public function searchProducts(array $searchParams): array
    {
        try {
            Log::info('产品目录服务：搜索产品', $searchParams);
            
            $page = $searchParams['page'] ?? 1;
            $limit = $searchParams['limit'] ?? 20;
            $cacheKey = 'product_search_' . md5(json_encode($searchParams));
            
            // 尝试从缓存获取
            $cachedResult = Cache::get($cacheKey);
            if ($cachedResult) {
                Log::info('从缓存获取搜索结果', ['cache_key' => $cacheKey]);
                return $cachedResult;
            }
            
            // 构建搜索条件
            $conditions = $this->buildSearchConditions($searchParams);
            
            // 执行搜索
            $products = ProductCatalog::searchByDynamicConditions($conditions, $page, $limit);
            
            $result = [
                'success' => true,
                'data' => [
                    'products' => $products,
                    'total' => count($products),
                    'page' => $page,
                    'limit' => $limit
                ],
                'message' => '搜索完成'
            ];
            
            // 缓存结果（5分钟）
            Cache::set($cacheKey, $result, 300);
            
            Log::info('产品搜索完成', ['result_count' => count($products)]);
            return $result;
            
        } catch (\Exception $e) {
            Log::error('产品搜索失败', [
                'search_params' => $searchParams,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'data' => null,
                'message' => '搜索失败：' . $e->getMessage()
            ];
        }
    }

    /**
     * 更新产品属性
     * 
     * @param string $productId 产品ID
     * @param array $attributes 属性数据
     * @return array
     */
    public function updateProductAttributes(string $productId, array $attributes): array
    {
        try {
            Log::info('产品目录服务：更新产品属性', ['product_id' => $productId]);
            
            // 验证属性数据
            $validatedAttributes = $this->validateAttributes($attributes);
            
            // 更新属性
            $success = ProductCatalog::updateDynamicAttributes($productId, $validatedAttributes);
            
            if ($success) {
                // 清除产品缓存
                $this->clearProductCacheById($productId);
                
                Log::info('产品属性更新成功', ['product_id' => $productId]);
                return [
                    'success' => true,
                    'data' => ['product_id' => $productId],
                    'message' => '属性更新成功'
                ];
            }
            
            return [
                'success' => false,
                'data' => null,
                'message' => '属性更新失败'
            ];
            
        } catch (\Exception $e) {
            Log::error('产品属性更新失败', [
                'product_id' => $productId,
                'attributes' => $attributes,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'data' => null,
                'message' => '属性更新失败：' . $e->getMessage()
            ];
        }
    }

    /**
     * 添加产品变体
     * 
     * @param string $productId 产品ID
     * @param array $variantData 变体数据
     * @return array
     */
    public function addProductVariant(string $productId, array $variantData): array
    {
        try {
            Log::info('产品目录服务：添加产品变体', ['product_id' => $productId]);
            
            // 验证变体数据
            $this->validateVariantData($variantData);
            
            // 添加变体
            $success = ProductCatalog::addProductVariant($productId, $variantData);
            
            if ($success) {
                // 清除产品缓存
                $this->clearProductCacheById($productId);
                
                Log::info('产品变体添加成功', ['product_id' => $productId]);
                return [
                    'success' => true,
                    'data' => ['product_id' => $productId],
                    'message' => '变体添加成功'
                ];
            }
            
            return [
                'success' => false,
                'data' => null,
                'message' => '变体添加失败'
            ];
            
        } catch (\Exception $e) {
            Log::error('产品变体添加失败', [
                'product_id' => $productId,
                'variant_data' => $variantData,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'data' => null,
                'message' => '变体添加失败：' . $e->getMessage()
            ];
        }
    }

    /**
     * 获取产品推荐
     * 
     * @param string $productId 产品ID
     * @param int $limit 推荐数量
     * @return array
     */
    public function getProductRecommendations(string $productId, int $limit = 10): array
    {
        try {
            Log::info('产品目录服务：获取产品推荐', ['product_id' => $productId, 'limit' => $limit]);
            
            $cacheKey = "product_recommendations_{$productId}_{$limit}";
            
            // 尝试从缓存获取
            $cachedResult = Cache::get($cacheKey);
            if ($cachedResult) {
                return $cachedResult;
            }
            
            // 获取当前产品信息
            $currentProduct = ProductCatalog::find($productId);
            if (!$currentProduct) {
                throw new \Exception('产品不存在');
            }
            
            // 基于类别和属性推荐相似产品
            $recommendations = $this->findSimilarProducts($currentProduct->toArray(), $limit);
            
            $result = [
                'success' => true,
                'data' => [
                    'recommendations' => $recommendations,
                    'total' => count($recommendations)
                ],
                'message' => '推荐获取成功'
            ];
            
            // 缓存结果（10分钟）
            Cache::set($cacheKey, $result, 600);
            
            Log::info('产品推荐获取完成', ['recommendation_count' => count($recommendations)]);
            return $result;
            
        } catch (\Exception $e) {
            Log::error('获取产品推荐失败', [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'data' => null,
                'message' => '推荐获取失败：' . $e->getMessage()
            ];
        }
    }

    /**
     * 验证产品数据
     * 
     * @param array $productData 产品数据
     * @throws \Exception
     */
    private function validateProductData(array $productData): void
    {
        if (empty($productData['name'])) {
            throw new \Exception('产品名称不能为空');
        }
        
        if (empty($productData['category'])) {
            throw new \Exception('产品类别不能为空');
        }
        
        if (isset($productData['price']) && !is_numeric($productData['price'])) {
            throw new \Exception('产品价格必须为数字');
        }
    }

    /**
     * 处理产品数据
     * 
     * @param array $productData 原始产品数据
     * @return array 处理后的产品数据
     */
    private function processProductData(array $productData): array
    {
        // 设置默认值
        $processedData = array_merge([
            'status' => 'active',
            'visibility' => 'public',
            'stock' => 0,
            'attributes' => [],
            'specifications' => [],
            'variants' => [],
            'metadata' => []
        ], $productData);
        
        // 处理价格
        if (isset($processedData['price'])) {
            $processedData['price'] = (float)$processedData['price'];
        }
        
        // 处理库存
        if (isset($processedData['stock'])) {
            $processedData['stock'] = (int)$processedData['stock'];
        }
        
        // 生成SKU（如果没有提供）
        if (empty($processedData['sku'])) {
            $processedData['sku'] = $this->generateSKU($processedData);
        }
        
        return $processedData;
    }

    /**
     * 构建搜索条件
     * 
     * @param array $searchParams 搜索参数
     * @return array 搜索条件
     */
    private function buildSearchConditions(array $searchParams): array
    {
        $conditions = [];
        
        // 关键词搜索
        if (!empty($searchParams['keyword'])) {
            $conditions['name'] = $searchParams['keyword'];
        }
        
        // 类别过滤
        if (!empty($searchParams['category'])) {
            $conditions['category'] = $searchParams['category'];
        }
        
        // 价格范围
        if (!empty($searchParams['min_price'])) {
            $conditions['price'] = ['>=', (float)$searchParams['min_price']];
        }
        
        if (!empty($searchParams['max_price'])) {
            if (isset($conditions['price'])) {
                $conditions['price'] = ['between', [(float)$searchParams['min_price'], (float)$searchParams['max_price']]];
            } else {
                $conditions['price'] = ['<=', (float)$searchParams['max_price']];
            }
        }
        
        // 状态过滤
        if (!empty($searchParams['status'])) {
            $conditions['status'] = $searchParams['status'];
        }
        
        // 动态属性过滤
        if (!empty($searchParams['attributes']) && is_array($searchParams['attributes'])) {
            foreach ($searchParams['attributes'] as $key => $value) {
                $conditions["attributes.{$key}"] = $value;
            }
        }
        
        return $conditions;
    }

    /**
     * 验证属性数据
     * 
     * @param array $attributes 属性数据
     * @return array 验证后的属性数据
     */
    private function validateAttributes(array $attributes): array
    {
        $validatedAttributes = [];
        
        foreach ($attributes as $key => $value) {
            // 过滤无效的属性键
            if (is_string($key) && !empty($key)) {
                $validatedAttributes[$key] = $value;
            }
        }
        
        return $validatedAttributes;
    }

    /**
     * 验证变体数据
     * 
     * @param array $variantData 变体数据
     * @throws \Exception
     */
    private function validateVariantData(array $variantData): void
    {
        if (empty($variantData['name'])) {
            throw new \Exception('变体名称不能为空');
        }
        
        if (isset($variantData['price']) && !is_numeric($variantData['price'])) {
            throw new \Exception('变体价格必须为数字');
        }
    }

    /**
     * 查找相似产品
     * 
     * @param array $currentProduct 当前产品
     * @param int $limit 限制数量
     * @return array 相似产品列表
     */
    private function findSimilarProducts(array $currentProduct, int $limit): array
    {
        // 基于类别查找相似产品
        $conditions = [
            'category' => $currentProduct['category'] ?? '',
            'status' => 'active'
        ];
        
        $similarProducts = ProductCatalog::searchByDynamicConditions($conditions, 1, $limit + 1);
        
        // 移除当前产品
        $recommendations = [];
        foreach ($similarProducts as $product) {
            if ($product['_id'] !== $currentProduct['_id']) {
                $recommendations[] = $product;
            }
        }
        
        return array_slice($recommendations, 0, $limit);
    }

    /**
     * 生成SKU
     * 
     * @param array $productData 产品数据
     * @return string SKU
     */
    private function generateSKU(array $productData): string
    {
        $category = strtoupper(substr($productData['category'] ?? 'PROD', 0, 4));
        $timestamp = substr((string)time(), -6);
        $random = strtoupper(substr(md5(uniqid()), 0, 4));
        
        return $category . $timestamp . $random;
    }

    /**
     * 清除产品缓存
     * 
     * @param string $category 产品类别
     */
    private function clearProductCache(string $category): void
    {
        try {
            // 清除搜索缓存
            Cache::tag('product_search')->clear();
            
            // 清除类别相关缓存
            if (!empty($category)) {
                Cache::delete("category_products_{$category}");
            }
            
            Log::info('产品缓存清除完成', ['category' => $category]);
        } catch (\Exception $e) {
            Log::warning('清除产品缓存失败', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 根据产品ID清除缓存
     * 
     * @param string $productId 产品ID
     */
    private function clearProductCacheById(string $productId): void
    {
        try {
            Cache::delete("product_{$productId}");
            Cache::delete("product_recommendations_{$productId}_10");
            Cache::tag('product_search')->clear();
            
            Log::info('产品缓存清除完成', ['product_id' => $productId]);
        } catch (\Exception $e) {
            Log::warning('清除产品缓存失败', ['error' => $e->getMessage()]);
        }
    }
}