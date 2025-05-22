<?php
declare(strict_types=1);

namespace app\model;

use think\Model;
use think\model\concern\SoftDelete;

class Product extends Model
{
    use SoftDelete;
    
    // 设置MongoDB连接
    protected $connection = 'mongo';
    
    // 设置集合名称
    protected $table = 'products';
    
    // 设置主键
    protected $pk = '_id';
    
    // 自动时间戳
    protected $autoWriteTimestamp = true;
    
    // 允许写入的字段（动态模式下，可以不严格限制字段）
    protected $field = true;
    
    /**
     * 添加产品（支持动态字段）
     * 
     * @param array $data 产品数据
     * @return Product
     */
    public static function addProduct(array $data): Product
    {
        return self::create($data);
    }
    
    /**
     * 更新产品（支持动态字段）
     * 
     * @param mixed $id 产品ID
     * @param array $data 更新数据
     * @return bool
     */
    public static function updateProduct($id, array $data): bool
    {
        return self::find($id)->save($data);
    }
    
    /**
     * 查询产品（支持动态字段查询）
     * 
     * @param array $condition 查询条件
     * @return array
     */
    public static function findProducts(array $condition = []): array
    {
        return self::where($condition)->select()->toArray();
    }
} 