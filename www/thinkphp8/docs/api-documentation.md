# 停车场闸机系统 - API接口文档

## 接口规范

### 基本信息

- **基础URL**: `/api/v1`
- **请求方式**: REST风格API，使用HTTP动词表示操作类型
  - GET: 获取资源
  - POST: 创建资源
  - PUT: 更新资源
  - DELETE: 删除资源
- **数据格式**: 请求和响应均使用JSON格式
- **字符编码**: UTF-8

### 请求头

| 请求头 | 说明 |
|--------|------|
| Content-Type | application/json |
| Authorization | Bearer {token} |
| Accept-Language | zh-CN, en-US |

### 响应格式

所有API响应均使用统一的JSON格式：

```json
{
  "code": 0,       // 状态码，0表示成功，非0表示失败
  "msg": "success", // 状态消息
  "data": {}      // 响应数据，可能是对象、数组或null
}
```

分页数据格式：

```json
{
  "code": 0,
  "msg": "success",
  "count": 100,   // 总记录数
  "data": []      // 当前页数据
}
```

## 认证机制

系统使用JWT（JSON Web Token）进行API认证，认证流程如下：

1. 客户端通过登录接口获取token
2. 客户端在后续请求中，在Authorization头中携带token
3. 服务器验证token的有效性
4. token过期后，客户端需要重新登录获取新token

### 登录获取Token

```
POST /api/v1/auth/login
```

请求参数：

```json
{
  "username": "admin",
  "password": "password"
}
```

响应结果：

```json
{
  "code": 0,
  "msg": "success",
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "expires_in": 86400
  }
}
```

## 接口列表

### 1. 停车场管理接口

#### 1.1 获取停车场列表

```
GET /api/v1/parking-lots
```

请求参数：

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| page | int | 否 | 页码，默认1 |
| limit | int | 否 | 每页记录数，默认10 |
| status | int | 否 | 状态筛选 |

响应结果：

```json
{
  "code": 0,
  "msg": "success",
  "count": 100,
  "data": [
    {
      "id": 1,
      "name": "中央广场停车场",
      "address": "市中心中央广场地下一层",
      "total_spaces": 500,
      "occupied_spaces": 320,
      "status": 1,
      "contact_phone": "13800138000",
      "create_time": "2023-01-01 10:00:00",
      "update_time": "2023-01-01 10:00:00",
      "occupancy_rate": 64,
      "available_spaces": 180
    }
  ]
}
```

#### 1.2 获取停车场详情

```
GET /api/v1/parking-lots/{id}
```

响应结果：

```json
{
  "code": 0,
  "msg": "success",
  "data": {
    "id": 1,
    "name": "中央广场停车场",
    "address": "市中心中央广场地下一层",
    "total_spaces": 500,
    "occupied_spaces": 320,
    "status": 1,
    "contact_phone": "13800138000",
    "create_time": "2023-01-01 10:00:00",
    "update_time": "2023-01-01 10:00:00",
    "occupancy_rate": 64,
    "available_spaces": 180
  }
}
```

#### 1.3 添加停车场

```
POST /api/v1/parking-lots
```

请求参数：

```json
{
  "name": "中央广场停车场",
  "address": "市中心中央广场地下一层",
  "total_spaces": 500,
  "contact_phone": "13800138000",
  "status": 1
}
```

响应结果：

```json
{
  "code": 0,
  "msg": "success",
  "data": {
    "id": 1
  }
}
```

#### 1.4 更新停车场

```
PUT /api/v1/parking-lots/{id}
```

请求参数：

```json
{
  "name": "中央广场停车场",
  "address": "市中心中央广场地下一层",
  "total_spaces": 600,
  "contact_phone": "13800138000",
  "status": 1
}
```

响应结果：

```json
{
  "code": 0,
  "msg": "success",
  "data": null
}
```

#### 1.5 删除停车场

```
DELETE /api/v1/parking-lots/{id}
```

响应结果：

```json
{
  "code": 0,
  "msg": "success",
  "data": null
}
```

### 2. 闸机设备接口

#### 2.1 获取闸机设备列表

```
GET /api/v1/gate-devices
```

请求参数：

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| page | int | 否 | 页码，默认1 |
| limit | int | 否 | 每页记录数，默认10 |
| parking_lot_id | int | 否 | 停车场ID |
| status | int | 否 | 设备状态 |

响应结果：

```json
{
  "code": 0,
  "msg": "success",
  "count": 10,
  "data": [
    {
      "id": 1,
      "name": "东门入口闸机",
      "device_sn": "GD20240501001",
      "parking_lot_id": 1,
      "device_type": 1,
      "status": 1,
      "ip_address": "192.168.1.100",
      "create_time": "2024-05-01 10:00:00",
      "update_time": "2024-05-01 10:00:00"
    }
  ]
}
```

#### 2.2 获取闸机设备详情

```
GET /api/v1/gate-devices/{id}
```

响应结果：

```json
{
  "code": 0,
  "msg": "success",
  "data": {
    "id": 1,
    "name": "东门入口闸机",
    "device_sn": "GD20240501001",
    "parking_lot_id": 1,
    "device_type": 1,
    "status": 1,
    "ip_address": "192.168.1.100",
    "create_time": "2024-05-01 10:00:00",
    "update_time": "2024-05-01 10:00:00"
  }
}
```

#### 2.3 添加闸机设备

```
POST /api/v1/gate-devices
```

请求参数：

```json
{
  "name": "东门入口闸机",
  "device_sn": "GD20240501001",
  "parking_lot_id": 1,
  "device_type": 1,
  "ip_address": "192.168.1.100"
}
```

响应结果：

```json
{
  "code": 0,
  "msg": "success",
  "data": {
    "id": 1
  }
}
```

#### 2.4 更新闸机设备

```
PUT /api/v1/gate-devices/{id}
```

请求参数：

```json
{
  "name": "东门入口闸机",
  "device_type": 1,
  "ip_address": "192.168.1.101",
  "status": 1
}
```

响应结果：

```json
{
  "code": 0,
  "msg": "success",
  "data": null
}
```

#### 2.5 删除闸机设备

```
DELETE /api/v1/gate-devices/{id}
```

响应结果：

```json
{
  "code": 0,
  "msg": "success",
  "data": null
}
```

#### 2.6 远程开闸

```
POST /api/v1/gate-devices/{id}/open
```

响应结果：

```json
{
  "code": 0,
  "msg": "success",
  "data": null
}
```

### 3. 车辆管理接口

#### 3.1 获取车辆列表

```
GET /api/v1/vehicles
```

请求参数：

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| page | int | 否 | 页码，默认1 |
| limit | int | 否 | 每页记录数，默认10 |
| plate_number | string | 否 | 车牌号码 |
| vehicle_type | int | 否 | 车辆类型 |
| status | int | 否 | 状态 |

响应结果：

```json
{
  "code": 0,
  "msg": "success",
  "count": 100,
  "data": [
    {
      "id": 1,
      "plate_number": "京A12345",
      "vehicle_type": 2,
      "owner_name": "张三",
      "owner_phone": "13900139000",
      "status": 1,
      "create_time": "2024-05-01 10:00:00",
      "update_time": "2024-05-01 10:00:00"
    }
  ]
}
```

#### 3.2 获取车辆详情

```
GET /api/v1/vehicles/{id}
```

响应结果：

```json
{
  "code": 0,
  "msg": "success",
  "data": {
    "id": 1,
    "plate_number": "京A12345",
    "vehicle_type": 2,
    "owner_name": "张三",
    "owner_phone": "13900139000",
    "status": 1,
    "create_time": "2024-05-01 10:00:00",
    "update_time": "2024-05-01 10:00:00",
    "monthly_pass": {
      "id": 1,
      "plate_number": "京A12345",
      "start_date": "2024-05-01",
      "end_date": "2024-05-31",
      "status": 1
    }
  }
}
```

#### 3.3 添加车辆

```
POST /api/v1/vehicles
```

请求参数：

```json
{
  "plate_number": "京A12345",
  "vehicle_type": 2,
  "owner_name": "张三",
  "owner_phone": "13900139000",
  "status": 1
}
```

响应结果：

```json
{
  "code": 0,
  "msg": "success",
  "data": {
    "id": 1
  }
}
```

#### 3.4 更新车辆

```
PUT /api/v1/vehicles/{id}
```

请求参数：

```json
{
  "vehicle_type": 2,
  "owner_name": "张三",
  "owner_phone": "13900139001",
  "status": 1
}
```

响应结果：

```json
{
  "code": 0,
  "msg": "success",
  "data": null
}
```

#### 3.5 删除车辆

```
DELETE /api/v1/vehicles/{id}
```

响应结果：

```json
{
  "code": 0,
  "msg": "success",
  "data": null
}
```

### 4. 月租通行证接口

#### 4.1 获取月租通行证列表

```
GET /api/v1/monthly-passes
```

请求参数：

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| page | int | 否 | 页码，默认1 |
| limit | int | 否 | 每页记录数，默认10 |
| plate_number | string | 否 | 车牌号码 |
| status | int | 否 | 状态 |

响应结果：

```json
{
  "code": 0,
  "msg": "success",
  "count": 50,
  "data": [
    {
      "id": 1,
      "plate_number": "京A12345",
      "start_date": "2024-05-01",
      "end_date": "2024-05-31",
      "status": 1,
      "create_time": "2024-05-01 10:00:00",
      "update_time": "2024-05-01 10:00:00",
      "vehicle": {
        "owner_name": "张三",
        "owner_phone": "13900139000"
      }
    }
  ]
}
```

#### 4.2 创建月租通行证

```
POST /api/v1/monthly-passes
```

请求参数：

```json
{
  "plate_number": "京A12345",
  "start_date": "2024-05-01",
  "end_date": "2024-05-31"
}
```

响应结果：

```json
{
  "code": 0,
  "msg": "success",
  "data": {
    "id": 1
  }
}
```

#### 4.3 续期月租通行证

```
PUT /api/v1/monthly-passes/{id}/renew
```

请求参数：

```json
{
  "end_date": "2024-06-30"
}
```

响应结果：

```json
{
  "code": 0,
  "msg": "success",
  "data": null
}
```

### 5. 停车记录接口

#### 5.1 获取停车记录列表

```
GET /api/v1/parking-records
```

请求参数：

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| page | int | 否 | 页码，默认1 |
| limit | int | 否 | 每页记录数，默认10 |
| plate_number | string | 否 | 车牌号码 |
| status | int | 否 | 记录状态 |
| start_time | string | 否 | 开始时间 |
| end_time | string | 否 | 结束时间 |
| parking_lot_id | int | 否 | 停车场ID |

响应结果：

```json
{
  "code": 0,
  "msg": "success",
  "count": 1000,
  "data": [
    {
      "id": 1,
      "parking_lot_id": 1,
      "plate_number": "京A12345",
      "entry_time": "2024-05-10 08:30:00",
      "exit_time": "2024-05-10 17:45:00",
      "parking_fee": 25.00,
      "payment_status": 1,
      "payment_method": 2,
      "status": 2,
      "create_time": "2024-05-10 08:30:00",
      "update_time": "2024-05-10 17:45:00",
      "parking_lot": {
        "name": "中央广场停车场"
      },
      "duration": "9小时15分钟"
    }
  ]
}
```

#### 5.2 获取停车记录详情

```
GET /api/v1/parking-records/{id}
```

响应结果：

```json
{
  "code": 0,
  "msg": "success",
  "data": {
    "id": 1,
    "parking_lot_id": 1,
    "plate_number": "京A12345",
    "entry_time": "2024-05-10 08:30:00",
    "exit_time": "2024-05-10 17:45:00",
    "parking_fee": 25.00,
    "payment_status": 1,
    "payment_method": 2,
    "status": 2,
    "create_time": "2024-05-10 08:30:00",
    "update_time": "2024-05-10 17:45:00",
    "parking_lot": {
      "id": 1,
      "name": "中央广场停车场"
    },
    "duration": "9小时15分钟",
    "fee_details": {
      "base_fee": 25.00,
      "discount": 0,
      "final_fee": 25.00,
      "rule_applied": "工作日收费标准"
    }
  }
}
```

#### 5.3 车辆入场

```
POST /api/v1/parking-records/entry
```

请求参数：

```json
{
  "parking_lot_id": 1,
  "plate_number": "京A12345",
  "gate_device_id": 1
}
```

响应结果：

```json
{
  "code": 0,
  "msg": "success",
  "data": {
    "id": 1,
    "entry_time": "2024-05-10 08:30:00",
    "vehicle_type": 2,
    "is_monthly_pass": true
  }
}
```

#### 5.4 车辆出场

```
POST /api/v1/parking-records/exit
```

请求参数：

```json
{
  "plate_number": "京A12345",
  "gate_device_id": 2
}
```

响应结果：

```json
{
  "code": 0,
  "msg": "success",
  "data": {
    "id": 1,
    "entry_time": "2024-05-10 08:30:00",
    "exit_time": "2024-05-10 17:45:00",
    "duration": "9小时15分钟",
    "parking_fee": 25.00,
    "payment_status": 0,
    "is_monthly_pass": true
  }
}
```

#### 5.5 支付停车费

```
POST /api/v1/parking-records/{id}/payment
```

请求参数：

```json
{
  "payment_method": 2,
  "amount": 25.00
}
```

响应结果：

```json
{
  "code": 0,
  "msg": "success",
  "data": {
    "payment_status": 1,
    "payment_time": "2024-05-10 17:47:30"
  }
}
```

### 6. 收费规则接口

#### 6.1 获取收费规则列表

```
GET /api/v1/parking-fee-rules
```

请求参数：

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| page | int | 否 | 页码，默认1 |
| limit | int | 否 | 每页记录数，默认10 |
| parking_lot_id | int | 否 | 停车场ID |
| status | int | 否 | 状态 |

响应结果：

```json
{
  "code": 0,
  "msg": "success",
  "count": 10,
  "data": [
    {
      "id": 1,
      "parking_lot_id": 1,
      "rule_name": "工作日收费标准",
      "rule_type": 1,
      "fee_standard": {
        "first_hour": 10,
        "additional_hour": 5
      },
      "free_minutes": 15,
      "daily_cap": 50.00,
      "status": 1,
      "create_time": "2024-05-01 10:00:00",
      "update_time": "2024-05-01 10:00:00",
      "parking_lot": {
        "name": "中央广场停车场"
      }
    }
  ]
}
```

#### 6.2 获取收费规则详情

```
GET /api/v1/parking-fee-rules/{id}
```

响应结果：

```json
{
  "code": 0,
  "msg": "success",
  "data": {
    "id": 1,
    "parking_lot_id": 1,
    "rule_name": "工作日收费标准",
    "rule_type": 1,
    "fee_standard": {
      "first_hour": 10,
      "additional_hour": 5
    },
    "free_minutes": 15,
    "daily_cap": 50.00,
    "status": 1,
    "create_time": "2024-05-01 10:00:00",
    "update_time": "2024-05-01 10:00:00",
    "parking_lot": {
      "name": "中央广场停车场"
    }
  }
}
```

#### 6.3 创建收费规则

```
POST /api/v1/parking-fee-rules
```

请求参数：

```json
{
  "parking_lot_id": 1,
  "rule_name": "工作日收费标准",
  "rule_type": 1,
  "fee_standard": {
    "first_hour": 10,
    "additional_hour": 5
  },
  "free_minutes": 15,
  "daily_cap": 50.00,
  "status": 1
}
```

响应结果：

```json
{
  "code": 0,
  "msg": "success",
  "data": {
    "id": 1
  }
}
```

#### 6.4 更新收费规则

```
PUT /api/v1/parking-fee-rules/{id}
```

请求参数：

```json
{
  "rule_name": "工作日收费标准",
  "fee_standard": {
    "first_hour": 12,
    "additional_hour": 6
  },
  "free_minutes": 15,
  "daily_cap": 60.00,
  "status": 1
}
```

响应结果：

```json
{
  "code": 0,
  "msg": "success",
  "data": null
}
```

#### 6.5 删除收费规则

```
DELETE /api/v1/parking-fee-rules/{id}
```

响应结果：

```json
{
  "code": 0,
  "msg": "success",
  "data": null
}
```

## 错误码说明

| 错误码 | 说明 |
|--------|------|
| 0 | 成功 |
| 1000 | 系统错误 |
| 1001 | 参数错误 |
| 1002 | 数据库错误 |
| 2001 | 用户未登录 |
| 2002 | 用户名或密码错误 |
| 2003 | 权限不足 |
| 3001 | 停车场不存在 |
| 3002 | 停车场已满 |
| 4001 | 闸机设备不存在 |
| 4002 | 闸机设备离线