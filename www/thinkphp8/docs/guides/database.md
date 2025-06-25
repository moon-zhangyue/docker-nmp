# 数据库设计文档

本文档详细描述了电子商务应用的数据库设计。

## 数据库配置

项目使用PostgreSQL数据库，配置信息如下：

```php
// PostgreSQL连接配置
'postgresql' => [
    // 数据库类型
    'type'                 => 'pgsql',
    // 服务器地址
    'hostname'             => env('pgsql.hostname', 'postgres'),
    // 数据库名
    'database'             => env('pgsql.database', 'tp8'),
    // 用户名
    'username'             => env('pgsql.username', 'postgres'),
    // 密码
    'password'             => env('pgsql.password', '123456'),
    // 端口
    'hostport'             => env('pgsql.hostport', '8432'),
    // 数据库编码默认采用utf8
    'charset'              => env('pgsql.charset', 'utf8'),
    // 数据库表前缀
    'prefix'               => env('pgsql.prefix', ''),
    // Schema配置
    'schema'               => 'public',
    // 是否使用连接池
    'use_pool'             => true,
    // 连接池数量
    'max_connections'      => 50,
]
```

## 数据表设计

### 用户模块

#### 用户表(pg_users)

| 字段名          | 类型         | 长度 | 可空 | 默认值 | 描述        |
|----------------|--------------|-----|------|-------|------------|
| id             | bigserial    |     | 否   |       | 用户ID(主键) |
| username       | varchar      | 50  | 否   |       | 用户名      |
| password       | varchar      | 255 | 否   |       | 密码        |
| nickname       | varchar      | 50  | 是   | NULL  | 昵称        |
| avatar         | varchar      | 255 | 是   | NULL  | 头像URL     |
| mobile         | varchar      | 20  | 是   | NULL  | 手机号      |
| email          | varchar      | 100 | 是   | NULL  | 邮箱        |
| gender         | smallint     |     | 否   | 0     | 性别(0未知,1男,2女) |
| birthday       | date         |     | 是   | NULL  | 生日        |
| last_login_time| timestamp    |     | 是   | NULL  | 最后登录时间 |
| last_login_ip  | varchar      | 50  | 是   | NULL  | 最后登录IP  |
| status         | smallint     |     | 否   | 1     | 状态(0禁用,1正常) |
| create_time    | timestamp    |     | 否   | now() | 创建时间     |
| update_time    | timestamp    |     | 否   | now() | 更新时间     |
| delete_time    | timestamp    |     | 是   | NULL  | 删除时间     |

索引：
- PRIMARY KEY (id)
- UNIQUE INDEX (username)
- UNIQUE INDEX (email) WHERE email IS NOT NULL
- UNIQUE INDEX (mobile) WHERE mobile IS NOT NULL

#### 用户地址表(pg_user_addresses)

| 字段名          | 类型         | 长度 | 可空 | 默认值 | 描述        |
|----------------|--------------|-----|------|-------|------------|
| id             | bigserial    |     | 否   |       | 地址ID(主键) |
| user_id        | bigint       |     | 否   |       | 用户ID      |
| name           | varchar      | 50  | 否   |       | 收货人姓名   |
| mobile         | varchar      | 20  | 否   |       | 手机号      |
| province       | varchar      | 50  | 否   |       | 省份        |
| city           | varchar      | 50  | 否   |       | 城市        |
| district       | varchar      | 50  | 否   |       | 区/县       |
| detail         | varchar      | 255 | 否   |       | 详细地址     |
| is_default     | smallint     |     | 否   | 0     | 是否默认(0否,1是) |
| create_time    | timestamp    |     | 否   | now() | 创建时间     |
| update_time    | timestamp    |     | 否   | now() | 更新时间     |
| delete_time    | timestamp    |     | 是   | NULL  | 删除时间     |

索引：
- PRIMARY KEY (id)
- INDEX (user_id)

### 商品模块

#### 商品分类表(pg_categories)

| 字段名          | 类型         | 长度 | 可空 | 默认值 | 描述        |
|----------------|--------------|-----|------|-------|------------|
| id             | bigserial    |     | 否   |       | 分类ID(主键) |
| parent_id      | bigint       |     | 否   | 0     | 父分类ID    |
| name           | varchar      | 50  | 否   |       | 分类名称     |
| cover          | varchar      | 255 | 是   | NULL  | 分类图片     |
| sort           | int          |     | 否   | 0     | 排序值      |
| is_show        | smallint     |     | 否   | 1     | 是否显示(0否,1是) |
| create_time    | timestamp    |     | 否   | now() | 创建时间     |
| update_time    | timestamp    |     | 否   | now() | 更新时间     |
| delete_time    | timestamp    |     | 是   | NULL  | 删除时间     |

索引：
- PRIMARY KEY (id)
- INDEX (parent_id)

#### 品牌表(pg_brands)

| 字段名          | 类型         | 长度 | 可空 | 默认值 | 描述        |
|----------------|--------------|-----|------|-------|------------|
| id             | bigserial    |     | 否   |       | 品牌ID(主键) |
| name           | varchar      | 50  | 否   |       | 品牌名称     |
| logo           | varchar      | 255 | 是   | NULL  | 品牌Logo    |
| description    | text         |     | 是   | NULL  | 品牌描述     |
| sort           | int          |     | 否   | 0     | 排序值      |
| is_show        | smallint     |     | 否   | 1     | 是否显示(0否,1是) |
| create_time    | timestamp    |     | 否   | now() | 创建时间     |
| update_time    | timestamp    |     | 否   | now() | 更新时间     |
| delete_time    | timestamp    |     | 是   | NULL  | 删除时间     |

索引：
- PRIMARY KEY (id)
- UNIQUE INDEX (name)

#### 商品表(pg_goods)

| 字段名          | 类型         | 长度 | 可空 | 默认值 | 描述        |
|----------------|--------------|-----|------|-------|------------|
| id             | bigserial    |     | 否   |       | 商品ID(主键) |
| category_id    | bigint       |     | 否   |       | 分类ID      |
| brand_id       | bigint       |     | 是   | NULL  | 品牌ID      |
| name           | varchar      | 100 | 否   |       | 商品名称     |
| cover          | varchar      | 255 | 否   |       | 商品主图     |
| images         | text[]       |     | 是   | NULL  | 商品图片数组 |
| price          | decimal      | 10,2| 否   | 0.00  | 销售价格     |
| market_price   | decimal      | 10,2| 否   | 0.00  | 市场价格     |
| stock          | int          |     | 否   | 0     | 库存        |
| sales          | int          |     | 否   | 0     | 销量        |
| description    | text         |     | 是   | NULL  | 商品描述     |
| attributes     | jsonb        |     | 是   | NULL  | 商品属性     |
| is_hot         | smallint     |     | 否   | 0     | 是否热门(0否,1是) |
| is_new         | smallint     |     | 否   | 0     | 是否新品(0否,1是) |
| is_recommend   | smallint     |     | 否   | 0     | 是否推荐(0否,1是) |
| status         | smallint     |     | 否   | 1     | 状态(0下架,1上架) |
| create_time    | timestamp    |     | 否   | now() | 创建时间     |
| update_time    | timestamp    |     | 否   | now() | 更新时间     |
| delete_time    | timestamp    |     | 是   | NULL  | 删除时间     |

索引：
- PRIMARY KEY (id)
- INDEX (category_id)
- INDEX (brand_id)
- INDEX (status)
- INDEX (is_hot)
- INDEX (is_new)
- INDEX (is_recommend)
- GIN (attributes) WHERE attributes IS NOT NULL

#### 商品规格表(pg_goods_skus)

| 字段名          | 类型         | 长度 | 可空 | 默认值 | 描述        |
|----------------|--------------|-----|------|-------|------------|
| id             | bigserial    |     | 否   |       | SKU ID(主键)|
| goods_id       | bigint       |     | 否   |       | 商品ID      |
| spec_json      | jsonb        |     | 否   |       | 规格JSON    |
| price          | decimal      | 10,2| 否   | 0.00  | 价格        |
| stock          | int          |     | 否   | 0     | 库存        |
| sales          | int          |     | 否   | 0     | 销量        |
| code           | varchar      | 50  | 是   | NULL  | 商品编码     |
| image          | varchar      | 255 | 是   | NULL  | 规格图片     |
| status         | smallint     |     | 否   | 1     | 状态(0禁用,1启用) |
| create_time    | timestamp    |     | 否   | now() | 创建时间     |
| update_time    | timestamp    |     | 否   | now() | 更新时间     |
| delete_time    | timestamp    |     | 是   | NULL  | 删除时间     |

索引：
- PRIMARY KEY (id)
- INDEX (goods_id)
- GIN (spec_json)

### 购物模块

#### 购物车表(pg_carts)

| 字段名          | 类型         | 长度 | 可空 | 默认值 | 描述        |
|----------------|--------------|-----|------|-------|------------|
| id             | bigserial    |     | 否   |       | 购物车ID(主键)|
| user_id        | bigint       |     | 否   |       | 用户ID      |
| goods_id       | bigint       |     | 否   |       | 商品ID      |
| sku_id         | bigint       |     | 否   |       | SKU ID     |
| quantity       | int          |     | 否   | 1     | 数量        |
| selected       | smallint     |     | 否   | 1     | 是否选中(0否,1是) |
| create_time    | timestamp    |     | 否   | now() | 创建时间     |
| update_time    | timestamp    |     | 否   | now() | 更新时间     |

索引：
- PRIMARY KEY (id)
- UNIQUE INDEX (user_id, sku_id)
- INDEX (user_id)
- INDEX (goods_id)

#### 订单表(pg_orders)

| 字段名          | 类型         | 长度 | 可空 | 默认值 | 描述        |
|----------------|--------------|-----|------|-------|------------|
| id             | varchar      | 32  | 否   |       | 订单ID(主键) |
| user_id        | bigint       |     | 否   |       | 用户ID      |
| address_id     | bigint       |     | 否   |       | 地址ID      |
| total_price    | decimal      | 10,2| 否   | 0.00  | 订单总价     |
| pay_price      | decimal      | 10,2| 否   | 0.00  | 支付金额     |
| status         | smallint     |     | 否   | 1     | 订单状态     |
| pay_status     | smallint     |     | 否   | 0     | 支付状态     |
| pay_method     | smallint     |     | 是   | NULL  | 支付方式     |
| pay_time       | timestamp    |     | 是   | NULL  | 支付时间     |
| ship_status    | smallint     |     | 否   | 0     | 发货状态     |
| ship_time      | timestamp    |     | 是   | NULL  | 发货时间     |
| ship_company   | varchar      | 50  | 是   | NULL  | 快递公司     |
| ship_number    | varchar      | 50  | 是   | NULL  | 快递单号     |
| remark         | varchar      | 255 | 是   | NULL  | 订单备注     |
| create_time    | timestamp    |     | 否   | now() | 创建时间     |
| update_time    | timestamp    |     | 否   | now() | 更新时间     |
| delete_time    | timestamp    |     | 是   | NULL  | 删除时间     |

索引：
- PRIMARY KEY (id)
- INDEX (user_id)
- INDEX (status)
- INDEX (pay_status)

#### 订单商品表(pg_order_items)

| 字段名          | 类型         | 长度 | 可空 | 默认值 | 描述        |
|----------------|--------------|-----|------|-------|------------|
| id             | bigserial    |     | 否   |       | 订单商品ID(主键)|
| order_id       | varchar      | 32  | 否   |       | 订单ID      |
| goods_id       | bigint       |     | 否   |       | 商品ID      |
| sku_id         | bigint       |     | 否   |       | SKU ID     |
| goods_name     | varchar      | 100 | 否   |       | 商品名称     |
| goods_cover    | varchar      | 255 | 否   |       | 商品图片     |
| spec_json      | jsonb        |     | 否   |       | 规格JSON    |
| price          | decimal      | 10,2| 否   | 0.00  | 价格        |
| quantity       | int          |     | 否   | 1     | 数量        |
| total_price    | decimal      | 10,2| 否   | 0.00  | 总价        |

索引：
- PRIMARY KEY (id)
- INDEX (order_id)
- INDEX (goods_id)
- INDEX (sku_id)

#### 订单日志表(pg_order_logs)

| 字段名          | 类型         | 长度 | 可空 | 默认值 | 描述        |
|----------------|--------------|-----|------|-------|------------|
| id             | bigserial    |     | 否   |       | 日志ID(主键) |
| order_id       | varchar      | 32  | 否   |       | 订单ID      |
| action         | varchar      | 50  | 否   |       | 操作类型     |
| operator       | varchar      | 20  | 否   |       | 操作者类型   |
| operator_id    | bigint       |     | 是   | NULL  | 操作者ID     |
| content        | text         |     | 否   |       | 日志内容     |
| create_time    | timestamp    |     | 否   | now() | 创建时间     |

索引：
- PRIMARY KEY (id)
- INDEX (order_id)

## 数据关系图

```
users 1:n user_addresses  (一个用户可以有多个地址)
categories 1:n categories (分类可以有子分类)
categories 1:n goods      (一个分类可以有多个商品)
brands 1:n goods          (一个品牌可以有多个商品)
goods 1:n goods_skus      (一个商品可以有多个规格)
users 1:n carts           (一个用户可以有多个购物车项)
goods 1:n carts           (一个商品可以在多个购物车中)
users 1:n orders          (一个用户可以有多个订单)
orders 1:n order_items    (一个订单可以有多个商品项)
orders 1:n order_logs     (一个订单可以有多个日志)
``` 