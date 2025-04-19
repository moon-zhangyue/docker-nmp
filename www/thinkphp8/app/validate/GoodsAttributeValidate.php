<?php
declare(strict_types=1);

namespace app\validate;

use think\Validate;

class GoodsAttributeValidate extends Validate
{
    protected $rule = [
        'spu_id' => 'require|integer|min:1',
        'name'   => 'require|max:50',
        'value'  => 'require|max:255',
    ];

    protected $message = [
        'spu_id.require' => 'SPU ID 不能为空',
        'name.require'   => '属性名不能为空',
        'name.max'       => '属性名长度不能超过 50',
        'value.require'  => '属性值不能为空',
        'value.max'      => '属性值长度不能超过 255',
    ];
}