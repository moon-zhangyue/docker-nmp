# ThinkPHP8 MongoDB 五大特性实现

本项目展示了如何在ThinkPHP8框架中实现MongoDB的五大核心特性：**文档存储**、**地理空间**、**聚合框架**、**分片**和**副本集**。

## 📋 目录

- [项目概述](#项目概述)
- [MongoDB五大特性](#mongodb五大特性)
- [项目结构](#项目结构)
- [安装配置](#安装配置)
- [使用示例](#使用示例)
- [API接口](#api接口)
- [性能优化](#性能优化)
- [监控运维](#监控运维)

## 🎯 项目概述

本项目基于ThinkPHP8框架，全面实现了MongoDB的五大核心特性，包括：

1. **文档存储** - 产品目录管理系统
2. **地理空间** - 位置服务和地理查询
3. **聚合框架** - 用户行为分析和实时仪表盘
4. **分片** - IoT大数据处理
5. **副本集** - 全球数据复制和一致性

## 🚀 MongoDB五大特性

### 1. 文档存储 (Document Storage)

**实现场景**: 电商产品目录管理

**核心特性**:
- 灵活的文档结构，支持嵌套对象和数组
- 动态Schema，无需预定义表结构
- 丰富的数据类型支持

**实现文件**:
- 模型: `app/model/ProductCatalog.php`
- 服务: `app/service/ProductCatalogService.php`
- 控制器: `app/controller/ProductController.php`

**示例用法**:
```php
// 创建产品
$product = [
    'name' => 'iPhone 15 Pro',
    'sku' => 'IPHONE15PRO-128GB',
    'price' => 999.99,
    'attributes' => [
        'color' => 'Blue',
        'storage' => '128GB',
        'features' => ['Face ID', '5G', 'Wireless Charging']
    ],
    'variants' => [
        ['color' => 'Blue', 'storage' => '128GB', 'price' => 999.99],
        ['color' => 'Blue', 'storage' => '256GB', 'price' => 1099.99]
    ]
];

$result = $productService->createProduct($product);
```

### 2. 地理空间 (Geospatial)

**实现场景**: 位置服务和地理查询

**核心特性**:
- 2dsphere索引支持球面几何
- 地理空间查询($near, $geoWithin, $geoIntersects)
- 距离计算和路径规划

**实现文件**:
- 模型: `app/model/LocationService.php`
- 服务: `app/service/LocationService.php`
- 控制器: `app/controller/LocationController.php`

**示例用法**:
```php
// 查找附近的位置
$nearbyLocations = $locationService->findNearbyLocations(
    -73.9731, // 经度
    40.7589,  // 纬度
    1000,     // 1000米范围内
    ['limit' => 10, 'type' => 'store']
);

// 检查点是否在多边形内
$polygon = [
    [-73.9857, 40.7484],
    [-73.9857, 40.7584],
    [-73.9757, 40.7584],
    [-73.9757, 40.7484],
    [-73.9857, 40.7484] // 闭合多边形
];

$isInside = $locationService->findLocationsInArea($polygon);
```

### 3. 聚合框架 (Aggregation Framework)

**实现场景**: 用户行为分析和实时数据统计

**核心特性**:
- 强大的聚合管道操作
- 实时数据分析和统计
- 复杂的数据转换和计算

**实现文件**:
- 模型: `app/model/AnalyticsAggregation.php`
- 服务: `app/service/AnalyticsAggregationService.php`
- 控制器: `app/controller/AnalyticsController.php`

**示例用法**:
```php
// 获取用户行为分析
$userAnalysis = $analyticsService->getUserBehaviorSummary('user_001', [
    'start_date' => '2024-01-01',
    'end_date' => '2024-12-31',
    'events' => ['page_view', 'click', 'purchase']
]);

// 获取实时指标
$realTimeMetrics = $analyticsService->getRealTimeMetrics([
    'time_range' => '1h', // 最近1小时
    'metrics' => ['pv', 'uv', 'conversion_rate']
]);
```

### 4. 分片 (Sharding)

**实现场景**: IoT大数据处理

**核心特性**:
- 水平扩展，支持海量数据
- 自动数据分布和负载均衡
- 高可用性和容错能力

**实现文件**:
- 模型: `app/model/IoTDataSharded.php`
- 服务: `app/service/IoTDataShardedService.php`
- 控制器: `app/controller/IoTDataShardedController.php`

**示例用法**:
```php
// 批量处理IoT数据
$iotData = [
    [
        'device_id' => 'sensor_001',
        'timestamp' => time(),
        'data' => ['temperature' => 23.5, 'humidity' => 65.2]
    ],
    [
        'device_id' => 'sensor_002',
        'timestamp' => time(),
        'data' => ['pm25' => 12.3, 'co2' => 410]
    ]
];

$result = $iotService->batchProcessData($iotData);

// 获取设备分析数据
$deviceAnalysis = $iotService->getDeviceAnalysis('sensor_001', [
    'start_time' => strtotime('-7 days'),
    'end_time' => time(),
    'metrics' => ['avg', 'min', 'max']
]);
```

### 5. 副本集 (Replica Set)

**实现场景**: 全球数据复制和一致性

**核心特性**:
- 数据冗余和高可用性
- 自动故障转移
- 读写分离和负载均衡

**实现文件**:
- 模型: `app/model/GlobalDataReplication.php`
- 服务: `app/service/GlobalDataReplicationService.php`
- 控制器: `app/controller/GlobalDataController.php`

**示例用法**:
```php
// 创建全球数据记录
$globalData = [
    'global_id' => 'config_001',
    'data_type' => 'configuration',
    'data' => ['feature_flags' => ['new_ui' => true]],
    'regions' => ['us-east', 'eu-central', 'asia-pacific']
];

$result = $globalService->createGlobalRecord($globalData);

// 获取区域数据
$regionalData = $globalService->getRegionalData('us-east', [
    'data_type' => 'configuration',
    'include_sync_status' => true
]);
```

## 📁 项目结构

```
thinkphp8/
├── app/
│   ├── controller/          # 控制器层
│   │   ├── ProductController.php
│   │   ├── LocationController.php
│   │   ├── AnalyticsController.php
│   │   ├── IoTDataShardedController.php
│   │   └── GlobalDataController.php
│   ├── service/            # 服务层
│   │   ├── ProductCatalogService.php
│   │   ├── LocationService.php
│   │   ├── AnalyticsAggregationService.php
│   │   ├── IoTDataShardedService.php
│   │   └── GlobalDataReplicationService.php
│   └── model/              # 模型层
│       ├── ProductCatalog.php
│       ├── LocationService.php
│       ├── AnalyticsAggregation.php
│       ├── IoTDataSharded.php
│       └── GlobalDataReplication.php
├── config/
│   └── mongodb.php         # MongoDB配置文件
├── database/
│   ├── migrations/         # 数据库迁移
│   └── mongodb_init.js     # MongoDB初始化脚本
├── route/
│   └── api.php            # API路由配置
└── README_MongoDB.md      # 本文档
```

## ⚙️ 安装配置

### 1. 环境要求

- PHP >= 8.1
- ThinkPHP >= 8.0
- MongoDB >= 5.0
- MongoDB PHP Extension >= 1.15

### 2. 安装MongoDB PHP驱动

```bash
# 安装MongoDB PHP扩展
pecl install mongodb

# 在php.ini中启用扩展
echo "extension=mongodb" >> php.ini
```

### 3. 安装Composer依赖

```bash
composer require mongodb/mongodb
```

### 4. 配置环境变量

在`.env`文件中添加MongoDB配置：

```env
# MongoDB基础配置
MONGODB_URI=mongodb://localhost:27017
MONGODB_DATABASE=thinkphp_mongodb

# 副本集配置
MONGODB_REPLICA_URI=mongodb://mongo1:27017,mongo2:27017,mongo3:27017/?replicaSet=rs0
MONGODB_REPLICA_DATABASE=thinkphp_replica

# 分片集群配置
MONGODB_SHARDED_URI=mongodb://mongos1:27017,mongos2:27017
MONGODB_SHARDED_DATABASE=thinkphp_sharded

# 日志配置
MONGODB_ENABLE_QUERY_LOG=true
MONGODB_LOG_LEVEL=info
```

### 5. 初始化数据库

```bash
# 执行MongoDB初始化脚本
mongo thinkphp_mongodb database/mongodb_init.js

# 或者使用mongosh
mongosh thinkphp_mongodb database/mongodb_init.js
```

## 🔧 使用示例

### 产品目录管理

```php
// 创建产品
POST /api/products/create
{
    "name": "MacBook Pro 16\"",
    "sku": "MBP16-M3-512GB",
    "price": 2499.99,
    "category_id": "laptops",
    "attributes": {
        "processor": "M3 Pro",
        "memory": "18GB",
        "storage": "512GB SSD"
    }
}

// 搜索产品
GET /api/products/search?q=MacBook&category=laptops&min_price=1000&max_price=3000
```

### 地理位置服务

```php
// 添加位置
POST /api/locations/add
{
    "name": "Apple Store",
    "type": "store",
    "longitude": -73.9731,
    "latitude": 40.7589,
    "address": "767 5th Ave, New York"
}

// 查找附近位置
GET /api/locations/nearby?lng=-73.9731&lat=40.7589&distance=1000&type=store
```

### 用户行为分析

```php
// 记录事件
POST /api/analytics/event
{
    "user_id": "user_001",
    "event_type": "product_view",
    "properties": {
        "product_id": "MBP16-M3-512GB",
        "category": "laptops"
    }
}

// 获取用户分析
GET /api/analytics/user/user_001?start_date=2024-01-01&end_date=2024-12-31
```

### IoT数据处理

```php
// 批量上传IoT数据
POST /api/iot/batch-data
{
    "data": [
        {
            "device_id": "sensor_001",
            "data": {
                "temperature": 23.5,
                "humidity": 65.2
            }
        }
    ]
}

// 获取设备指标
GET /api/iot/device-metrics/sensor_001?start_time=1640995200&end_time=1641081600
```

### 全球数据管理

```php
// 创建全球记录
POST /api/global-data/create
{
    "global_id": "config_001",
    "data_type": "configuration",
    "data": {
        "feature_flags": {
            "new_checkout": true
        }
    },
    "regions": ["us-east", "eu-central"]
}

// 获取区域数据
GET /api/global-data/regional/us-east?data_type=configuration
```

## 📊 API接口

### 产品管理接口

| 方法 | 路径 | 描述 | 权限 |
|------|------|------|------|
| POST | `/api/products/create` | 创建产品 | admin, operator |
| PUT | `/api/products/update` | 更新产品 | admin, operator |
| GET | `/api/products/search` | 搜索产品 | 公开 |
| GET | `/api/products/detail/:id` | 获取产品详情 | 公开 |

### 位置服务接口

| 方法 | 路径 | 描述 | 权限 |
|------|------|------|------|
| POST | `/api/locations/add` | 添加位置 | admin, operator |
| GET | `/api/locations/nearby` | 查找附近位置 | 公开 |
| POST | `/api/locations/check-polygon` | 检查点在多边形内 | 公开 |

### 分析统计接口

| 方法 | 路径 | 描述 | 权限 |
|------|------|------|------|
| POST | `/api/analytics/event` | 记录事件 | operator, admin |
| GET | `/api/analytics/user/:id` | 获取用户分析 | viewer, operator, admin |
| GET | `/api/analytics/dashboard` | 实时仪表盘 | viewer, operator, admin |

### IoT数据接口

| 方法 | 路径 | 描述 | 权限 |
|------|------|------|------|
| POST | `/api/iot/batch-data` | 批量数据上传 | admin, operator |
| GET | `/api/iot/device-metrics/:id` | 设备指标 | viewer, operator, admin |
| POST | `/api/iot/archive-data` | 归档数据 | admin |

### 全球数据接口

| 方法 | 路径 | 描述 | 权限 |
|------|------|------|------|
| POST | `/api/global-data/create` | 创建全球记录 | admin, operator |
| GET | `/api/global-data/regional/:region` | 获取区域数据 | viewer, operator, admin |
| POST | `/api/global-data/replicate` | 复制数据 | admin |

## ⚡ 性能优化

### 1. 索引优化

```javascript
// 复合索引
db.products.createIndex({"category_id": 1, "price": 1})

// 文本搜索索引
db.products.createIndex({"name": "text", "description": "text"})

// 地理空间索引
db.locations.createIndex({"location": "2dsphere"})

// 时间序列索引
db.iot_data.createIndex({"device_id": 1, "timestamp": -1})
```

### 2. 查询优化

```php
// 使用投影减少数据传输
$options = [
    'projection' => [
        'name' => 1,
        'price' => 1,
        'category_id' => 1
    ]
];

// 使用限制和排序
$options = [
    'limit' => 20,
    'sort' => ['created_at' => -1]
];

// 使用聚合管道优化
$pipeline = [
    ['$match' => ['status' => 'active']],
    ['$group' => [
        '_id' => '$category_id',
        'count' => ['$sum' => 1],
        'avg_price' => ['$avg' => '$price']
    ]],
    ['$sort' => ['count' => -1]]
];
```

### 3. 连接池配置

```php
// 在config/mongodb.php中优化连接池
'options' => [
    'maxPoolSize' => 100,
    'minPoolSize' => 5,
    'maxIdleTimeMS' => 300000,
    'connectTimeoutMS' => 10000,
    'socketTimeoutMS' => 30000
]
```

### 4. 缓存策略

```php
// 查询结果缓存
$cacheKey = 'products_' . md5(json_encode($query));
$result = Cache::remember($cacheKey, 300, function() use ($query) {
    return $this->model->find($query);
});

// 聚合结果缓存
$cacheKey = 'analytics_' . $userId . '_' . date('Y-m-d');
$result = Cache::remember($cacheKey, 600, function() use ($userId) {
    return $this->getUserAnalytics($userId);
});
```

## 📈 监控运维

### 1. 性能监控

```php
// 慢查询监控
if ($queryTime > 1000) {
    Log::warning('慢查询检测', [
        'query' => $query,
        'time' => $queryTime,
        'collection' => $collection
    ]);
}

// 连接池监控
$poolStats = $this->getConnectionPoolStats();
if ($poolStats['active_connections'] > 80) {
    Log::warning('连接池使用率过高', $poolStats);
}
```

### 2. 错误处理

```php
try {
    $result = $collection->insertOne($document);
} catch (MongoDB\Driver\Exception\BulkWriteException $e) {
    Log::error('批量写入失败', [
        'error' => $e->getMessage(),
        'writeErrors' => $e->getWriteResult()->getWriteErrors()
    ]);
} catch (MongoDB\Driver\Exception\ConnectionTimeoutException $e) {
    Log::error('连接超时', ['error' => $e->getMessage()]);
}
```

### 3. 健康检查

```php
// 数据库连接检查
public function healthCheck(): array
{
    try {
        $result = $this->client->selectDatabase('admin')
                             ->command(['ping' => 1]);
        
        return [
            'status' => 'healthy',
            'response_time' => $this->getResponseTime(),
            'connections' => $this->getConnectionStats()
        ];
    } catch (\Exception $e) {
        return [
            'status' => 'unhealthy',
            'error' => $e->getMessage()
        ];
    }
}
```

## 🔒 安全配置

### 1. 认证授权

```javascript
// 创建应用用户
db.createUser({
    user: "thinkphp_app",
    pwd: "secure_password",
    roles: [
        { role: "readWrite", db: "thinkphp_mongodb" }
    ]
})

// 创建只读用户
db.createUser({
    user: "thinkphp_readonly",
    pwd: "readonly_password",
    roles: [
        { role: "read", db: "thinkphp_mongodb" }
    ]
})
```

### 2. 网络安全

```yaml
# mongod.conf
net:
  bindIp: 127.0.0.1,10.0.0.1
  port: 27017
  ssl:
    mode: requireSSL
    PEMKeyFile: /path/to/mongodb.pem
```

### 3. 数据加密

```javascript
// 字段级加密配置
db.createCollection("sensitive_data", {
    encryptedFields: {
        "ssn": {
            keyId: UUID("12345678-1234-1234-1234-123456789012"),
            bsonType: "string",
            algorithm: "AEAD_AES_256_CBC_HMAC_SHA_512-Deterministic"
        }
    }
})
```

## 🚀 部署建议

### 1. 生产环境配置

```yaml
# docker-compose.yml
version: '3.8'
services:
  mongodb-primary:
    image: mongo:7.0
    command: mongod --replSet rs0 --bind_ip_all
    environment:
      MONGO_INITDB_ROOT_USERNAME: admin
      MONGO_INITDB_ROOT_PASSWORD: password
    volumes:
      - mongodb_data:/data/db
    ports:
      - "27017:27017"
  
  mongodb-secondary1:
    image: mongo:7.0
    command: mongod --replSet rs0 --bind_ip_all
    depends_on:
      - mongodb-primary
  
  mongodb-secondary2:
    image: mongo:7.0
    command: mongod --replSet rs0 --bind_ip_all
    depends_on:
      - mongodb-primary
```

### 2. 备份策略

```bash
#!/bin/bash
# 备份脚本
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backup/mongodb"

# 创建备份
mongodump --host localhost:27017 --db thinkphp_mongodb --out $BACKUP_DIR/$DATE

# 压缩备份
tar -czf $BACKUP_DIR/mongodb_backup_$DATE.tar.gz -C $BACKUP_DIR $DATE

# 清理旧备份（保留7天）
find $BACKUP_DIR -name "mongodb_backup_*.tar.gz" -mtime +7 -delete
```

### 3. 监控告警

```yaml
# prometheus配置
- job_name: 'mongodb'
  static_configs:
    - targets: ['localhost:9216']
  scrape_interval: 30s
  metrics_path: /metrics
```

## 📚 参考资料

- [MongoDB官方文档](https://docs.mongodb.com/)
- [ThinkPHP8官方文档](https://www.kancloud.cn/manual/thinkphp8/)
- [MongoDB PHP驱动文档](https://docs.mongodb.com/php-library/)
- [MongoDB最佳实践](https://docs.mongodb.com/manual/administration/production-notes/)

## 🤝 贡献指南

1. Fork本项目
2. 创建特性分支 (`git checkout -b feature/AmazingFeature`)
3. 提交更改 (`git commit -m 'Add some AmazingFeature'`)
4. 推送到分支 (`git push origin feature/AmazingFeature`)
5. 开启Pull Request

## 📄 许可证

本项目采用MIT许可证 - 查看 [LICENSE](LICENSE) 文件了解详情。

## 📞 联系方式

如有问题或建议，请通过以下方式联系：

- 项目Issues: [GitHub Issues](https://github.com/your-repo/issues)
- 邮箱: your-email@example.com

---

**注意**: 本项目仅用于学习和演示目的，生产环境使用前请进行充分的测试和安全评估。