<?php
declare(strict_types=1);

namespace app\service;

use app\model\Product;
use think\facade\Log;
use think\Exception;

class ProductService
{
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
                'name' => $data['name'],
                'category' => $data['category'],
                'data' => $data
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
                'condition' => $condition,
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