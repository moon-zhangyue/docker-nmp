# 停车场闸机系统 - 核心模块说明

## 1. 停车场管理模块

停车场管理模块负责管理系统中的停车场信息，包括停车场的基本信息、车位状态和运营数据等。

### 功能描述

- **停车场信息管理**：添加、编辑、删除和查询停车场信息，包括名称、地址、联系方式等基本信息。
- **车位状态监控**：实时监控停车场的车位使用情况，包括总车位数、已占用车位数和可用车位数。
- **停车场状态管理**：管理停车场的运营状态，如正常营业或暂停营业。
- **车位占用率统计**：计算并展示停车场的车位占用率，为管理决策提供数据支持。

### 核心方法

- `getAvailableSpaces()`：计算当前可用车位数
- `getOccupancyRate()`：计算车位占用率
- `updateOccupiedSpaces()`：更新已占用车位数
- `checkCapacity()`：检查停车场是否已满

### 数据流程

1. 车辆入场时，系统调用`updateOccupiedSpaces()`方法增加已占用车位数
2. 车辆出场时，系统调用`updateOccupiedSpaces()`方法减少已占用车位数
3. 管理界面实时显示`getAvailableSpaces()`和`getOccupancyRate()`的计算结果
4. 当`checkCapacity()`返回停车场已满时，入场闸机将拒绝新车入场

## 2. 闸机控制模块

闸机控制模块负责管理和控制停车场的入口和出口闸机设备，实现车辆的自动识别和通行控制。

### 功能描述

- **闸机设备管理**：添加、编辑、删除和查询闸机设备信息，包括设备名称、序列号、IP地址等。
- **设备状态监控**：实时监控闸机设备的在线状态和工作状态。
- **远程控制**：远程开启或关闭闸机，处理特殊情况。
- **设备故障报警**：当设备离线或发生故障时，系统自动报警并通知管理员。

### 核心方法

- `openGate()`：开启闸机
- `closeGate()`：关闭闸机
- `checkDeviceStatus()`：检查设备状态
- `handleVehicleEntry()`：处理车辆入场
- `handleVehicleExit()`：处理车辆出场

### 数据流程

1. 车辆到达入口闸机时，车牌识别设备识别车牌并调用`handleVehicleEntry()`方法
2. 系统验证车辆信息后，调用`openGate()`方法开启闸机
3. 车辆通过后，系统自动调用`closeGate()`方法关闭闸机
4. 车辆到达出口闸机时，系统调用`handleVehicleExit()`方法处理出场流程
5. 系统定期调用`checkDeviceStatus()`方法监控设备状态

## 3. 车辆管理模块

车辆管理模块负责管理系统中的车辆信息，包括车辆基本信息、车辆类型和月租车辆管理等。

### 功能描述

- **车辆信息管理**：添加、编辑、删除和查询车辆信息，包括车牌号、车主信息等。
- **车辆类型管理**：管理不同类型的车辆，如普通车辆、月租车辆、VIP车辆和黑名单车辆。
- **月租车辆管理**：管理月租车辆的通行证信息，包括有效期、状态等。
- **黑名单管理**：管理黑名单车辆，限制其进入停车场。

### 核心方法

- `registerVehicle()`：注册新车辆
- `updateVehicleInfo()`：更新车辆信息
- `checkVehicleType()`：检查车辆类型
- `isMonthlyPass()`：检查是否为月租车辆
- `isBlacklisted()`：检查是否为黑名单车辆
- `createMonthlyPass()`：创建月租通行证
- `renewMonthlyPass()`：续期月租通行证
- `checkMonthlyPassValidity()`：检查月租通行证有效性

### 数据流程

1. 车辆首次进入停车场时，系统调用`registerVehicle()`方法注册车辆信息
2. 车辆入场时，系统调用`checkVehicleType()`方法确定车辆类型
3. 对于月租车辆，系统调用`isMonthlyPass()`和`checkMonthlyPassValidity()`方法验证通行证
4. 对于黑名单车辆，系统调用`isBlacklisted()`方法并拒绝其入场
5. 月租车辆到期前，系统提醒车主调用`renewMonthlyPass()`方法续期

## 4. 停车记录模块

停车记录模块负责管理车辆的进出记录，包括入场时间、出场时间、停车费用等信息。

### 功能描述

- **进出记录管理**：记录车辆的进出信息，包括入场时间、出场时间、车牌号等。
- **停车时长计算**：自动计算车辆的停车时长。
- **停车费用计算**：根据收费规则自动计算停车费用。
- **历史记录查询**：提供多种条件的历史记录查询功能。
- **数据统计分析**：对停车记录进行统计分析，生成各类报表。

### 核心方法

- `createEntryRecord()`：创建入场记录
- `updateExitRecord()`：更新出场记录
- `calculateParkingDuration()`：计算停车时长
- `calculateParkingFee()`：计算停车费用
- `queryRecords()`：查询停车记录
- `generateStatistics()`：生成统计数据

### 数据流程

1. 车辆入场时，系统调用`createEntryRecord()`方法创建入场记录
2. 车辆出场时，系统调用`calculateParkingDuration()`方法计算停车时长
3. 系统根据停车时长和收费规则，调用`calculateParkingFee()`方法计算停车费用
4. 用户支付后，系统调用`updateExitRecord()`方法更新出场记录
5. 管理员可以通过`queryRecords()`方法查询历史记录
6. 系统定期调用`generateStatistics()`方法生成统计报表

## 5. 收费规则模块

收费规则模块负责管理停车场的收费标准，支持多种收费模式和灵活的规则配置。

### 功能描述

- **收费规则管理**：添加、编辑、删除和查询收费规则，支持多种收费模式。
- **收费模式配置**：支持按小时收费、按次收费等多种收费模式。
- **免费时段设置**：配置免费停车时长，如首15分钟免费。
- **每日封顶设置**：设置每日最高收费限额。
- **特殊车辆规则**：为VIP车辆、月租车辆等特殊车辆配置专属收费规则。

### 核心方法

- `createRule()`：创建收费规则
- `updateRule()`：更新收费规则
- `applyRule()`：应用收费规则计算费用
- `calculateHourlyFee()`：计算按小时收费的费用
- `calculateFixedFee()`：计算按次收费的费用
- `applyFreeMinutes()`：应用免费时长
- `applyDailyCap()`：应用每日封顶金额

### 数据流程

1. 管理员通过`createRule()`或`updateRule()`方法配置收费规则
2. 车辆出场时，系统根据车辆类型和停车时长，调用`applyRule()`方法选择适用的收费规则
3. 系统根据规则类型，调用`calculateHourlyFee()`或`calculateFixedFee()`方法计算基础费用
4. 系统调用`applyFreeMinutes()`方法应用免费时长政策
5. 系统调用`applyDailyCap()`方法确保费用不超过每日封顶金额
6. 最终计算结果显示给用户并用于支付

## 模块间交互

### 车辆入场流程

1. **闸机控制模块**：车牌识别设备识别车牌
2. **车辆管理模块**：验证车辆信息和类型
3. **停车场管理模块**：检查停车场容量
4. **停车记录模块**：创建入场记录
5. **停车场管理模块**：更新已占用车位数
6. **闸机控制模块**：开启闸机放行

### 车辆出场流程

1. **闸机控制模块**：车牌识别设备识别车牌
2. **停车记录模块**：查询入场记录，计算停车时长
3. **收费规则模块**：应用收费规则计算费用
4. **停车记录模块**：更新出场记录和支付信息
5. **停车场管理模块**：更新已占用车位数
6. **闸机控制模块**：开启闸机放行

## 技术实现

系统采用ThinkPHP 8.0框架开发，采用MVC架构模式：

- **模型层（Model）**：包含ParkingLot、GateDevice、Vehicle、MonthlyPass、ParkingRecord、ParkingFeeRule等模型类，负责数据访问和业务逻辑
- **视图层（View）**：采用前后端分离架构，前端使用Vue.js构建用户界面
- **控制器层（Controller）**：包含ParkingLotController、GateController、VehicleController、ParkingRecordController、ParkingFeeRuleController等控制器，负责处理用户请求和返回响应

系统还利用Redis缓存提高性能，使用Kafka消息队列处理异步任务，如设备通信和数据统计等。