# MongoDB 特性使用指南

本文档详细介绍了在 ThinkPHP 8 框架下，如何利用 MongoDB 的核心特性来解决不同应用场景的问题。

## 目录

- [动态模式 - 电商产品目录](#动态模式---电商产品目录)
- [高扩展性 - 物联网数据存储](#高扩展性---物联网数据存储)
- [地理空间支持 - LBS应用](#地理空间支持---lbs应用)
- [聚合框架 - 数据分析](#聚合框架---数据分析)
- [副本集与分片 - 高可用性](#副本集与分片---高可用性)
- [配置说明](#配置说明)

## 动态模式 - 电商产品目录

MongoDB 的动态模式特性非常适合电商产品目录这类需要频繁变更字段的场景。

### 模型层设计

```php
<?php
declare(strict_types=1);

namespace app\model;

use think\Model;
use think\model\concern\SoftDelete;

class Product extends Model
{
    use SoftDelete;

    // 设置MongoDB连接
    protected $connection = 'mongo';

    // 设置集合名称
    protected $table = 'products';

    // 设置主键
    protected $pk = '_id';

    // 自动时间戳
    protected $autoWriteTimestamp = true;

    // 允许写入的字段（动态模式下，可以不严格限制字段）
    protected $field = true;

    /**
     * 添加产品（支持动态字段）
     */
    public static function addProduct(array $data): Product
    {
        return self::create($data);
    }

    /**
     * 更新产品（支持动态字段）
     */
    public static function updateProduct($id, array $data): bool
    {
        return self::find($id)->save($data);
    }

    /**
     * 查询产品（支持动态字段查询）
     */
    public static function findProducts(array $condition = []): array
    {
        return self::where($condition)->select()->toArray();
    }
}
```

### 使用示例

```php
// 创建具有不同属性的产品
$laptop = [
    'name' => '高性能笔记本',
    'category' => '电脑',
    'price' => 5999,
    'specifications' => [
        'cpu' => 'Intel i7',
        'memory' => '16GB',
        'storage' => '512GB SSD'
    ],
    'colors' => ['黑色', '银色']
];

$phone = [
    'name' => '智能手机',
    'category' => '手机',
    'price' => 3999,
    'specifications' => [
        'screen' => '6.7英寸',
        'camera' => '4800万像素',
        'battery' => '5000mAh'
    ],
    'colors' => ['黑色', '白色', '蓝色'],
    'has_5g' => true
];

// 保存不同结构的产品
$laptopProduct = Product::addProduct($laptop);
$phoneProduct = Product::addProduct($phone);

// 根据任意字段查询
$phones = Product::findProducts(['category' => '手机', 'has_5g' => true]);
$blackProducts = Product::findProducts(['colors' => '黑色']);
```

## 高扩展性 - 物联网数据存储

MongoDB 的高扩展性特性适合物联网等大规模数据存储场景。

### 核心设计

```php
<?php
declare(strict_types=1);

namespace app\model;

use think\Model;
use think\facade\Log;

class IoTData extends Model
{
    // 设置MongoDB连接
    protected $connection = 'mongo';

    // 设置集合名称
    protected $table = 'iot_data';

    /**
     * 批量保存IoT设备数据
     */
    public static function batchSave(array $dataList): bool
    {
        try {
            // 使用事务保证数据一致性
            return self::startTrans(function() use ($dataList) {
                foreach ($dataList as $data) {
                    self::create($data);
                }
                return true;
            });
        } catch (\Exception $e) {
            Log::error('批量保存IoT数据失败', ['message' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * 根据时间范围分页查询数据
     */
    public static function getDataByTimeRange(
        string $deviceId,
        string $startTime,
        string $endTime,
        int $page = 1,
        int $limit = 20
    ): array
    {
        try {
            return self::where('device_id', $deviceId)
                ->where('create_time', 'between', [strtotime($startTime), strtotime($endTime)])
                ->page($page, $limit)
                ->order('create_time', 'desc')
                ->select()
                ->toArray();
        } catch (\Exception $e) {
            Log::error('查询IoT数据失败', [
                'device_id' => $deviceId,
                'time_range' => [$startTime, $endTime],
                'message' => $e->getMessage()
            ]);
            return [];
        }
    }
}
```

### 使用示例

```php
// 批量接收设备数据
$dataList = [
    [
        'device_id' => 'device001',
        'temperature' => 25.6,
        'humidity' => 65,
        'battery' => 85,
        'timestamp' => time(),
        'location' => [116.397428, 39.90923]
    ],
    [
        'device_id' => 'device002',
        'temperature' => 26.1,
        'humidity' => 62,
        'battery' => 90,
        'timestamp' => time(),
        'location' => [116.398438, 39.90933]
    ]
    // 可以批量添加成千上万条数据
];

// 保存批量数据
IoTData::batchSave($dataList);

// 时间范围查询
$historyData = IoTData::getDataByTimeRange(
    'device001',
    '2023-10-01 00:00:00',
    '2023-10-31 23:59:59',
    1,
    100
);
```

## 地理空间支持 - LBS应用

MongoDB 提供内置地理空间索引和查询功能，非常适合基于位置的服务应用。

### 核心设计

```php
// 位置信息模型
class Location extends Model
{
    // MongoDB连接
    protected $connection = 'mongo';

    /**
     * 创建位置信息
     */
    public static function createLocation(array $data): ?Location
    {
        try {
            // 构建地理位置点数据
            if (isset($data['longitude']) && isset($data['latitude'])) {
                $data['location'] = [
                    'type' => 'Point',
                    'coordinates' => [
                        floatval($data['longitude']),
                        floatval($data['latitude'])
                    ]
                ];
            }

            return self::create($data);
        } catch (\Exception $e) {
            Log::error('创建位置信息失败', ['data' => $data, 'message' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * 根据距离查询附近的位置
     */
    public static function findNearby(
        float $longitude,
        float $latitude,
        int $distance = 1000,
        array $filter = [],
        int $limit = 20
    ): array
    {
        try {
            // 构建地理空间查询
            $geoNear = [
                '$geoNear' => [
                    'near' => [
                        'type' => 'Point',
                        'coordinates' => [$longitude, $latitude]
                    ],
                    'distanceField' => 'distance',
                    'maxDistance' => $distance,
                    'spherical' => true,
                    'query' => $filter
                ]
            ];

            // 限制数量
            $limit = ['$limit' => $limit];

            // 执行聚合查询
            $result = self::mongoAggregate([$geoNear, $limit]);

            return $result ?: [];
        } catch (\Exception $e) {
            Log::error('查询附近位置失败', [
                'coordinates' => [$longitude, $latitude],
                'distance' => $distance,
                'message' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * 根据多边形区域查询位置
     */
    public static function findInPolygon(array $polygon, array $filter = []): array
    {
        try {
            // 确保多边形闭合
            if ($polygon[0] !== end($polygon)) {
                $polygon[] = $polygon[0];
            }

            // 构建地理空间查询条件
            $condition = array_merge($filter, [
                'location' => [
                    '$geoWithin' => [
                        '$geometry' => [
                            'type' => 'Polygon',
                            'coordinates' => [$polygon]
                        ]
                    ]
                ]
            ]);

            $result = self::where($condition)->select()->toArray();

            return $result ?: [];
        } catch (\Exception $e) {
            Log::error('根据多边形区域查询位置失败', [
                'polygon' => $polygon,
                'message' => $e->getMessage()
            ]);
            return [];
        }
    }
}
```

### 地理空间查询示例

```php
// 保存位置数据
$locationData = [
    'name' => '北京国贸',
    'type' => 'landmark',
    'longitude' => 116.467979,
    'latitude' => 39.914492,
    'tags' => ['商业', '购物', '餐饮']
];

$location = Location::createLocation($locationData);

// 获取附近 1000 米内的位置
$nearbyLocations = Location::findNearby(
    116.467979, // 经度
    39.914492,  // 纬度
    1000,       // 1000米内
    ['type' => 'restaurant'], // 过滤条件 - 只查询餐厅
    20          // 最多返回20条
);

// 按区域查询
$polygonArea = [
    [116.46, 39.91],
    [116.47, 39.91],
    [116.47, 39.92],
    [116.46, 39.92]
];

$locationsInArea = Location::findInPolygon($polygonArea, ['type' => 'landmark']);
```

## 聚合框架 - 数据分析

MongoDB 强大的聚合框架适合复杂数据分析场景，如用户行为分析、实时统计等。

### 核心设计

```php
class Analytics extends Model
{
    // MongoDB连接
    protected $connection = 'mongo';

    /**
     * 按时间段统计用户行为
     */
    public static function aggregateByTime(
        string $actionType,
        string $startTime,
        string $endTime,
        string $timeUnit = 'day'
    ): array
    {
        try {
            // 时间单位对应的格式化
            $dateFormat = match ($timeUnit) {
                'hour' => '%Y-%m-%d %H:00:00',
                'day' => '%Y-%m-%d',
                'week' => '%Y-%U',
                'month' => '%Y-%m',
                default => '%Y-%m-%d'
            };

            // 构建聚合管道
            $pipeline = [
                [
                    '$match' => [
                        'action_type' => $actionType,
                        'create_time' => [
                            '$gte' => strtotime($startTime),
                            '$lte' => strtotime($endTime)
                        ]
                    ]
                ],
                [
                    '$group' => [
                        '_id' => [
                            'time_unit' => [
                                '$dateToString' => [
                                    'format' => $dateFormat,
                                    'date' => ['$toDate' => ['$multiply' => ['$create_time', 1000]]]
                                ]
                            ]
                        ],
                        'count' => ['$sum' => 1]
                    ]
                ],
                [
                    '$sort' => ['_id.time_unit' => 1]
                ]
            ];

            // 执行聚合查询
            return self::mongoAggregate($pipeline);
        } catch (\Exception $e) {
            Log::error('按时间段统计用户行为失败', [
                'action_type' => $actionType,
                'time_range' => [$startTime, $endTime],
                'message' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * 统计不同行为类型的占比
     */
    public static function aggregateActionTypes(string $startTime, string $endTime): array
    {
        try {
            // 构建聚合管道
            $pipeline = [
                [
                    '$match' => [
                        'create_time' => [
                            '$gte' => strtotime($startTime),
                            '$lte' => strtotime($endTime)
                        ]
                    ]
                ],
                [
                    '$group' => [
                        '_id' => '$action_type',
                        'count' => ['$sum' => 1]
                    ]
                ],
                [
                    '$sort' => ['count' => -1]
                ]
            ];

            // 执行聚合查询
            $result = self::mongoAggregate($pipeline);

            // 计算总数
            $total = array_sum(array_column($result, 'count'));

            // 计算占比
            if ($total > 0) {
                foreach ($result as &$item) {
                    $item['percentage'] = round($item['count'] / $total * 100, 2);
                }
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('统计不同行为类型的占比失败', [
                'time_range' => [$startTime, $endTime],
                'message' => $e->getMessage()
            ]);
            return [];
        }
    }
}
```

### 使用示例

```php
// 记录用户行为
$userAction = [
    'user_id' => 1001,
    'action_type' => 'page_view',
    'page' => 'product_detail',
    'product_id' => 'p123456',
    'create_time' => time(),
    'session_id' => 'sess_12345',
    'device' => 'mobile',
    'ip' => '192.168.1.1'
];

Analytics::create($userAction);

// 按时间聚合统计访问量
$dailyViews = Analytics::aggregateByTime(
    'page_view',
    '2023-10-01 00:00:00',
    '2023-10-31 23:59:59',
    'day'
);

// 统计行为类型占比
$actionDistribution = Analytics::aggregateActionTypes(
    '2023-10-01 00:00:00',
    '2023-10-31 23:59:59'
);
```

## 聚合查询常见问题与解决方案

### 1. ThinkPHP MongoDB驱动聚合方法错误

**错误信息**: `think\db\Mongo::aggregate(): Argument #1 ($aggregate) must be of type string, array given`

**原因**: ThinkPHP的MongoDB驱动中，`aggregate`方法的第一个参数应该是字符串（集合名称），而不是数组（管道）。

**错误用法**:
```php
// ❌ 错误：直接使用ThinkPHP的aggregate方法
$results = Db::connect('mongo')
    ->name('orders')
    ->aggregate($pipeline); // 这里会报错
```

**正确用法**:
```php
// ✅ 正确：使用MongoDB原生客户端
protected function mongoAggregate(array $pipeline): array
{
    try {
        // 使用 MongoDB 原生客户端进行聚合
        $mongoConfig = config('mongodb.default');
        $client      = new \MongoDB\Client($mongoConfig['uri'], $mongoConfig['options'], $mongoConfig['driverOptions']);
        $database    = $client->selectDatabase($mongoConfig['database']);
        $collection  = $database->selectCollection($this->ordersCollection);

        // 获取聚合配置选项
        $aggregationConfig = config('mongodb.aggregation.pipeline_options', []);

        $result = $collection->aggregate($pipeline, $aggregationConfig)->toArray();

        return $result;
    } catch (\Exception $e) {
        Log::error('MongoDB聚合查询失败: {message}, 管道: {pipeline}', [
            'pipeline' => $pipeline,
            'message' => $e->getMessage()
        ]);
        return [];
    }
}
```

### 2. 聚合管道性能优化

**建议**:
- 在管道早期使用`$match`过滤数据
- 合理使用索引支持聚合操作
- 对于大数据集，启用`allowDiskUse`选项
- 设置合适的`maxTimeMS`避免长时间运行

```php
// 聚合配置优化
'aggregation' => [
    'pipeline_options' => [
        'allowDiskUse' => true,     // 允许使用磁盘
        'batchSize' => 1000,        // 批次大小
        'maxTimeMS' => 300000,      // 最大执行时间
    ],
],
```

## 副本集与分片 - 高可用性

MongoDB 的副本集与分片特性实现高可用性和水平扩展，适合全球化分布式应用。

### 配置示例

```php
// 数据库配置 (config/database.php)
'connections' => [
    // MongoDB副本集连接配置
    'mongo' => [
        'type'          => 'mongodb',
        'dsn'           => env('mongo.dsn', 'mongodb://username:password@rs1.example.com:27017,rs2.example.com:27017,rs3.example.com:27017/admin'),
        'database'      => env('mongo.database', 'mongo_db'),
        'replica_set'   => env('mongo.replica_set', 'rs0'),
        'read_preference' => 'secondaryPreferred',
        'options'       => [
            'w'                => 'majority',  // 确保数据写入到大多数节点
            'readConcern'      => 'majority', // 确保从大多数节点读取最新数据
        ],
    ],

    // MongoDB分片集群连接配置
    'mongo_sharded' => [
        'type'          => 'mongodb',
        'dsn'           => env('mongo_sharded.dsn', 'mongodb://mongos1.example.com:27017,mongos2.example.com:27017/admin'),
        'database'      => env('mongo_sharded.database', 'sharded_db'),
        'options'       => [
            'maxPoolSize'      => 100,
            'w'                => 'majority',
            'readConcern'      => 'majority',
        ],
    ],
]
```

### 全球化数据模型示例

```php
class GlobalData extends Model
{
    // 设置MongoDB连接（使用分片集群配置）
    protected $connection = 'mongo_sharded';

    /**
     * 保存全球数据
     */
    public static function saveGlobalData(array $data): ?GlobalData
    {
        try {
            // 添加区域信息用于分片
            if (!isset($data['region'])) {
                throw new \Exception('区域信息不能为空，用于数据分片');
            }

            // 记录日志
            Log::info('保存全球数据', ['region' => $data['region']]);

            // 创建数据
            return self::create($data);
        } catch (\Exception $e) {
            Log::error('保存全球数据失败', ['data' => $data, 'message' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * 多区域数据对比
     */
    public static function compareRegions(array $regions, string $metric): array
    {
        try {
            // 结果集
            $result = [];

            // 遍历区域
            foreach ($regions as $region) {
                // 构建聚合管道
                $pipeline = [
                    [
                        '$match' => ['region' => $region]
                    ],
                    [
                        '$group' => [
                            '_id' => '$region',
                            'total' => ['$sum' => '$' . $metric],
                            'avg' => ['$avg' => '$' . $metric],
                            'max' => ['$max' => '$' . $metric],
                            'min' => ['$min' => '$' . $metric],
                            'count' => ['$sum' => 1]
                        ]
                    ]
                ];

                // 执行聚合查询
                $regionResult = self::mongoAggregate($pipeline);

                // 提取结果
                if (!empty($regionResult)) {
                    $result[] = [
                        'region' => $region,
                        'total' => $regionResult[0]['total'] ?? 0,
                        'avg' => $regionResult[0]['avg'] ?? 0,
                        'max' => $regionResult[0]['max'] ?? 0,
                        'min' => $regionResult[0]['min'] ?? 0,
                        'count' => $regionResult[0]['count'] ?? 0
                    ];
                } else {
                    $result[] = [
                        'region' => $region,
                        'total' => 0,
                        'avg' => 0,
                        'max' => 0,
                        'min' => 0,
                        'count' => 0
                    ];
                }
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('多区域数据对比失败', [
                'regions' => $regions,
                'metric' => $metric,
                'message' => $e->getMessage()
            ]);
            return [];
        }
    }
}
```

### 使用示例

```php
// 保存各区域数据
$europeData = [
    'region' => 'eu',
    'content' => '欧洲区域数据',
    'user_count' => 1000,
    'transaction_volume' => 25000
];

$asiaData = [
    'region' => 'asia',
    'content' => '亚洲区域数据',
    'user_count' => 5000,
    'transaction_volume' => 120000
];

// 保存到不同区域分片
GlobalData::saveGlobalData($europeData);
GlobalData::saveGlobalData($asiaData);

// 多区域对比
$regionComparison = GlobalData::compareRegions(
    ['eu', 'asia', 'na'], // 区域列表
    'transaction_volume'  // 对比指标
);
```

## 配置说明

在使用MongoDB特性前，需要正确配置数据库连接。在 `config/database.php` 中添加：

```php
'connections' => [
    // MongoDB连接配置
    'mongo' => [
        // 数据库类型
        'type'          => 'mongodb',
        // 连接dsn
        'dsn'           => env('mongo.dsn', 'mongodb://username:password@localhost:27017/admin'),
        // 数据库名
        'database'      => env('mongo.database', 'mongodb'),
        // 用户名
        'username'      => env('mongo.username', ''),
        // 密码
        'password'      => env('mongo.password', ''),
        // 是否开启查询缓存
        'query_cache_enable'=> true,
        // 慢查询日志
        'slow_query_log' => true,
        // 连接参数
        'options'       => [
            // 连接超时时间（毫秒）
            'connectTimeoutMS' => 5000,
            // Socket超时时间（毫秒）
            'socketTimeoutMS'  => 60000,
            // 连接池数量
            'maxPoolSize'      => 50,
        ],
    ],
]
```

同时需要安装 MongoDB PHP 扩展和 ThinkPHP 的 MongoDB 驱动：

```bash
# 安装PHP MongoDB扩展
pecl install mongodb

# 安装ThinkPHP的MongoDB驱动
composer require topthink/think-mongo
```

## 索引优化

为了提升 MongoDB 查询性能，应该针对常用的查询字段创建适当的索引：

```php
// 创建地理空间索引
db.locations.createIndex({ location: "2dsphere" });

// 常规字段索引
db.products.createIndex({ category: 1, price: -1 });

// 复合索引
db.iot_data.createIndex({ device_id: 1, create_time: -1 });

// 文本索引
db.products.createIndex({ name: "text", description: "text" });
```

## 最佳实践

1. **合理利用文档模型**：将关联性强的数据嵌入同一文档，减少关联查询
2. **避免过大文档**：单个文档不要超过 16MB
3. **合理设计索引**：根据查询模式创建索引，避免过多索引
4. **使用投影**：只返回需要的字段，减少网络传输
5. **批量操作**：使用批量插入和更新，提高性能
6. **注意内存使用**：聚合操作可能消耗大量内存，设置合理的限制
7. **使用缓存**：对频繁查询的数据进行缓存