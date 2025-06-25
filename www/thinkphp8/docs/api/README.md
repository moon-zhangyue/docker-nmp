# API 文档

本文档详细描述了电子商务应用的API接口。

## 用户模块 API

### 注册接口

- **URL**: `/pg/user/register`
- **方法**: POST
- **描述**: 用户注册
- **参数**:

| 参数名   | 类型   | 必填 | 描述     |
|---------|--------|-----|----------|
| username | string | 是   | 用户名   |
| password | string | 是   | 密码     |
| email    | string | 是   | 邮箱     |
| mobile   | string | 否   | 手机号   |
| nickname | string | 否   | 昵称     |

- **返回示例**:

```json
{
    "code": 200,
    "msg": "注册成功",
    "data": {
        "user_id": 1,
        "username": "testuser"
    }
}
```

### 登录接口

- **URL**: `/pg/user/login`
- **方法**: POST
- **描述**: 用户登录
- **参数**:

| 参数名   | 类型   | 必填 | 描述     |
|---------|--------|-----|----------|
| username | string | 是   | 用户名   |
| password | string | 是   | 密码     |

- **返回示例**:

```json
{
    "code": 200,
    "msg": "登录成功",
    "data": {
        "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
        "user_info": {
            "id": 1,
            "username": "testuser",
            "nickname": "测试用户",
            "avatar": "http://example.com/avatar.jpg",
            "mobile": "13800138000",
            "email": "test@example.com"
        }
    }
}
```

### 用户详情接口

- **URL**: `/pg/user/info`
- **方法**: GET
- **描述**: 获取用户详情
- **参数**: 无（通过Token认证）
- **返回示例**:

```json
{
    "code": 200,
    "msg": "获取成功",
    "data": {
        "id": 1,
        "username": "testuser",
        "nickname": "测试用户",
        "avatar": "http://example.com/avatar.jpg",
        "mobile": "13800138000",
        "email": "test@example.com",
        "gender": 1,
        "gender_text": "男",
        "last_login_time": "2023-05-01 10:00:00",
        "last_login_ip": "127.0.0.1",
        "create_time": "2023-01-01 12:00:00"
    }
}
```

### 更新用户信息接口

- **URL**: `/pg/user/update`
- **方法**: POST
- **描述**: 更新用户信息
- **参数**:

| 参数名   | 类型   | 必填 | 描述     |
|---------|--------|-----|----------|
| nickname | string | 否   | 昵称     |
| avatar   | string | 否   | 头像URL  |
| mobile   | string | 否   | 手机号   |
| gender   | int    | 否   | 性别(0未知,1男,2女) |

- **返回示例**:

```json
{
    "code": 200,
    "msg": "更新成功",
    "data": {
        "id": 1,
        "nickname": "新昵称",
        "avatar": "http://example.com/new-avatar.jpg",
        "mobile": "13900139000",
        "gender": 1,
        "gender_text": "男"
    }
}
```

## 地址模块 API

### 添加地址接口

- **URL**: `/pg/user/address/add`
- **方法**: POST
- **描述**: 添加收货地址
- **参数**:

| 参数名        | 类型   | 必填 | 描述     |
|--------------|--------|-----|----------|
| name         | string | 是   | 收货人姓名 |
| mobile       | string | 是   | 手机号    |
| province     | string | 是   | 省份      |
| city         | string | 是   | 城市      |
| district     | string | 是   | 区/县     |
| detail       | string | 是   | 详细地址   |
| is_default   | int    | 否   | 是否默认(0否,1是) |

- **返回示例**:

```json
{
    "code": 200,
    "msg": "添加成功",
    "data": {
        "id": 1,
        "name": "张三",
        "mobile": "13800138000",
        "province": "广东省",
        "city": "深圳市",
        "district": "南山区",
        "detail": "科技园1号楼",
        "is_default": 1,
        "create_time": "2023-05-01 12:00:00"
    }
}
```

## 商品模块 API

### 商品列表接口

- **URL**: `/pg/goods/list`
- **方法**: GET
- **描述**: 获取商品列表
- **参数**:

| 参数名      | 类型   | 必填 | 描述     |
|------------|--------|-----|----------|
| category_id | int    | 否   | 分类ID   |
| brand_id    | int    | 否   | 品牌ID   |
| keyword     | string | 否   | 搜索关键词 |
| price_min   | float  | 否   | 最低价格  |
| price_max   | float  | 否   | 最高价格  |
| sort_field  | string | 否   | 排序字段  |
| sort_order  | string | 否   | 排序方式(asc,desc) |
| page        | int    | 否   | 页码(默认1) |
| limit       | int    | 否   | 每页数量(默认20) |

- **返回示例**:

```json
{
    "code": 200,
    "msg": "获取成功",
    "data": {
        "total": 100,
        "per_page": 20,
        "current_page": 1,
        "last_page": 5,
        "data": [
            {
                "id": 1,
                "name": "商品1",
                "cover": "http://example.com/image1.jpg",
                "price": 99.00,
                "market_price": 199.00,
                "sales": 100,
                "category_id": 1,
                "brand_id": 1,
                "is_hot": 1,
                "is_new": 1,
                "is_recommend": 1
            },
            // 更多商品...
        ]
    }
}
```

### 商品详情接口

- **URL**: `/pg/goods/detail/{id}`
- **方法**: GET
- **描述**: 获取商品详情
- **参数**:

| 参数名 | 类型 | 必填 | 描述     |
|-------|------|-----|----------|
| id    | int  | 是   | 商品ID   |

- **返回示例**:

```json
{
    "code": 200,
    "msg": "获取成功",
    "data": {
        "id": 1,
        "name": "商品1",
        "cover": "http://example.com/image1.jpg",
        "price": 99.00,
        "market_price": 199.00,
        "sales": 100,
        "stock": 1000,
        "category_id": 1,
        "category_name": "电子产品",
        "brand_id": 1,
        "brand_name": "品牌1",
        "description": "商品详细描述...",
        "images": [
            "http://example.com/image1.jpg",
            "http://example.com/image2.jpg"
        ],
        "attributes": [
            {
                "name": "颜色",
                "value": "红色"
            },
            {
                "name": "尺寸",
                "value": "大"
            }
        ],
        "skus": [
            {
                "id": 1,
                "goods_id": 1,
                "spec_json": "{\"颜色\":\"红色\",\"尺寸\":\"大\"}",
                "price": 99.00,
                "stock": 500
            },
            {
                "id": 2,
                "goods_id": 1,
                "spec_json": "{\"颜色\":\"蓝色\",\"尺寸\":\"大\"}",
                "price": 99.00,
                "stock": 500
            }
        ]
    }
}
```

## 购物车模块 API

### 添加购物车接口

- **URL**: `/pg/cart/add`
- **方法**: POST
- **描述**: 添加商品到购物车
- **参数**:

| 参数名    | 类型 | 必填 | 描述     |
|----------|------|-----|----------|
| sku_id   | int  | 是   | SKU ID   |
| quantity | int  | 是   | 数量     |

- **返回示例**:

```json
{
    "code": 200,
    "msg": "添加成功",
    "data": {
        "id": 1,
        "user_id": 1,
        "sku_id": 1,
        "goods_id": 1,
        "goods_name": "商品1",
        "goods_cover": "http://example.com/image1.jpg",
        "spec_json": "{\"颜色\":\"红色\",\"尺寸\":\"大\"}",
        "price": 99.00,
        "quantity": 2,
        "total_price": 198.00,
        "create_time": "2023-05-01 12:00:00"
    }
}
```

### 购物车列表接口

- **URL**: `/pg/cart/list`
- **方法**: GET
- **描述**: 获取购物车列表
- **参数**: 无（通过Token认证）
- **返回示例**:

```json
{
    "code": 200,
    "msg": "获取成功",
    "data": {
        "total_price": 297.00,
        "total_quantity": 3,
        "items": [
            {
                "id": 1,
                "user_id": 1,
                "sku_id": 1,
                "goods_id": 1,
                "goods_name": "商品1",
                "goods_cover": "http://example.com/image1.jpg",
                "spec_json": "{\"颜色\":\"红色\",\"尺寸\":\"大\"}",
                "price": 99.00,
                "quantity": 2,
                "total_price": 198.00,
                "create_time": "2023-05-01 12:00:00"
            },
            {
                "id": 2,
                "user_id": 1,
                "sku_id": 2,
                "goods_id": 1,
                "goods_name": "商品1",
                "goods_cover": "http://example.com/image1.jpg",
                "spec_json": "{\"颜色\":\"蓝色\",\"尺寸\":\"大\"}",
                "price": 99.00,
                "quantity": 1,
                "total_price": 99.00,
                "create_time": "2023-05-01 12:00:00"
            }
        ]
    }
}
```

## 订单模块 API

### 创建订单接口

- **URL**: `/pg/order/create`
- **方法**: POST
- **描述**: 创建订单
- **参数**:

| 参数名      | 类型   | 必填 | 描述     |
|------------|--------|-----|----------|
| cart_ids   | array  | 是   | 购物车ID数组 |
| address_id | int    | 是   | 地址ID   |
| remark     | string | 否   | 订单备注  |

- **返回示例**:

```json
{
    "code": 200,
    "msg": "创建成功",
    "data": {
        "order_id": "ORD202305010001",
        "order_info": {
            "id": "ORD202305010001",
            "user_id": 1,
            "address_id": 1,
            "total_price": 297.00,
            "pay_price": 297.00,
            "status": 1,
            "status_text": "待付款",
            "pay_status": 0,
            "pay_status_text": "未支付",
            "pay_method": null,
            "pay_method_text": null,
            "ship_status": 0,
            "ship_status_text": "未发货",
            "remark": "备注信息",
            "create_time": "2023-05-01 12:00:00"
        },
        "payment_url": "http://example.com/pay/ORD202305010001"
    }
}
```

### 订单列表接口

- **URL**: `/pg/order/list`
- **方法**: GET
- **描述**: 获取订单列表
- **参数**:

| 参数名  | 类型 | 必填 | 描述     |
|--------|------|-----|----------|
| status | int  | 否   | 订单状态  |
| page   | int  | 否   | 页码(默认1) |
| limit  | int  | 否   | 每页数量(默认20) |

- **返回示例**:

```json
{
    "code": 200,
    "msg": "获取成功",
    "data": {
        "total": 10,
        "per_page": 20,
        "current_page": 1,
        "last_page": 1,
        "data": [
            {
                "id": "ORD202305010001",
                "user_id": 1,
                "address_id": 1,
                "total_price": 297.00,
                "pay_price": 297.00,
                "status": 1,
                "status_text": "待付款",
                "pay_status": 0,
                "pay_status_text": "未支付",
                "pay_method": null,
                "pay_method_text": null,
                "ship_status": 0,
                "ship_status_text": "未发货",
                "remark": "备注信息",
                "create_time": "2023-05-01 12:00:00",
                "items": [
                    {
                        "id": 1,
                        "order_id": "ORD202305010001",
                        "goods_id": 1,
                        "sku_id": 1,
                        "goods_name": "商品1",
                        "goods_cover": "http://example.com/image1.jpg",
                        "spec_json": "{\"颜色\":\"红色\",\"尺寸\":\"大\"}",
                        "price": 99.00,
                        "quantity": 2,
                        "total_price": 198.00
                    },
                    {
                        "id": 2,
                        "order_id": "ORD202305010001",
                        "goods_id": 1,
                        "sku_id": 2,
                        "goods_name": "商品1",
                        "goods_cover": "http://example.com/image1.jpg",
                        "spec_json": "{\"颜色\":\"蓝色\",\"尺寸\":\"大\"}",
                        "price": 99.00,
                        "quantity": 1,
                        "total_price": 99.00
                    }
                ]
            }
        ]
    }
}
```

### 订单详情接口

- **URL**: `/pg/order/detail/{id}`
- **方法**: GET
- **描述**: 获取订单详情
- **参数**:

| 参数名 | 类型   | 必填 | 描述     |
|-------|--------|-----|----------|
| id    | string | 是   | 订单ID   |

- **返回示例**:

```json
{
    "code": 200,
    "msg": "获取成功",
    "data": {
        "id": "ORD202305010001",
        "user_id": 1,
        "address_id": 1,
        "address_info": {
            "name": "张三",
            "mobile": "13800138000",
            "province": "广东省",
            "city": "深圳市",
            "district": "南山区",
            "detail": "科技园1号楼"
        },
        "total_price": 297.00,
        "pay_price": 297.00,
        "status": 1,
        "status_text": "待付款",
        "pay_status": 0,
        "pay_status_text": "未支付",
        "pay_method": null,
        "pay_method_text": null,
        "pay_time": null,
        "ship_status": 0,
        "ship_status_text": "未发货",
        "ship_time": null,
        "ship_company": null,
        "ship_number": null,
        "remark": "备注信息",
        "create_time": "2023-05-01 12:00:00",
        "items": [
            {
                "id": 1,
                "order_id": "ORD202305010001",
                "goods_id": 1,
                "sku_id": 1,
                "goods_name": "商品1",
                "goods_cover": "http://example.com/image1.jpg",
                "spec_json": "{\"颜色\":\"红色\",\"尺寸\":\"大\"}",
                "price": 99.00,
                "quantity": 2,
                "total_price": 198.00
            },
            {
                "id": 2,
                "order_id": "ORD202305010001",
                "goods_id": 1,
                "sku_id": 2,
                "goods_name": "商品1",
                "goods_cover": "http://example.com/image1.jpg",
                "spec_json": "{\"颜色\":\"蓝色\",\"尺寸\":\"大\"}",
                "price": 99.00,
                "quantity": 1,
                "total_price": 99.00
            }
        ],
        "logs": [
            {
                "id": 1,
                "order_id": "ORD202305010001",
                "action": "create",
                "action_text": "创建订单",
                "operator": "user",
                "operator_id": 1,
                "content": "用户创建订单",
                "create_time": "2023-05-01 12:00:00"
            }
        ]
    }
}
``` 