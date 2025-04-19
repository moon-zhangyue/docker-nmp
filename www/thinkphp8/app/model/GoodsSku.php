<?php
declare(strict_types=1);

namespace app\model;

use think\Model;
use think\facade\Queue;

class GoodsSku extends Model
{
    protected $name = 'goods_sku';

    public function spu()
    {
        return $this->belongsTo(GoodsSpu::class, 'spu_id', 'id');
    }

    protected static function boot()
    {
        parent::boot();

        static::updated(function ($sku) {
            $spu        = $sku->spu;
            $attributes = $spu->attributes()->select();
            Queue::push('app\job\IndexGoodsJob', [
                'spu_id'            => $spu->id,
                'sku_id'            => $sku->id,
                'name'              => $spu->name,
                'description'       => $spu->description,
                'category_id'       => $spu->category_id,
                'brand_id'          => $spu->brand_id,
                'price'             => $sku->price,
                'stock'             => $sku->stock,
                'sku_attributes'    => $sku->attributes,
                'common_attributes' => $attributes->toArray(),
                'status'            => $sku->status,
                'created_at'        => $spu->created_at,
            ]);
        });

        static::deleted(function ($sku) {
            Queue::push('app\job\DeleteGoodsJob', [
                'sku_id' => $sku->id,
            ]);
        });
    }
}