<?php
declare(strict_types=1);

namespace app\validate\pg;

use think\Validate;

/**
 * 购物车验证器
 */
class CartValidate extends Validate
{
    /**
     * 验证规则
     *
     * @var array
     */
    protected $rule = [
        'sku_id'   => 'require|number|gt:0',
        'quantity' => 'require|number|between:1,100',
        'selected' => 'require|boolean',
    ];

    /**
     * 错误消息
     *
     * @var array
     */
    protected $message = [
        'sku_id.require'   => '商品规格ID不能为空',
        'sku_id.number'    => '商品规格ID必须是数字',
        'sku_id.gt'        => '商品规格ID必须大于0',
        'quantity.require' => '商品数量不能为空',
        'quantity.number'  => '商品数量必须是数字',
        'quantity.between' => '商品数量必须在1-100之间',
        'selected.require' => '选中状态不能为空',
        'selected.boolean' => '选中状态必须是布尔值',
    ];

    /**
     * 验证场景
     *
     * @var array
     */
    protected $scene = [
        'add'             => ['sku_id', 'quantity'],
        'update_quantity' => ['quantity'],
        'update_selected' => ['selected'],
        'select_all'      => ['selected'],
    ];

    /**
     * 构造函数
     */
    public function __construct()
    {
        parent::__construct();

        // 设置数据表连接
        $this->db('postgresql');
    }
} 