<?php

namespace app\validate;

use think\Validate;

class GoodsSearchValidate extends Validate
{
    protected $rule = [
        'query'             => 'max:255',
        'category_id'       => 'integer|min:0',
        'min_price'         => 'float|min:0',
        'max_price'         => 'float|min:0',
        'sku_attributes'    => 'array',
        'common_attributes' => 'array',
        'sort'              => 'in:price,stock,created_at',
        'order'             => 'in:asc,desc',
        'from'              => 'integer|min:0',
        'size'              => 'integer|between:1,100',
    ];

    protected $message = [
        'sort.in'      => '排序字段必须是 price, stock 或 created_at',
        'order.in'     => '排序顺序必须是 asc 或 desc',
        'size.between' => '每页数量必须在 1 到 100 之间',
    ];
}