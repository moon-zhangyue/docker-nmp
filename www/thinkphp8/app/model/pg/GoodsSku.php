<?php
declare(strict_types=1);

namespace app\model\pg;

use think\Model;
use think\model\concern\SoftDelete;

/**
 * 商品SKU模型
 */
class GoodsSku extends Model
{
    use SoftDelete;

    // 设置当前模型对应的数据库连接
    protected $connection = 'postgresql';

    // 设置当前模型对应的完整数据表名称
    protected $table = 'goods_skus';

    // 设置当前模型的数据库主键
    protected $pk = 'id';

    // 设置软删除字段
    protected $deleteTime = 'delete_time';

    // 自动写入时间戳
    protected $autoWriteTimestamp = true;

    // 类型转换
    protected $type = [
        'id'             => 'integer',
        'goods_id'       => 'integer',
        'specs'          => 'array',
        'price'          => 'float',
        'original_price' => 'float',
        'cost_price'     => 'float',
        'stock'          => 'integer',
        'sales'          => 'integer',
        'status'         => 'boolean'
    ];

    /**
     * 关联商品
     *
     * @return \think\model\relation\BelongsTo
     */
    public function goods()
    {
        return $this->belongsTo(Goods::class, 'goods_id', 'id');
    }

    /**
     * 获取规格属性文本
     *
     * @param mixed $value 值
     * @param array $data 数据
     * @return string
     */
    public function getSpecsTextAttr($value, $data)
    {
        if (empty($data['specs'])) {
            return '';
        }

        $text = [];
        foreach ($data['specs'] as $spec => $value) {
            $text[] = "{$spec}:{$value}";
        }

        return implode(', ', $text);
    }

    /**
     * 减少库存
     *
     * @param int $num 减少数量
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function decreaseStock(int $num)
    {
        if ($num <= 0) {
            return false;
        }

        // 使用数据库事务和悲观锁保证库存操作的原子性
        $result = self::where('id', $this->id)
            ->where('stock', '>=', $num)
            ->lock(true)
            ->update([
                'stock' => ['dec', $num],
                'sales' => ['inc', $num]
            ]);

        if ($result) {
            // 更新商品总销量和总库存
            Goods::where('id', $this->goods_id)->update([
                'sales' => ['inc', $num],
                'stock' => ['dec', $num]
            ]);

            // 重新加载数据
            $this->refresh();
            return true;
        }

        return false;
    }

    /**
     * 增加库存
     *
     * @param int $num 增加数量
     * @return bool
     */
    public function increaseStock(int $num)
    {
        if ($num <= 0) {
            return false;
        }

        // 使用数据库事务和悲观锁保证库存操作的原子性
        $result = self::where('id', $this->id)
            ->lock(true)
            ->update([
                'stock' => ['inc', $num],
                'sales' => ['dec', min($num, $this->sales)]
            ]);

        if ($result) {
            // 更新商品总销量和总库存
            Goods::where('id', $this->goods_id)->update([
                'stock' => ['inc', $num],
                'sales' => ['dec', min($num, $this->goods->sales)]
            ]);

            // 重新加载数据
            $this->refresh();
            return true;
        }

        return false;
    }
}