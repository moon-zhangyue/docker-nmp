# 秒杀活动系统使用指南

## 1. 系统概述

秒杀活动系统是一个专为电商平台设计的高性能、高并发的促销活动解决方案。系统采用Redis缓存和消息队列技术，能够有效应对秒杀场景下的流量冲击，保证系统的稳定性和可靠性。

### 1.1 核心功能

- **秒杀活动管理**：创建、编辑、取消秒杀活动
- **秒杀商品管理**：设置秒杀商品、价格和库存
- **秒杀订单处理**：高并发下的订单创建和处理
- **库存控制**：精确的库存控制，防止超卖
- **用户限购**：支持每人限购数量设置
- **活动状态管理**：自动管理活动状态（未开始、进行中、已结束、已取消）

### 1.2 技术架构

- **数据存储**：MySQL数据库 + Redis缓存
- **消息队列**：使用ThinkPHP的Queue组件处理异步任务
- **缓存预热**：活动开始前预热商品库存到Redis
- **分布式锁**：使用Redis实现分布式锁，防止并发问题
- **状态自动更新**：通过定时任务自动更新活动状态

## 2. 数据表设计

系统使用三个主要数据表来存储秒杀活动相关信息：

### 2.1 秒杀活动表 (seckill_activity)

| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | int | 主键ID |
| title | varchar(100) | 活动标题 |
| description | text | 活动描述 |
| start_time | int | 活动开始时间（Unix时间戳） |
| end_time | int | 活动结束时间（Unix时间戳） |
| status | tinyint | 活动状态：0-未开始，1-进行中，2-已结束，3-已取消 |
| rules | text | 活动规则 |
| max_buy_limit | int | 每人最大购买数量限制 |
| is_featured | boolean | 是否推荐活动 |
| banner_image | varchar(255) | 活动banner图片URL |
| created_at | int | 创建时间（Unix时间戳） |
| updated_at | int | 更新时间（Unix时间戳） |

### 2.2 秒杀商品表 (seckill_goods)

| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | int | 主键ID |
| activity_id | int | 所属秒杀活动ID |
| sku_id | int | 商品SKU ID |
| spu_id | int | 商品SPU ID |
| original_price | decimal(10,2) | 商品原价 |
| seckill_price | decimal(10,2) | 秒杀价格 |
| total_stock | int | 秒杀总库存 |
| remain_stock | int | 剩余库存 |
| limit_per_user | int | 每人限购数量 |
| sort_order | int | 排序权重，数值越大越靠前 |
| status | tinyint | 状态：0-下架，1-上架 |
| created_at | int | 创建时间（Unix时间戳） |
| updated_at | int | 更新时间（Unix时间戳） |

### 2.3 秒杀订单表 (seckill_order)

| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | int | 主键ID |
| order_sn | varchar(64) | 订单编号 |
| user_id | int | 用户ID |
| activity_id | int | 秒杀活动ID |
| goods_id | int | 秒杀商品ID |
| sku_id | int | 商品SKU ID |
| quantity | int | 购买数量 |
| price | decimal(10,2) | 秒杀价格 |
| total_amount | decimal(10,2) | 订单总金额 |
| status | tinyint | 订单状态：0-待支付，1-已支付，2-已取消，3-已超时 |
| payment_time | int | 支付时间（Unix时间戳） |
| payment_method | varchar(20) | 支付方式：wechat-微信支付，alipay-支付宝 |
| transaction_id | varchar(64) | 支付交易号 |
| created_at | int | 创建时间（Unix时间戳） |
| updated_at | int | 更新时间（Unix时间戳） |

## 3. 活动状态管理

### 3.1 状态定义

秒杀活动有四种状态：

- **未开始 (STATUS_NOT_STARTED = 0)**：活动已创建但尚未到开始时间
- **进行中 (STATUS_IN_PROGRESS = 1)**：当前时间在活动开始和结束时间之间
- **已结束 (STATUS_ENDED = 2)**：当前时间已超过活动结束时间
- **已取消 (STATUS_CANCELED = 3)**：活动被管理员手动取消

### 3.2 状态更新机制

系统通过以下两种方式更新活动状态：

1. **创建活动时**：
   - 如果创建活动时当前时间已经在活动时间范围内，状态会自动设置为"进行中"(1)
   - 否则，状态设置为"未开始"(0)

2. **定时任务**：
   - 系统每5分钟执行一次`seckill:update-status`命令，自动更新所有活动状态
   - 每天执行一次强制更新，确保所有活动状态正确

用户参与秒杀时，系统只会检查活动状态，而不会更新状态。这确保了活动状态的管理完全由后台系统控制，不受用户行为影响。活动状态的检查是为了确保用户只能参与"进行中"的活动，而不能参与"未开始"、"已结束"或"已取消"的活动。

### 3.3 状态更新命令

系统提供了`UpdateSeckillStatus`命令用于更新活动状态：

```bash
# 更新近期活动状态
php think seckill:update-status

# 强制更新所有活动状态
php think seckill:update-status --force
```

### 3.4 状态变更处理

当活动状态发生变化时，系统会执行相应的处理：

- **未开始 → 进行中**：
  - 更新Redis中的活动状态
  - 可以触发活动开始通知
  - 更新首页推荐等

- **进行中 → 已结束**：
  - 更新Redis中的活动状态
  - 可以取消未支付的订单
  - 恢复未售出的商品库存

## 4. 使用指南

### 4.1 创建秒杀活动

通过API接口创建秒杀活动：

```http
POST /api/promotion/createSeckill
Content-Type: application/json

{
  "title": "618大促秒杀",
  "description": "年中大促，爆款商品5折起",
  "start_time": 1686960000,
  "end_time": 1687046400,
  "sku_id": 1001,
  "seckill_price": 99.99,
  "total_stock": 100,
  "limit_per_user": 1,
  "max_buy_limit": 1,
  "is_featured": true,
  "banner_image": "https://example.com/images/banner.jpg"
}
```

### 4.2 参与秒杀活动

用户通过API接口参与秒杀：

```http
POST /api/promotion/joinSeckill
Content-Type: application/json

{
  "sku_id": 1001,
  "user_id": 2001,
  "quantity": 1
}
```

### 4.3 查询秒杀活动列表

获取当前进行中和即将开始的秒杀活动：

```http
GET /api/promotion/getSeckillList
```

## 5. 性能优化

### 5.1 Redis缓存

系统使用Redis缓存秒杀活动信息和库存数据：

- **活动信息**：`seckill:goods:{sku_id}`哈希表存储活动详情
- **库存数据**：`seckill:stock:{sku_id}`列表存储预热的库存
- **用户购买记录**：`seckill:user:{user_id}:bought:{sku_id}`记录用户购买数量

### 5.2 库存预热

活动创建后，系统会预热库存到Redis：

```php
// 预热秒杀库存到队列
Queue::push('app\job\SeckillStockWarmupJob', [
    'sku_id'      => $skuId,
    'goods_id'    => $seckillGoods->id,
    'activity_id' => $activity->id,
    'total_stock' => $totalStock
]);
```

### 5.3 异步处理订单

用户抢购成功后，系统通过消息队列异步处理订单：

```php
// 将订单信息推送到队列中异步处理
Queue::push('app\job\SeckillOrderJob', [
    'user_id'     => $userId,
    'sku_id'      => $skuId,
    'activity_id' => $activityId,
    'goods_id'    => $goodsId,
    'quantity'    => $quantity,
    'price'       => $seckillInfo['seckill_price'],
    'order_sn'    => $orderSn,
    'seckill_key' => $seckillKey
]);
```

## 6. 常见问题

### 6.1 活动状态不更新

如果活动状态没有自动更新，可以尝试以下解决方案：

1. 手动执行状态更新命令：

   ```bash
   php think seckill:update-status --force
   ```

2. 检查定时任务是否正常运行：

   ```bash
   php think task:run
   ```

3. 确保系统crontab已正确配置：

   ```bash
   * * * * * cd /path/to/project && php think task:run >> /dev/null 2>&1
   ```

### 6.2 库存超卖问题

系统通过Redis原子操作和数据库事务防止超卖，如果仍然出现超卖问题，请检查：

1. Redis连接是否稳定
2. 是否有多个实例同时操作同一个库存
3. 是否正确使用了Redis的原子操作

### 6.3 高并发优化建议

1. 使用Redis集群提高缓存性能
2. 配置多个消息队列处理器提高订单处理速度
3. 对热门商品进行更多的库存预热
4. 实施流量控制，限制API请求频率
5. 使用CDN加速静态资源加载

## 6. 秒杀活动模块

秒杀活动模块负责管理电商平台的秒杀促销活动，包括活动创建、商品管理、订单处理和状态管理等功能。

### 功能说明

- **活动管理**：创建、编辑、取消秒杀活动，管理活动的基本信息和时间范围。
- **商品管理**：设置参与秒杀的商品、价格和库存，管理商品的上下架状态。
- **订单处理**：处理用户的秒杀请求，创建秒杀订单，管理订单状态。
- **状态管理**：自动管理活动状态（未开始、进行中、已结束、已取消），确保活动按时开始和结束。
- **库存控制**：精确控制秒杀商品库存，防止超卖和库存不一致问题。
- **用户限购**：限制每个用户的购买数量，确保活动的公平性。

### 核心方法

- `createSeckill()`：创建秒杀活动和商品
- `joinSeckill()`：用户参与秒杀活动
- `updateStatus()`：更新活动状态
- `getSeckillList()`：获取秒杀活动列表
- `decreaseStock()`：减少商品库存
- `checkUserLimit()`：检查用户购买限制

### 数据流程

1. 管理员通过`createSeckill()`方法创建秒杀活动和商品
2. 系统预热商品库存到Redis缓存，并设置活动状态
3. 活动开始时，系统自动将状态更新为"进行中"
4. 用户通过`joinSeckill()`方法参与秒杀活动
5. 系统检查库存和用户限购情况，减少库存并创建订单
6. 系统通过消息队列异步处理订单，更新数据库
7. 活动结束时，系统自动将状态更新为"已结束"

### 状态更新机制

秒杀活动状态更新通过以下两种方式实现：

1. **创建活动时**：根据当前时间与活动时间的关系设置初始状态
2. **定时任务**：系统每5分钟执行一次状态更新任务，确保所有活动状态正确

### 秒杀活动流程

1. **秒杀活动模块**：创建秒杀活动和商品
2. **Redis缓存**：预热商品库存和活动信息
3. **定时任务**：自动更新活动状态
4. **用户请求**：用户参与秒杀活动
5. **状态检查**：系统检查活动状态，确保活动处于"进行中"状态
6. **Redis缓存**：原子操作减少库存，记录用户购买数量
7. **消息队列**：异步处理订单创建和库存同步
8. **数据库**：持久化订单和库存数据

## 技术实现

系统采用ThinkPHP 8.0框架开发，采用MVC架构模式：

- **模型层（Model）**：包含ParkingLot、GateDevice、Vehicle、MonthlyPass、ParkingRecord、ParkingFeeRule、SeckillActivity、SeckillGoods、SeckillOrder等模型类，负责数据访问和业务逻辑
- **视图层（View）**：采用前后端分离架构，前端使用Vue.js构建用户界面
- **控制器层（Controller）**：包含ParkingLotController、GateController、VehicleController、ParkingRecordController、ParkingFeeRuleController、PromotionController等控制器，负责处理用户请求和返回响应

系统还利用Redis缓存提高性能，使用消息队列处理异步任务，如订单处理、库存同步和数据统计等。