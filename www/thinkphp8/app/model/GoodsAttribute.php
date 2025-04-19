<?php
declare(strict_types=1);

namespace app\model;

use think\Model;
use think\facade\Queue;
use think\facade\Cache;

class GoodsAttribute extends Model
{
    protected $name = 'goods_attribute';

    // 关联 SPU
    public function spu()
    {
        return $this->belongsTo(GoodsSpu::class, 'spu_id', 'id');
    }

    protected static function boot()
    {
        parent::boot();

        // 创建属性时，更新相关 SPU 的所有 SKU 索引
        static::created(function ($attribute) {
            $spu = $attribute->spu;
            if ($spu) {
                Cache::deletePattern('goods_search_*'); // 清除搜索缓存
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
            }
        });

        // 更新属性时，更新相关 SPU 的所有 SKU 索引
        static::updated(function ($attribute) {
            $spu = $attribute->spu;
            if ($spu) {
                Cache::deletePattern('goods_search_*'); // 清除搜索缓存
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
            }
        });

        // 删除属性时，更新相关 SPU 的所有 SKU 索引
        static::deleted(function ($attribute) {
            $spu = $attribute->spu;
            if ($spu) {
                Cache::deletePattern('goods_search_*'); // 清除搜索缓存
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
            }
        });
    }
}