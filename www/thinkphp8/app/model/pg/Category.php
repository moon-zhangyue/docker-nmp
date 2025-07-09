<?php
declare(strict_types=1);

namespace app\model\pg;

use think\Model;
use think\model\concern\SoftDelete;

/**
 * 商品分类模型
 */
class Category extends Model
{
    use SoftDelete;

    // 设置当前模型对应的数据库连接
    protected $connection = 'postgresql';

    // 设置当前模型对应的完整数据表名称
    protected $table = 'categories';

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
        'id'        => 'integer',
        'parent_id' => 'integer',
        'sort'      => 'integer',
        'status'    => 'boolean'
    ];

    /**
     * 搜索分类名称
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
     * 搜索父级分类ID
     *
     * @param \think\db\Query $query 查询对象
     * @param mixed $value 搜索值
     * @return void
     */
    public function searchParentIdAttr($query, $value)
    {
        if ($value !== '' && $value !== null) {
            $query->where('parent_id', $value);
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
     * 关联父级分类
     *
     * @return \think\model\relation\BelongsTo
     */
    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id', 'id');
    }

    /**
     * 关联子分类
     *
     * @return \think\model\relation\HasMany
     */
    public function children()
    {
        return $this->hasMany(self::class, 'parent_id', 'id');
    }

    /**
     * 关联商品
     *
     * @return \think\model\relation\HasMany
     */
    public function goods()
    {
        return $this->hasMany(Goods::class, 'category_id', 'id');
    }

    /**
     * 获取分类树状结构
     *
     * @param integer $parentId 父级ID
     * @param array $filter 过滤条件
     * @return array
     */
    public static function getTree($parentId = 0, $filter = [])
    {
        $filter = array_merge(['status' => true], $filter);

        // 查询指定父级下的所有分类
        $categories = self::where('parent_id', $parentId)
            ->where($filter)
            ->order('sort', 'asc')
            ->select();

        $tree = [];
        foreach ($categories as $category) {
            $item = $category->toArray();
            // 递归获取子分类
            $item['children'] = self::getTree($category->id, $filter);
            $tree[]           = $item;
        }

        return $tree;
    }

    /**
     * 获取所有子分类ID（包括自身）
     *
     * @param integer $categoryId 分类ID
     * @return array
     */
    public static function getAllChildrenIds($categoryId)
    {
        $ids = [$categoryId];

        // 获取直接子分类
        $children = self::where('parent_id', $categoryId)->column('id');

        // 递归获取所有子分类
        foreach ($children as $childId) {
            $ids = array_merge($ids, self::getAllChildrenIds($childId));
        }

        return $ids;
    }
}