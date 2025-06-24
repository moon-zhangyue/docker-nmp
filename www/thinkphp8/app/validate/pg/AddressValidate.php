<?php
declare(strict_types=1);

namespace app\validate\pg;

use think\Validate;

/**
 * 地址验证器
 */
class AddressValidate extends Validate
{
    /**
     * 验证规则
     *
     * @var array
     */
    protected $rule = [
        'name'      => 'require|chsAlphaNum|length:2,50',
        'mobile'    => 'require|mobile',
        'province'  => 'require|chsAlpha|length:2,50',
        'city'      => 'require|chsAlpha|length:2,50',
        'district'  => 'require|chsAlpha|length:2,50',
        'detail'    => 'require|length:5,255',
        'is_default'=> 'boolean',
    ];

    /**
     * 错误消息
     *
     * @var array
     */
    protected $message = [
        'name.require'      => '收货人姓名不能为空',
        'name.chsAlphaNum'  => '收货人姓名只能是汉字、字母和数字',
        'name.length'       => '收货人姓名长度必须在2-50个字符之间',
        'mobile.require'    => '手机号不能为空',
        'mobile.mobile'     => '手机号格式不正确',
        'province.require'  => '省份不能为空',
        'province.chsAlpha' => '省份只能是汉字和字母',
        'province.length'   => '省份长度必须在2-50个字符之间',
        'city.require'      => '城市不能为空',
        'city.chsAlpha'     => '城市只能是汉字和字母',
        'city.length'       => '城市长度必须在2-50个字符之间',
        'district.require'  => '区/县不能为空',
        'district.chsAlpha' => '区/县只能是汉字和字母',
        'district.length'   => '区/县长度必须在2-50个字符之间',
        'detail.require'    => '详细地址不能为空',
        'detail.length'     => '详细地址长度必须在5-255个字符之间',
        'is_default.boolean'=> '是否默认地址必须是布尔值',
    ];

    /**
     * 验证场景
     *
     * @var array
     */
    protected $scene = [
        'add'    => ['name', 'mobile', 'province', 'city', 'district', 'detail', 'is_default'],
        'update' => ['name', 'mobile', 'province', 'city', 'district', 'detail', 'is_default'],
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