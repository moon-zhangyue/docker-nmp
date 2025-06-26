<?php
declare(strict_types=1);

use think\migration\Migrator;
use think\migration\db\Column;

class CreatePgsqlTables extends Migrator
{
    /**
     * 执行迁移
     */
    public function up()
    {
        // 创建用户表
        $this->table('users', ['id' => false, 'engine' => 'InnoDB'])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false, 'comment' => '用户ID'])
            ->addColumn('username', 'string', ['limit' => 50, 'null' => false, 'comment' => '用户名'])
            ->addColumn('password', 'string', ['limit' => 255, 'null' => false, 'comment' => '密码'])
            ->addColumn('email', 'string', ['limit' => 100, 'null' => false, 'comment' => '邮箱'])
            ->addColumn('mobile', 'string', ['limit' => 20, 'null' => true, 'comment' => '手机号'])
            ->addColumn('avatar', 'string', ['limit' => 255, 'null' => true, 'comment' => '头像'])
            ->addColumn('nickname', 'string', ['limit' => 50, 'null' => true, 'comment' => '昵称'])
            ->addColumn('gender', 'integer', ['limit' => 1, 'default' => 0, 'comment' => '性别(0:未知,1:男,2:女)'])
            ->addColumn('status', 'integer', ['limit' => 1, 'default' => 1, 'comment' => '状态(0:禁用,1:正常)'])
            ->addColumn('last_login_ip', 'string', ['limit' => 50, 'null' => true, 'comment' => '最后登录IP'])
            ->addColumn('last_login_time', 'timestamp', ['null' => true, 'comment' => '最后登录时间'])
            ->addColumn('create_time', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'comment' => '创建时间'])
            ->addColumn('update_time', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'comment' => '更新时间'])
            ->addColumn('delete_time', 'timestamp', ['null' => true, 'comment' => '删除时间'])
            ->addIndex(['username'], ['unique' => true, 'name' => 'idx_users_username'])
            ->addIndex(['email'], ['unique' => true, 'name' => 'idx_users_email'])
            ->addIndex(['mobile'], ['unique' => true, 'name' => 'idx_users_mobile'])
            ->addIndex(['status'], ['name' => 'idx_users_status'])
            ->setPrimaryKey(['id'])
            ->create();

        // 创建用户地址表
        $this->table('user_addresses', ['id' => false, 'engine' => 'InnoDB'])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false, 'comment' => '地址ID'])
            ->addColumn('user_id', 'biginteger', ['signed' => false, 'null' => false, 'comment' => '用户ID'])
            ->addColumn('name', 'string', ['limit' => 50, 'null' => false, 'comment' => '收货人姓名'])
            ->addColumn('mobile', 'string', ['limit' => 20, 'null' => false, 'comment' => '收货人手机号'])
            ->addColumn('province', 'string', ['limit' => 50, 'null' => false, 'comment' => '省份'])
            ->addColumn('city', 'string', ['limit' => 50, 'null' => false, 'comment' => '城市'])
            ->addColumn('district', 'string', ['limit' => 50, 'null' => false, 'comment' => '区/县'])
            ->addColumn('detail', 'string', ['limit' => 255, 'null' => false, 'comment' => '详细地址'])
            ->addColumn('is_default', 'boolean', ['default' => false, 'comment' => '是否默认地址'])
            ->addColumn('create_time', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'comment' => '创建时间'])
            ->addColumn('update_time', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'comment' => '更新时间'])
            ->addColumn('delete_time', 'timestamp', ['null' => true, 'comment' => '删除时间'])
            ->addIndex(['user_id'], ['name' => 'idx_user_addresses_user_id'])
            ->addIndex(['is_default'], ['name' => 'idx_user_addresses_is_default'])
            ->setPrimaryKey(['id'])
            ->create();

        // 创建商品分类表
        $this->table('categories', ['id' => false, 'engine' => 'InnoDB'])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false, 'comment' => '分类ID'])
            ->addColumn('parent_id', 'biginteger', ['signed' => false, 'default' => 0, 'comment' => '父级分类ID'])
            ->addColumn('name', 'string', ['limit' => 50, 'null' => false, 'comment' => '分类名称'])
            ->addColumn('sort', 'integer', ['default' => 0, 'comment' => '排序'])
            ->addColumn('status', 'boolean', ['default' => true, 'comment' => '状态(false:禁用,true:正常)'])
            ->addColumn('create_time', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'comment' => '创建时间'])
            ->addColumn('update_time', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'comment' => '更新时间'])
            ->addColumn('delete_time', 'timestamp', ['null' => true, 'comment' => '删除时间'])
            ->addIndex(['parent_id'], ['name' => 'idx_categories_parent_id'])
            ->addIndex(['sort'], ['name' => 'idx_categories_sort'])
            ->addIndex(['status'], ['name' => 'idx_categories_status'])
            ->setPrimaryKey(['id'])
            ->create();

        // 创建商品品牌表
        $this->table('brands', ['id' => false, 'engine' => 'InnoDB'])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false, 'comment' => '品牌ID'])
            ->addColumn('name', 'string', ['limit' => 50, 'null' => false, 'comment' => '品牌名称'])
            ->addColumn('logo', 'string', ['limit' => 255, 'null' => true, 'comment' => '品牌Logo'])
            ->addColumn('description', 'text', ['null' => true, 'comment' => '品牌描述'])
            ->addColumn('sort', 'integer', ['default' => 0, 'comment' => '排序'])
            ->addColumn('status', 'boolean', ['default' => true, 'comment' => '状态(false:禁用,true:正常)'])
            ->addColumn('create_time', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'comment' => '创建时间'])
            ->addColumn('update_time', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'comment' => '更新时间'])
            ->addColumn('delete_time', 'timestamp', ['null' => true, 'comment' => '删除时间'])
            ->addIndex(['name'], ['name' => 'idx_brands_name'])
            ->addIndex(['sort'], ['name' => 'idx_brands_sort'])
            ->addIndex(['status'], ['name' => 'idx_brands_status'])
            ->setPrimaryKey(['id'])
            ->create();

        // 创建商品SPU表(标准产品单位)
        $this->table('goods', ['id' => false, 'engine' => 'InnoDB'])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false, 'comment' => '商品ID'])
            ->addColumn('category_id', 'biginteger', ['signed' => false, 'null' => false, 'comment' => '分类ID'])
            ->addColumn('brand_id', 'biginteger', ['signed' => false, 'null' => true, 'comment' => '品牌ID'])
            ->addColumn('name', 'string', ['limit' => 100, 'null' => false, 'comment' => '商品名称'])
            ->addColumn('sub_title', 'string', ['limit' => 200, 'null' => true, 'comment' => '副标题'])
            ->addColumn('cover', 'string', ['limit' => 255, 'null' => true, 'comment' => '封面图'])
            ->addColumn('pictures', 'json', ['null' => true, 'comment' => '商品图片'])
            ->addColumn('description', 'text', ['null' => true, 'comment' => '商品描述'])
            ->addColumn('detail', 'text', ['null' => true, 'comment' => '商品详情'])
            ->addColumn('on_sale', 'boolean', ['default' => false, 'comment' => '是否上架'])
            ->addColumn('is_recommend', 'boolean', ['default' => false, 'comment' => '是否推荐'])
            ->addColumn('is_hot', 'boolean', ['default' => false, 'comment' => '是否热门'])
            ->addColumn('is_new', 'boolean', ['default' => false, 'comment' => '是否新品'])
            ->addColumn('sales', 'integer', ['default' => 0, 'comment' => '销量'])
            ->addColumn('stock', 'integer', ['default' => 0, 'comment' => '总库存'])
            ->addColumn('sort', 'integer', ['default' => 0, 'comment' => '排序'])
            ->addColumn('status', 'boolean', ['default' => true, 'comment' => '状态(false:禁用,true:正常)'])
            ->addColumn('create_time', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'comment' => '创建时间'])
            ->addColumn('update_time', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'comment' => '更新时间'])
            ->addColumn('delete_time', 'timestamp', ['null' => true, 'comment' => '删除时间'])
            ->addIndex(['category_id'], ['name' => 'idx_goods_category_id'])
            ->addIndex(['brand_id'], ['name' => 'idx_goods_brand_id'])
            ->addIndex(['name'], ['name' => 'idx_goods_name'])
            ->addIndex(['on_sale'], ['name' => 'idx_goods_on_sale'])
            ->addIndex(['is_recommend'], ['name' => 'idx_goods_is_recommend'])
            ->addIndex(['is_hot'], ['name' => 'idx_goods_is_hot'])
            ->addIndex(['is_new'], ['name' => 'idx_goods_is_new'])
            ->addIndex(['sales'], ['name' => 'idx_goods_sales'])
            ->addIndex(['stock'], ['name' => 'idx_goods_stock'])
            ->addIndex(['sort'], ['name' => 'idx_goods_sort'])
            ->addIndex(['status'], ['name' => 'idx_goods_status'])
            ->setPrimaryKey(['id'])
            ->create();

        // 创建商品SKU表(库存单位)
        $this->table('goods_skus', ['id' => false, 'engine' => 'InnoDB'])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false, 'comment' => 'SKU ID'])
            ->addColumn('goods_id', 'biginteger', ['signed' => false, 'null' => false, 'comment' => '商品ID'])
            ->addColumn('code', 'string', ['limit' => 100, 'null' => true, 'comment' => 'SKU编码'])
            ->addColumn('name', 'string', ['limit' => 100, 'null' => false, 'comment' => 'SKU名称'])
            ->addColumn('image', 'string', ['limit' => 255, 'null' => true, 'comment' => 'SKU图片'])
            ->addColumn('specs', 'json', ['null' => true, 'comment' => '规格属性'])
            ->addColumn('price', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '售价'])
            ->addColumn('original_price', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '原价'])
            ->addColumn('cost_price', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '成本价'])
            ->addColumn('stock', 'integer', ['default' => 0, 'comment' => '库存'])
            ->addColumn('sales', 'integer', ['default' => 0, 'comment' => '销量'])
            ->addColumn('status', 'boolean', ['default' => true, 'comment' => '状态(false:禁用,true:正常)'])
            ->addColumn('create_time', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'comment' => '创建时间'])
            ->addColumn('update_time', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'comment' => '更新时间'])
            ->addColumn('delete_time', 'timestamp', ['null' => true, 'comment' => '删除时间'])
            ->addIndex(['goods_id'], ['name' => 'idx_goods_skus_goods_id'])
            ->addIndex(['code'], ['unique' => true, 'name' => 'idx_goods_skus_code'])
            ->addIndex(['price'], ['name' => 'idx_goods_skus_price'])
            ->addIndex(['stock'], ['name' => 'idx_goods_skus_stock'])
            ->addIndex(['status'], ['name' => 'idx_goods_skus_status'])
            ->setPrimaryKey(['id'])
            ->create();

        // 创建商品规格表
        $this->table('specs', ['id' => false, 'engine' => 'InnoDB'])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false, 'comment' => '规格ID'])
            ->addColumn('name', 'string', ['limit' => 50, 'null' => false, 'comment' => '规格名称'])
            ->addColumn('sort', 'integer', ['default' => 0, 'comment' => '排序'])
            ->addColumn('status', 'boolean', ['default' => true, 'comment' => '状态(false:禁用,true:正常)'])
            ->addColumn('create_time', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'comment' => '创建时间'])
            ->addColumn('update_time', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'comment' => '更新时间'])
            ->addColumn('delete_time', 'timestamp', ['null' => true, 'comment' => '删除时间'])
            ->addIndex(['name'], ['unique' => true, 'name' => 'idx_specs_name'])
            ->addIndex(['sort'], ['name' => 'idx_specs_sort'])
            ->setPrimaryKey(['id'])
            ->create();

        // 创建商品规格值表
        $this->table('spec_values', ['id' => false, 'engine' => 'InnoDB'])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false, 'comment' => '规格值ID'])
            ->addColumn('spec_id', 'biginteger', ['signed' => false, 'null' => false, 'comment' => '规格ID'])
            ->addColumn('value', 'string', ['limit' => 50, 'null' => false, 'comment' => '规格值'])
            ->addColumn('sort', 'integer', ['default' => 0, 'comment' => '排序'])
            ->addColumn('status', 'boolean', ['default' => true, 'comment' => '状态(false:禁用,true:正常)'])
            ->addColumn('create_time', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'comment' => '创建时间'])
            ->addColumn('update_time', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'comment' => '更新时间'])
            ->addColumn('delete_time', 'timestamp', ['null' => true, 'comment' => '删除时间'])
            ->addIndex(['spec_id'], ['name' => 'idx_spec_values_spec_id'])
            ->addIndex(['sort'], ['name' => 'idx_spec_values_sort'])
            ->setPrimaryKey(['id'])
            ->create();

        // 创建购物车表
        $this->table('carts', ['id' => false, 'engine' => 'InnoDB'])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false, 'comment' => '购物车ID'])
            ->addColumn('user_id', 'biginteger', ['signed' => false, 'null' => false, 'comment' => '用户ID'])
            ->addColumn('sku_id', 'biginteger', ['signed' => false, 'null' => false, 'comment' => 'SKU ID'])
            ->addColumn('quantity', 'integer', ['default' => 1, 'comment' => '数量'])
            ->addColumn('selected', 'boolean', ['default' => true, 'comment' => '是否选中'])
            ->addColumn('create_time', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'comment' => '创建时间'])
            ->addColumn('update_time', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'comment' => '更新时间'])
            ->addIndex(['user_id', 'sku_id'], ['unique' => true, 'name' => 'idx_carts_user_sku'])
            ->addIndex(['selected'], ['name' => 'idx_carts_selected'])
            ->setPrimaryKey(['id'])
            ->create();

        // 创建订单表
        $this->table('orders', ['id' => false, 'engine' => 'InnoDB'])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false, 'comment' => '订单ID'])
            ->addColumn('order_no', 'string', ['limit' => 50, 'null' => false, 'comment' => '订单编号'])
            ->addColumn('user_id', 'biginteger', ['signed' => false, 'null' => false, 'comment' => '用户ID'])
            ->addColumn('total_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '订单总金额'])
            ->addColumn('pay_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '实付金额'])
            ->addColumn('discount_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '优惠金额'])
            ->addColumn('freight_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '运费金额'])
            ->addColumn('status', 'integer', ['limit' => 1, 'default' => 0, 'comment' => '订单状态(0:待付款,1:待发货,2:待收货,3:已完成,4:已关闭,5:已取消)'])
            ->addColumn('pay_status', 'integer', ['limit' => 1, 'default' => 0, 'comment' => '支付状态(0:未支付,1:已支付)'])
            ->addColumn('pay_method', 'integer', ['limit' => 1, 'null' => true, 'comment' => '支付方式(1:支付宝,2:微信,3:银行卡)'])
            ->addColumn('pay_time', 'timestamp', ['null' => true, 'comment' => '支付时间'])
            ->addColumn('ship_status', 'integer', ['limit' => 1, 'default' => 0, 'comment' => '发货状态(0:未发货,1:已发货,2:已收货)'])
            ->addColumn('ship_time', 'timestamp', ['null' => true, 'comment' => '发货时间'])
            ->addColumn('delivery_company', 'string', ['limit' => 50, 'null' => true, 'comment' => '物流公司'])
            ->addColumn('delivery_no', 'string', ['limit' => 50, 'null' => true, 'comment' => '物流单号'])
            ->addColumn('receiver_name', 'string', ['limit' => 50, 'null' => false, 'comment' => '收货人姓名'])
            ->addColumn('receiver_mobile', 'string', ['limit' => 20, 'null' => false, 'comment' => '收货人手机号'])
            ->addColumn('receiver_province', 'string', ['limit' => 50, 'null' => false, 'comment' => '省份'])
            ->addColumn('receiver_city', 'string', ['limit' => 50, 'null' => false, 'comment' => '城市'])
            ->addColumn('receiver_district', 'string', ['limit' => 50, 'null' => false, 'comment' => '区/县'])
            ->addColumn('receiver_address', 'string', ['limit' => 255, 'null' => false, 'comment' => '详细地址'])
            ->addColumn('note', 'string', ['limit' => 255, 'null' => true, 'comment' => '订单备注'])
            ->addColumn('confirm_time', 'timestamp', ['null' => true, 'comment' => '确认收货时间'])
            ->addColumn('cancel_time', 'timestamp', ['null' => true, 'comment' => '取消时间'])
            ->addColumn('cancel_reason', 'string', ['limit' => 255, 'null' => true, 'comment' => '取消原因'])
            ->addColumn('create_time', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'comment' => '创建时间'])
            ->addColumn('update_time', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'comment' => '更新时间'])
            ->addColumn('delete_time', 'timestamp', ['null' => true, 'comment' => '删除时间'])
            ->addIndex(['order_no'], ['unique' => true, 'name' => 'idx_orders_order_no'])
            ->addIndex(['user_id'], ['name' => 'idx_orders_user_id'])
            ->addIndex(['status'], ['name' => 'idx_orders_status'])
            ->addIndex(['pay_status'], ['name' => 'idx_orders_pay_status'])
            ->addIndex(['ship_status'], ['name' => 'idx_orders_ship_status'])
            ->setPrimaryKey(['id'])
            ->create();

        // 创建订单商品表
        $this->table('order_items', ['id' => false, 'engine' => 'InnoDB'])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false, 'comment' => '订单商品ID'])
            ->addColumn('order_id', 'biginteger', ['signed' => false, 'null' => false, 'comment' => '订单ID'])
            ->addColumn('order_no', 'string', ['limit' => 50, 'null' => false, 'comment' => '订单编号'])
            ->addColumn('goods_id', 'biginteger', ['signed' => false, 'null' => false, 'comment' => '商品ID'])
            ->addColumn('sku_id', 'biginteger', ['signed' => false, 'null' => false, 'comment' => 'SKU ID'])
            ->addColumn('goods_name', 'string', ['limit' => 100, 'null' => false, 'comment' => '商品名称'])
            ->addColumn('sku_name', 'string', ['limit' => 100, 'null' => false, 'comment' => 'SKU名称'])
            ->addColumn('image', 'string', ['limit' => 255, 'null' => true, 'comment' => '商品图片'])
            ->addColumn('specs', 'json', ['null' => true, 'comment' => '规格属性'])
            ->addColumn('price', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '商品单价'])
            ->addColumn('quantity', 'integer', ['default' => 1, 'comment' => '商品数量'])
            ->addColumn('total_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '商品总金额'])
            ->addColumn('create_time', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'comment' => '创建时间'])
            ->addColumn('update_time', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'comment' => '更新时间'])
            ->addIndex(['order_id'], ['name' => 'idx_order_items_order_id'])
            ->addIndex(['order_no'], ['name' => 'idx_order_items_order_no'])
            ->addIndex(['goods_id'], ['name' => 'idx_order_items_goods_id'])
            ->addIndex(['sku_id'], ['name' => 'idx_order_items_sku_id'])
            ->setPrimaryKey(['id'])
            ->create();

        // 创建订单日志表
        $this->table('order_logs', ['id' => false, 'engine' => 'InnoDB'])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false, 'comment' => '日志ID'])
            ->addColumn('order_id', 'biginteger', ['signed' => false, 'null' => false, 'comment' => '订单ID'])
            ->addColumn('order_no', 'string', ['limit' => 50, 'null' => false, 'comment' => '订单编号'])
            ->addColumn('user_id', 'biginteger', ['signed' => false, 'null' => true, 'comment' => '用户ID'])
            ->addColumn('type', 'integer', ['limit' => 1, 'default' => 0, 'comment' => '日志类型(0:系统,1:用户)'])
            ->addColumn('action', 'string', ['limit' => 50, 'null' => false, 'comment' => '操作行为'])
            ->addColumn('content', 'string', ['limit' => 255, 'null' => false, 'comment' => '日志内容'])
            ->addColumn('ip', 'string', ['limit' => 50, 'null' => true, 'comment' => 'IP地址'])
            ->addColumn('create_time', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'comment' => '创建时间'])
            ->addIndex(['order_id'], ['name' => 'idx_order_logs_order_id'])
            ->addIndex(['order_no'], ['name' => 'idx_order_logs_order_no'])
            ->addIndex(['user_id'], ['name' => 'idx_order_logs_user_id'])
            ->addIndex(['type'], ['name' => 'idx_order_logs_type'])
            ->setPrimaryKey(['id'])
            ->create();
    }

    /**
     * 回滚迁移
     */
    public function down()
    {
        $this->dropTable('order_logs');
        $this->dropTable('order_items');
        $this->dropTable('orders');
        $this->dropTable('carts');
        $this->dropTable('spec_values');
        $this->dropTable('specs');
        $this->dropTable('goods_skus');
        $this->dropTable('goods');
        $this->dropTable('brands');
        $this->dropTable('categories');
        $this->dropTable('user_addresses');
        $this->dropTable('users');
    }
}