<?php
declare(strict_types=1);

namespace app\model;

use think\Model;
use think\facade\Queue;
use app\model\GoodsSku;
use app\model\GoodsAttribute;

class GoodsSpu extends Model
{
    protected $name = 'goods_spu';

    public function skus()
    {
        return $this->hasMany(GoodsSku::class, 'spu_id', 'id');
    }

    public function attributes()
    {
        return $this->hasMany(GoodsAttribute::class, 'spu_id', 'id');
    }

    protected static function boot()
    {
        parent::boot();

        static::created(function ($spu) {
            $skus       = $spu->skus()->select();
            $attributes = $spu->attributes()->select();
            foreach ($skus as $sku) {
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
            }
        });

        static::updated(function ($spu) {
            $skus       = $spu->skus()->select();
            $attributes = $spu->attributes()->select();
            foreach ($skus as $sku) {
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
            }
        });

        static::deleted(function ($spu) {
            $skus = $spu->skus()->select();
            foreach ($skus as $sku) {
                Queue::push('app\job\DeleteGoodsJob', [
                    'sku_id' => $sku->id,
                ]);
            }
        });
    }
}