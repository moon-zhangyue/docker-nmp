# 停车场闸机系统 - 数据库设计

## 数据库ER图

```
+----------------+       +----------------+       +----------------+
|   parking_lot  |       |  gate_device   |       |     vehicle    |
+----------------+       +----------------+       +----------------+
| id             |<----->| id             |       | id             |
| name           |       | name           |       | plate_number   |
| address        |       | device_sn      |       | vehicle_type   |
| total_spaces   |       | parking_lot_id |<----->| owner_name     |
| occupied_spaces|       | device_type    |       | owner_phone    |
| status         |       | status         |       | status         |
| contact_phone  |       | ip_address     |       | create_time    |
| create_time    |       | create_time    |       | update_time    |
| update_time    |       | update_time    |       +-------^--------+
+-------^--------+       +----------------+               |
        |                                                 |
        |                                                 |
        |                                                 |
        |                +----------------+               |
        |                | monthly_pass   |               |
        |                +----------------+               |
        |                | id             |               |
        |                | plate_number   |---------------+
        |                | start_date     |
        |                | end_date       |
        |                | status         |
        |                | create_time    |
        |                | update_time    |
        |                +----------------+
        |
        |                +----------------+
        |                | parking_fee_rule|
        |                +----------------+
        |                | id             |
        |                | parking_lot_id |---------------+
        |                | rule_name      |               |
        |                | rule_type      |               |
        |                | fee_standard   |               |
        |                | free_minutes   |               |
        |                | daily_cap      |               |
        |                | status         |               |
        |                | create_time    |               |
        |                | update_time    |               |
        |                +----------------+               |
        |                                                 |
        |                                                 |
        |                +----------------+               |
        +--------------->| parking_record |               |
                         +----------------+               |
                         | id             |               |
                         | parking_lot_id |               |
                         | plate_number   |---------------+
                         | entry_time     |
                         | exit_time      |
                         | parking_fee    |
                         | payment_status |
                         | payment_method |
                         | status         |
                         | create_time    |
                         | update_time    |
                         +----------------+
```

## 表结构说明

### 1. parking_lot（停车场信息表）

存储停车场的基本信息，包括名称、地址、车位数量等。

| 字段名 | 类型 | 说明 | 约束 |
|--------|------|------|------|
| id | int | 主键ID | PRIMARY KEY, AUTO_INCREMENT |
| name | varchar(100) | 停车场名称 | NOT NULL |
| address | varchar(255) | 停车场地址 | NOT NULL |
| total_spaces | int | 总车位数 | NOT NULL, DEFAULT 0 |
| occupied_spaces | int | 已占用车位数 | NOT NULL, DEFAULT 0 |
| status | tinyint | 状态：1-正常营业，0-暂停营业 | NOT NULL, DEFAULT 1 |
| contact_phone | varchar(20) | 联系电话 | NULL |
| create_time | datetime | 创建时间 | NOT NULL |
| update_time | datetime | 更新时间 | NOT NULL |

### 2. gate_device（闸机设备表）

存储闸机设备的信息，包括设备编号、类型、状态等。

| 字段名 | 类型 | 说明 | 约束 |
|--------|------|------|------|
| id | int | 主键ID | PRIMARY KEY, AUTO_INCREMENT |
| name | varchar(100) | 设备名称 | NOT NULL |
| device_sn | varchar(50) | 设备序列号 | NOT NULL, UNIQUE |
| parking_lot_id | int | 所属停车场ID | NOT NULL, FOREIGN KEY |
| device_type | tinyint | 设备类型：1-入口闸机，2-出口闸机 | NOT NULL |
| status | tinyint | 设备状态：1-在线，0-离线 | NOT NULL, DEFAULT 1 |
| ip_address | varchar(15) | 设备IP地址 | NULL |
| create_time | datetime | 创建时间 | NOT NULL |
| update_time | datetime | 更新时间 | NOT NULL |

### 3. vehicle（车辆信息表）

存储车辆的基本信息，包括车牌号、车辆类型、车主信息等。

| 字段名 | 类型 | 说明 | 约束 |
|--------|------|------|------|
| id | int | 主键ID | PRIMARY KEY, AUTO_INCREMENT |
| plate_number | varchar(20) | 车牌号码 | NOT NULL, UNIQUE |
| vehicle_type | tinyint | 车辆类型：1-普通车辆，2-月租车辆，3-VIP车辆，4-黑名单车辆 | NOT NULL, DEFAULT 1 |
| owner_name | varchar(50) | 车主姓名 | NULL |
| owner_phone | varchar(20) | 车主电话 | NULL |
| status | tinyint | 状态：1-正常，0-禁用 | NOT NULL, DEFAULT 1 |
| create_time | datetime | 创建时间 | NOT NULL |
| update_time | datetime | 更新时间 | NOT NULL |

### 4. monthly_pass（月租通行证表）

存储月租车辆的通行证信息，包括有效期、状态等。

| 字段名 | 类型 | 说明 | 约束 |
|--------|------|------|------|
| id | int | 主键ID | PRIMARY KEY, AUTO_INCREMENT |
| plate_number | varchar(20) | 车牌号码 | NOT NULL, FOREIGN KEY |
| start_date | date | 开始日期 | NOT NULL |
| end_date | date | 结束日期 | NOT NULL |
| status | tinyint | 状态：1-有效，0-无效 | NOT NULL, DEFAULT 1 |
| create_time | datetime | 创建时间 | NOT NULL |
| update_time | datetime | 更新时间 | NOT NULL |

### 5. parking_record（停车记录表）

存储车辆的进出记录，包括入场时间、出场时间、停车费用等。

| 字段名 | 类型 | 说明 | 约束 |
|--------|------|------|------|
| id | int | 主键ID | PRIMARY KEY, AUTO_INCREMENT |
| parking_lot_id | int | 停车场ID | NOT NULL, FOREIGN KEY |
| plate_number | varchar(20) | 车牌号码 | NOT NULL |
| entry_time | datetime | 入场时间 | NOT NULL |
| exit_time | datetime | 出场时间 | NULL |
| parking_fee | decimal(10,2) | 停车费用 | NULL, DEFAULT 0.00 |
| payment_status | tinyint | 支付状态：0-未支付，1-已支付 | NOT NULL, DEFAULT 0 |
| payment_method | tinyint | 支付方式：1-现金，2-微信，3-支付宝，4-免费 | NULL |
| status | tinyint | 记录状态：1-进场，2-出场 | NOT NULL, DEFAULT 1 |
| create_time | datetime | 创建时间 | NOT NULL |
| update_time | datetime | 更新时间 | NOT NULL |

### 6. parking_fee_rule（停车收费规则表）

存储停车场的收费规则，包括收费标准、免费时长、每日封顶等。

| 字段名 | 类型 | 说明 | 约束 |
|--------|------|------|------|
| id | int | 主键ID | PRIMARY KEY, AUTO_INCREMENT |
| parking_lot_id | int | 停车场ID | NOT NULL, FOREIGN KEY |
| rule_name | varchar(100) | 规则名称 | NOT NULL |
| rule_type | tinyint | 规则类型：1-按小时收费，2-按次收费 | NOT NULL |
| fee_standard | text | 收费标准（JSON格式） | NOT NULL |
| free_minutes | int | 免费时长（分钟） | NOT NULL, DEFAULT 0 |
| daily_cap | decimal(10,2) | 每日封顶金额 | NULL |
| status | tinyint | 状态：1-启用，0-禁用 | NOT NULL, DEFAULT 1 |
| create_time | datetime | 创建时间 | NOT NULL |
| update_time | datetime | 更新时间 | NOT NULL |

## 索引设计

### parking_lot表索引
- PRIMARY KEY (`id`)

### gate_device表索引
- PRIMARY KEY (`id`)
- UNIQUE KEY `idx_device_sn` (`device_sn`)
- KEY `idx_parking_lot_id` (`parking_lot_id`)

### vehicle表索引
- PRIMARY KEY (`id`)
- UNIQUE KEY `idx_plate_number` (`plate_number`)

### monthly_pass表索引
- PRIMARY KEY (`id`)
- KEY `idx_plate_number` (`plate_number`)
- KEY `idx_end_date` (`end_date`)

### parking_record表索引
- PRIMARY KEY (`id`)
- KEY `idx_parking_lot_id` (`parking_lot_id`)
- KEY `idx_plate_number` (`plate_number`)
- KEY `idx_entry_time` (`entry_time`)
- KEY `idx_status` (`status`)

### parking_fee_rule表索引
- PRIMARY KEY (`id`)
- KEY `idx_parking_lot_id` (`parking_lot_id`)

## 表关系说明

1. **parking_lot与gate_device**：一对多关系，一个停车场可以有多个闸机设备
2. **parking_lot与parking_record**：一对多关系，一个停车场可以有多个停车记录
3. **parking_lot与parking_fee_rule**：一对多关系，一个停车场可以有多个收费规则
4. **vehicle与monthly_pass**：一对一关系，一个车辆可以有一个月租通行证
5. **vehicle与parking_record**：一对多关系，一个车辆可以有多个停车记录

## 数据字典

### 车辆类型（vehicle_type）
- 1: 普通车辆
- 2: 月租车辆
- 3: VIP车辆
- 4: 黑名单车辆

### 设备类型（device_type）
- 1: 入口闸机
- 2: 出口闸机

### 设备状态（status）
- 0: 离线
- 1: 在线

### 停车记录状态（status）
- 1: 进场
- 2: 出场

### 支付状态（payment_status）
- 0: 未支付
- 1: 已支付

### 支付方式（payment_method）
- 1: 现金
- 2: 微信
- 3: 支付宝
- 4: 免费

### 收费规则类型（rule_type）
- 1: 按小时收费
- 2: 按次收费