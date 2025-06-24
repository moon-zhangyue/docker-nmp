<?php
declare(strict_types=1);

namespace app\model\pg;

use think\Model;
use think\model\concern\SoftDelete;

/**
 * 商品模型
 */
class Goods extends Model
{
    use SoftDelete;
    
    // 设置当前模型对应的数据库连接
    protected $connection = 'postgresql';
    
    // 设置当前模型对应的完整数据表名称
    protected $table = 'goods';
    
    // 设置当前模型的数据库主键
    protected $pk = 'id';
    
    // 设置软删除字段
    protected $deleteTime = 'delete_time';
    
    // 自动写入时间戳
    protected $autoWriteTimestamp = true;
    
    // 类型转换
    protected $type = [
        'id'           => 'integer',
        'category_id'  => 'integer',
        'brand_id'     => 'integer',
        'pictures'     => 'array',
        'on_sale'      => 'boolean',
        'is_recommend' => 'boolean',
        'is_hot'       => 'boolean',
        'is_new'       => 'boolean',
        'sales'        => 'integer',
        'stock'        => 'integer',
        'sort'         => 'integer',
        'status'       => 'boolean'
    ];
    
    /**
     * 搜索商品名称
     *
     * @param \think\db\Query $query 查询对象
     * @param mixed $value 搜索值
     * @return void
     */
    public function searchNameAttr($query, $value)
    {
        if (!empty($value)) {
            $query->where('name', 'like', "%{$value}%");
        }
    }
    
    /**
     * 搜索分类ID
     *
     * @param \think\db\Query $query 查询对象
     * @param mixed $value 搜索值
     * @return void
     */
    public function searchCategoryIdAttr($query, $value)
    {
        if (!empty($value)) {
            $query->where('category_id', $value);
        }
    }
    
    /**
     * 搜索品牌ID
     *
     * @param \think\db\Query $query 查询对象
     * @param mixed $value 搜索值
     * @return void
     */
    public function searchBrandIdAttr($query, $value)
    {
        if (!empty($value)) {
            $query->where('brand_id', $value);
        }
    }
    
    /**
     * 搜索上架状态
     *
     * @param \think\db\Query $query 查询对象
     * @param mixed $value 搜索值
     * @return void
     */
    public function searchOnSaleAttr($query, $value)
    {
        if ($value !== '' && $value !== null) {
            $query->where('on_sale', $value);
        }
    }
    
    /**
     * 搜索价格区间
     *
     * @param \think\db\Query $query 查询对象
     * @param mixed $value 搜索值 [min, max]
     * @return void
     */
    public function searchPriceRangeAttr($query, $value)
    {
        if (!empty($value) && is_array($value)) {
            // 根据价格范围筛选，需要联表查询最低价格的SKU
            if (isset($value[0]) && $value[0] > 0) {
                $query->whereExists(function ($query) use ($value) {
                    $query->table('goods_skus')
                          ->where('goods_id', '=', new \think\db\Raw('goods.id'))
                          ->where('price', '>=', $value[0]);
                });
            }
            
            if (isset($value[1]) && $value[1] > 0) {
                $query->whereExists(function ($query) use ($value) {
                    $query->table('goods_skus')
                          ->where('goods_id', '=', new \think\db\Raw('goods.id'))
                          ->where('price', '<=', $value[1]);
                });
            }
        }
    }
    
    /**
     * 获取最低价格
     *
     * @param mixed $value 值
     * @param array $data 数据
     * @return float
     */
    public function getMinPriceAttr($value, $data)
    {
        // 获取商品的最低价格
        $minPrice = GoodsSku::where('goods_id', $data['id'])->min('price');
        return $minPrice ?: 0;
    }
    
    /**
     * 获取最高价格
     *
     * @param mixed $value 值
     * @param array $data 数据
     * @return float
     */
    public function getMaxPriceAttr($value, $data)
    {
        // 获取商品的最高价格
        $maxPrice = GoodsSku::where('goods_id', $data['id'])->max('price');
        return $maxPrice ?: 0;
    }
    
    /**
     * 关联分类
     *
     * @return \think\model\relation\BelongsTo
     */
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }
    
    /**
     * 关联品牌
     *
     * @return \think\model\relation\BelongsTo
     */
    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id', 'id');
    }
    
    /**
     * 关联SKU
     *
     * @return \think\model\relation\HasMany
     */
    public function skus()
    {
        return $this->hasMany(GoodsSku::class, 'goods_id', 'id');
    }
} 