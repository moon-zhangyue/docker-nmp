# 停车场监控系统 InfluxDB 使用指南

## 1. 概述

本文档提供了停车场监控系统中使用InfluxDB时序数据库的详细指南，包括适合存储的数据类型、数据收集方法和查询分析方法。

### 1.1 什么是InfluxDB

InfluxDB是一个开源的时序数据库，专为高效存储和查询时间序列数据而设计。它特别适合用于监控数据、传感器数据、实时分析等场景，这些场景通常具有高写入量、按时间范围查询和聚合分析的特点。

### 1.2 为什么在停车场系统中使用InfluxDB

停车场系统产生大量的时序数据，如车辆进出事件、车位占用率变化、设备状态等。这些数据具有以下特点：

- 按时间顺序写入，很少更新
- 查询通常基于时间范围
- 需要进行时间聚合分析（如高峰期识别）
- 数据量大，需要高效存储和查询

使用InfluxDB可以提供比传统关系型数据库更高效的存储和查询性能，同时支持更丰富的时间序列分析功能。

## 2. 适合存储在InfluxDB的数据类型

### 2.1 车辆进出事件

- **数据特点**：高频写入、按时间查询、很少更新
- **存储内容**：车牌号、进出时间、闸机ID、识别结果等
- **使用场景**：流量分析、高峰期识别、异常事件监控

### 2.2 车位占用率

- **数据特点**：定期采样、趋势分析、历史比较
- **存储内容**：总车位数、已占用车位数、占用率、时间戳
- **使用场景**：实时监控、历史趋势分析、预测分析

### 2.3 设备状态监控

- **数据特点**：定期心跳、状态变化、性能指标
- **存储内容**：设备ID、在线状态、响应时间、错误计数、电池电量等
- **使用场景**：设备健康监控、故障预警、性能分析

### 2.4 车牌识别性能

- **数据特点**：每次识别记录、性能指标
- **存储内容**：识别时间、置信度、成功率、光照条件等
- **使用场景**：识别算法优化、环境因素分析

### 2.5 高峰期分析数据

- **数据特点**：聚合数据、周期性模式
- **存储内容**：时间段、进出车辆数、平均停车时长、周转率等
- **使用场景**：高峰期预测、资源调度优化

### 2.6 停车时长统计

- **数据特点**：聚合数据、分布分析
- **存储内容**：短期/中期/长期停车数量、平均停车时长、最大/最小停车时长
- **使用场景**：停车行为分析、定价策略优化

### 2.7 闸机操作日志

- **数据特点**：事件记录、操作审计
- **存储内容**：操作类型、触发来源、操作结果、响应时间等
- **使用场景**：安全审计、故障诊断

## 3. 不适合存储在InfluxDB的数据

以下数据类型不适合存储在InfluxDB中，应继续使用关系型数据库：

- **用户账户信息**：需要关系模型和事务支持
- **支付记录**：需要ACID保证和关联查询
- **月卡信息**：需要复杂的关系和更新操作
- **车辆基本信息**：需要关联查询和频繁更新
- **停车场配置信息**：低频写入，高频读取，需要事务支持

## 4. 数据收集方法

### 4.1 使用InfluxDBService类

系统提供了`InfluxDBService`类，封装了与InfluxDB交互的方法。主要方法包括：

```php
// 写入停车场事件数据
$influxDBService->writeParkingEvent(string $eventType, array $eventData);

// 写入车位占用率数据
$influxDBService->writeOccupancyRate(string $parkingLotId, array $occupancyData);

// 写入设备状态数据
$influxDBService->writeDeviceStatus(string $deviceId, array $statusData);

// 写入车牌识别性能数据
$influxDBService->writePlateRecognitionMetrics(string $deviceId, array $recognitionData);

// 写入高峰期分析数据
$influxDBService->writePeakHoursData(string $parkingLotId, array $peakData);

// 写入停车时长统计数据
$influxDBService->writeParkingDurationStats(string $parkingLotId, array $durationData);

// 写入闸机操作日志
$influxDBService->writeGateOperation(string $deviceId, array $operationData);
```

### 4.2 数据收集时机

- **车辆进出事件**：在闸机控制器的开闸/关闸方法中调用
- **车位占用率**：定时任务每5-15分钟采集一次，或在车辆进出时更新
- **设备状态**：设备心跳接口中收集，通常每1-5分钟一次
- **车牌识别性能**：每次识别完成后记录
- **高峰期和停车时长统计**：定时任务每小时或每天汇总一次

### 4.3 示例：在闸机控制器中记录事件

```php
// 在GateController的openGate方法中添加
public function openGate(Request $request)
{
    // 现有代码...
    
    // 记录进场事件到InfluxDB
    if ($device->type == GateDevice::TYPE_ENTRANCE || $device->type == GateDevice::TYPE_BOTH) {
        $eventData = [
            'parking_lot_id' => $parkingLot->id,
            'gate_id' => $deviceId,
            'plate_number' => $plateNumber,
            'vehicle_type' => $vehicle ? $vehicle->type : 'unknown',
            'device_type' => $device->type,
            'timestamp' => time(),
            'success' => true,
            'recognition_time' => $request->param('recognition_time', 0)
        ];
        
        app('influxdb')->writeParkingEvent('entry', $eventData);
    }
    
    // 现有代码...
}
```

## 5. 数据查询和分析

### 5.1 使用InfluxDBService查询方法

```php
// 获取高峰期分析数据
$influxDBService->getPeakHoursAnalysis(string $parkingLotId, string $startTime, string $endTime, string $groupBy);

// 获取车位占用率历史数据
$influxDBService->getOccupancyRateHistory(string $parkingLotId, string $startTime, string $endTime, string $groupBy);

// 获取设备状态历史数据
$influxDBService->getDeviceStatusHistory(string $deviceId, string $startTime, string $endTime);

// 自定义查询
$influxDBService->query(string $query);
```

### 5.2 常见分析场景

#### 5.2.1 识别高峰时段

```php
// 获取一周内的高峰期数据，按小时分组
$startTime = date('Y-m-d', strtotime('-7 days'));
$endTime = date('Y-m-d');
$peakData = $influxDBService->getPeakHoursAnalysis($parkingLotId, $startTime, $endTime, '1h');
```

#### 5.2.2 分析车位占用率趋势

```php
// 获取一个月的占用率数据，按天分组
$startTime = date('Y-m-d', strtotime('-30 days'));
$endTime = date('Y-m-d');
$occupancyData = $influxDBService->getOccupancyRateHistory($parkingLotId, $startTime, $endTime, '1d');
```

#### 5.2.3 监控设备健康状态

```php
// 获取设备一周的状态历史
$startTime = date('Y-m-d', strtotime('-7 days'));
$endTime = date('Y-m-d');
$deviceData = $influxDBService->getDeviceStatusHistory($deviceId, $startTime, $endTime);
```

## 6. 最佳实践

### 6.1 数据保留策略

根据数据重要性和查询频率设置不同的保留策略：

- **高精度数据**（原始事件）：保留30-90天
- **中精度数据**（小时聚合）：保留6-12个月
- **低精度数据**（天/周聚合）：保留2-5年

### 6.2 标签和字段使用建议

- **标签**：用于筛选和分组的维度（如停车场ID、设备ID、事件类型）
- **字段**：用于存储测量值（如占用率、响应时间、计数值）

### 6.3 性能优化

- 使用批量写入减少网络开销
- 合理设置数据采集频率，避免过度采集
- 对长期存储的数据进行降采样和聚合
- 使用连续查询自动计算和存储聚合数据

### 6.4 监控InfluxDB自身

使用系统监控功能监控InfluxDB服务器的性能：

```php
// 记录InfluxDB服务器性能指标
$metrics = [
    'cpu' => [...],
    'memory' => [...],
    'disk' => [...]
];
$influxDBService->writeSystemMetrics($metrics);
```

## 7. 故障排除

### 7.1 常见问题

- **写入失败**：检查连接配置、网络连接和认证信息
- **查询返回空结果**：检查时间范围、过滤条件和数据保留策略
- **性能下降**：检查查询复杂度、数据量和服务器资源

### 7.2 日志分析

InfluxDBService会记录错误到系统日志，可以通过查看日志文件排查问题：

```php
Log::error('InfluxDB写入失败', [
    'error' => $e->getMessage(),
    'measurement' => $measurement,
    'tags' => $tags,
    'fields' => $fields
]);
```

## 8. 结论

在停车场监控系统中，合理使用InfluxDB可以显著提升系统对时序数据的处理能力，为实时监控和历史数据分析提供强大支持。通过本指南中的最佳实践，可以充分利用InfluxDB的优势，同时避免不适合的使用场景带来的问题。

将监控指标、事件数据和统计分析数据存储在InfluxDB中，而将用户信息、交易数据和业务配置存储在关系型数据库中，可以实现最佳的系统性能和数据管理效率。