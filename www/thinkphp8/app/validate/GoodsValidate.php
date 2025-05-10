<?php
declare(strict_types=1);

namespace app\validate;

use think\Validate;

class GoodsValidate extends Validate
{
    /**
     * 验证规则
     */
    protected $rule = [
        'id'          => 'integer|min:1',
        'name'        => 'require|string|max:255',
        'description' => 'string|max:1000',
        'category_id' => 'integer|min:0',
        'brand_id'    => 'integer|min:0',
        'skus'        => 'array|require',
        'attributes'  => 'array',
    ];

    /**
     * 错误消息
     */
    protected $message = [
        'id.integer'          => 'ID必须是整数',
        'id.min'              => 'ID必须大于0',
        'name.require'        => '商品名称不能为空',
        'name.max'            => '商品名称不能超过255个字符',
        'description.max'     => '商品描述不能超过1000个字符',
        'category_id.integer' => '分类ID必须是整数',
        'category_id.min'     => '分类ID不能小于0',
        'brand_id.integer'    => '品牌ID必须是整数',
        'brand_id.min'        => '品牌ID不能小于0',
        'skus.require'        => '至少需要一个SKU',
        'skus.array'          => 'SKU数据必须是数组',
        'attributes.array'    => '属性数据必须是数组',
    ];

    /**
     * 验证场景
     */
    protected $scene = [
        'create' => ['name', 'description', 'category_id', 'brand_id', 'skus', 'attributes'],
        'update' => ['id', 'name', 'description', 'category_id', 'brand_id', 'skus', 'attributes'],
    ];
    
    /**
     * 验证SKU数据
     * 
     * @param array $value SKU数据
     * @param mixed $rule 规则
     * @param string $data 完整数据
     * @return bool|string
     */
    protected function checkSkus($value, $rule, $data)
    {
        if (!is_array($value)) {
            return 'SKU数据必须是数组';
        }
        
        if (empty($value)) {
            return '至少需要一个SKU';
        }
        
        foreach ($value as $sku) {
            if (!isset($sku['price']) || !is_numeric($sku['price']) || $sku['price'] < 0) {
                return 'SKU价格必须是大于等于0的数字';
            }
            
            if (!isset($sku['stock']) || !is_numeric($sku['stock']) || $sku['stock'] < 0) {
                return 'SKU库存必须是大于等于0的整数';
            }
            
            if (isset($sku['attributes']) && !is_array($sku['attributes'])) {
                return 'SKU属性必须是数组';
            }
        }
        
        return true;
    }
    
    /**
     * 验证属性数据
     * 
     * @param array $value 属性数据
     * @param mixed $rule 规则
     * @param string $data 完整数据
     * @return bool|string
     */
    protected function checkAttributes($value, $rule, $data)
    {
        if (!is_array($value)) {
            return '属性数据必须是数组';
        }
        
        foreach ($value as $attr) {
            if (!isset($attr['name']) || empty($attr['name'])) {
                return '属性名称不能为空';
            }
            
            if (!isset($attr['value']) || empty($attr['value'])) {
                return '属性值不能为空';
            }
        }
        
        return true;
    }
}