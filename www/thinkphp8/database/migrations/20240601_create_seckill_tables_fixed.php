<?php
declare(strict_types=1);

use think\migration\Migrator;
use think\migration\db\Column;

/**
 * 秒杀活动相关数据表迁移
 * 
 * 包含三个表：
 * 1. seckill_activity - 秒杀活动主表
 * 2. seckill_goods - 秒杀商品表
 * 3. seckill_order - 秒杀订单表
 */
class CreateSeckillTablesFixed extends Migrator
{
    /**
     * 执行迁移
     */
    public function change()
    {
        // 创建秒杀活动表
        $this->createSeckillActivityTable();
        
        // 创建秒杀商品表
        $this->createSeckillGoodsTable();
        
        // 创建秒杀订单表
        $this->createSeckillOrderTable();
    }
    
    /**
     * 创建秒杀活动表
     */
    private function createSeckillActivityTable()
    {
        $table = $this->table('seckill_activity', [
            'engine'  => 'InnoDB',
            'comment' => '秒杀活动表'
        ]);
        
        $table
            ->addColumn('title', 'string', [
                'limit'   => 100, 
                'null'    => false, 
                'comment' => '活动标题'
            ])
            ->addColumn('description', 'text', [
                'null'    => true, 
                'comment' => '活动描述'
            ])
            ->addColumn('start_time', 'integer', [
                'null'    => false, 
                'comment' => '活动开始时间（Unix时间戳）'
            ])
            ->addColumn('end_time', 'integer', [
                'null'    => false, 
                'comment' => '活动结束时间（Unix时间戳）'
            ])
            ->addColumn('status', 'integer', [
                'limit'   => 1, 
                'default' => 0, 
                'null'    => false, 
                'comment' => '活动状态：0-未开始，1-进行中，2-已结束，3-已取消'
            ])
            ->addColumn('rules', 'text', [
                'null'    => true, 
                'comment' => '活动规则'
            ])
            ->addColumn('max_buy_limit', 'integer', [
                'default' => 1, 
                'null'    => false, 
                'comment' => '每人最大购买数量限制'
            ])
            ->addColumn('is_featured', 'boolean', [
                'default' => false, 
                'null'    => false, 
                'comment' => '是否推荐活动'
            ])
            ->addColumn('banner_image', 'string', [
                'limit'   => 255, 
                'null'    => true, 
                'comment' => '活动banner图片URL'
            ])
            ->addColumn('created_at', 'integer', [
                'null'    => false, 
                'comment' => '创建时间（Unix时间戳）'
            ])
            ->addColumn('updated_at', 'integer', [
                'null'    => false, 
                'comment' => '更新时间（Unix时间戳）'
            ])
            ->addIndex(['start_time', 'end_time'], [
                'name' => 'idx_time_range'
            ])
            ->addIndex(['status'], [
                'name' => 'idx_status'
            ])
            ->create();
    }
    
    /**
     * 创建秒杀商品表
     */
    private function createSeckillGoodsTable()
    {
        $table = $this->table('seckill_goods', [
            'engine'  => 'InnoDB',
            'comment' => '秒杀商品表'
        ]);
        
        $table
            ->addColumn('activity_id', 'integer', [
                'null'    => false,
                'comment' => '所属秒杀活动ID'
            ])
            ->addColumn('sku_id', 'integer', [
                'null'    => false,
                'comment' => '商品SKU ID'
            ])
            ->addColumn('spu_id', 'integer', [
                'null'    => false,
                'comment' => '商品SPU ID'
            ])
            ->addColumn('original_price', 'decimal', [
                'precision' => 10,
                'scale'     => 2,
                'null'      => false,
                'comment'   => '商品原价'
            ])
            ->addColumn('seckill_price', 'decimal', [
                'precision' => 10,
                'scale'     => 2,
                'null'      => false,
                'comment'   => '秒杀价格'
            ])
            ->addColumn('total_stock', 'integer', [
                'null'    => false,
                'comment' => '秒杀总库存'
            ])
            ->addColumn('remain_stock', 'integer', [
                'null'    => false,
                'comment' => '剩余库存'
            ])
            ->addColumn('limit_per_user', 'integer', [
                'default' => 1,
                'null'    => false,
                'comment' => '每人限购数量'
            ])
            ->addColumn('sort_order', 'integer', [
                'default' => 0,
                'null'    => false,
                'comment' => '排序权重，数值越大越靠前'
            ])
            ->addColumn('status', 'integer', [
                'limit'   => 1,
                'default' => 1,
                'null'    => false,
                'comment' => '状态：0-下架，1-上架'
            ])
            ->addColumn('created_at', 'integer', [
                'null'    => false,
                'comment' => '创建时间（Unix时间戳）'
            ])
            ->addColumn('updated_at', 'integer', [
                'null'    => false,
                'comment' => '更新时间（Unix时间戳）'
            ])
            ->addIndex(['activity_id'], [
                'name' => 'idx_activity_id'
            ])
            ->addIndex(['sku_id'], [
                'name' => 'idx_sku_id'
            ])
            ->addIndex(['spu_id'], [
                'name' => 'idx_spu_id'
            ])
            ->addIndex(['status'], [
                'name' => 'idx_status'
            ])
            ->create();
    }
    
    /**
     * 创建秒杀订单表
     */
    private function createSeckillOrderTable()
    {
        $table = $this->table('seckill_order', [
            'engine'  => 'InnoDB',
            'comment' => '秒杀订单表'
        ]);
        
        $table
            ->addColumn('order_sn', 'string', [
                'limit'   => 64,
                'null'    => false,
                'comment' => '订单编号'
            ])
            ->addColumn('user_id', 'integer', [
                'null'    => false,
                'comment' => '用户ID'
            ])
            ->addColumn('activity_id', 'integer', [
                'null'    => false,
                'comment' => '秒杀活动ID'
            ])
            ->addColumn('goods_id', 'integer', [
                'null'    => false,
                'comment' => '秒杀商品ID'
            ])
            ->addColumn('sku_id', 'integer', [
                'null'    => false,
                'comment' => '商品SKU ID'
            ])
            ->addColumn('quantity', 'integer', [
                'default' => 1,
                'null'    => false,
                'comment' => '购买数量'
            ])
            ->addColumn('price', 'decimal', [
                'precision' => 10,
                'scale'     => 2,
                'null'      => false,
                'comment'   => '秒杀价格'
            ])
            ->addColumn('total_amount', 'decimal', [
                'precision' => 10,
                'scale'     => 2,
                'null'      => false,
                'comment'   => '订单总金额'
            ])
            ->addColumn('status', 'integer', [
                'limit'   => 1,
                'default' => 0,
                'null'    => false,
                'comment' => '订单状态：0-待支付，1-已支付，2-已取消，3-已超时'
            ])
            ->addColumn('payment_time', 'integer', [
                'null'    => true,
                'comment' => '支付时间（Unix时间戳）'
            ])
            ->addColumn('payment_method', 'string', [
                'limit'   => 20,
                'null'    => true,
                'comment' => '支付方式：wechat-微信支付，alipay-支付宝'
            ])
            ->addColumn('transaction_id', 'string', [
                'limit'   => 64,
                'null'    => true,
                'comment' => '支付交易号'
            ])
            ->addColumn('created_at', 'integer', [
                'null'    => false,
                'comment' => '创建时间（Unix时间戳）'
            ])
            ->addColumn('updated_at', 'integer', [
                'null'    => false,
                'comment' => '更新时间（Unix时间戳）'
            ])
            ->addIndex(['order_sn'], [
                'unique' => true,
                'name'   => 'idx_order_sn'
            ])
            ->addIndex(['user_id'], [
                'name' => 'idx_user_id'
            ])
            ->addIndex(['activity_id'], [
                'name' => 'idx_activity_id'
            ])
            ->addIndex(['sku_id'], [
                'name' => 'idx_sku_id'
            ])
            ->addIndex(['status'], [
                'name' => 'idx_status'
            ])
            ->addIndex(['created_at'], [
                'name' => 'idx_created_at'
            ])
            ->create();
    }
}
