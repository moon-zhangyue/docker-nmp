<?php
declare(strict_types=1);

namespace app\model\pg;

use think\Model;
use think\model\concern\SoftDelete;

/**
 * 品牌模型
 */
class Brand extends Model
{
    use SoftDelete;
    
    // 设置当前模型对应的数据库连接
    protected $connection = 'postgresql';
    
    // 设置当前模型对应的完整数据表名称
    protected $table = 'brands';
    
    // 设置当前模型的数据库主键
    protected $pk = 'id';
    
    // 设置当前模型默认的查询条件
    protected $defaultScope = ['status' => true];
    
    // 设置软删除字段
    protected $deleteTime = 'delete_time';
    
    // 自动写入时间戳
    protected $autoWriteTimestamp = true;
    
    // 类型转换
    protected $type = [
        'id'     => 'integer',
        'sort'   => 'integer',
        'status' => 'boolean'
    ];
    
    /**
     * 搜索品牌名称
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
     * 搜索状态
     *
     * @param \think\db\Query $query 查询对象
     * @param mixed $value 搜索值
     * @return void
     */
    public function searchStatusAttr($query, $value)
    {
        if ($value !== '' && $value !== null) {
            $query->where('status', $value);
        }
    }
    
    /**
     * 关联商品
     *
     * @return \think\model\relation\HasMany
     */
    public function goods()
    {
        return $this->hasMany(Goods::class, 'brand_id', 'id');
    }
} 