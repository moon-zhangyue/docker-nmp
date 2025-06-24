<?php
declare(strict_types=1);

namespace app\validate\pg;

use think\Validate;

/**
 * 商品验证器
 */
class GoodsValidate extends Validate
{
    /**
     * 验证规则
     *
     * @var array
     */
    protected $rule = [
        'id'          => 'require|number|gt:0',
        'page'        => 'number|gt:0',
        'limit'       => 'number|between:1,100',
        'category_id' => 'number|egt:0',
        'brand_id'    => 'number|gt:0',
        'keyword'     => 'length:1,50',
        'sort'        => 'in:price,sales,new',
        'order'       => 'in:asc,desc',
        'on_sale'     => 'boolean',
    ];

    /**
     * 错误消息
     *
     * @var array
     */
    protected $message = [
        'id.require'      => '商品ID不能为空',
        'id.number'       => '商品ID必须是数字',
        'id.gt'           => '商品ID必须大于0',
        'page.number'     => '页码必须是数字',
        'page.gt'         => '页码必须大于0',
        'limit.number'    => '每页数量必须是数字',
        'limit.between'   => '每页数量必须在1-100之间',
        'category_id.number' => '分类ID必须是数字',
        'category_id.egt' => '分类ID必须大于等于0',
        'brand_id.number' => '品牌ID必须是数字',
        'brand_id.gt'     => '品牌ID必须大于0',
        'keyword.length'  => '关键词长度必须在1-50个字符之间',
        'sort.in'         => '排序字段只能是price,sales,new',
        'order.in'        => '排序方式只能是asc,desc',
        'on_sale.boolean' => '上架状态必须是布尔值',
    ];

    /**
     * 验证场景
     *
     * @var array
     */
    protected $scene = [
        'list'    => ['page', 'limit', 'category_id', 'brand_id', 'keyword', 'sort', 'order', 'on_sale'],
        'detail'  => ['id'],
        'sku'     => ['id'],
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