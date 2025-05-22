# MongoDB 索引和性能优化

本文档提供了在 ThinkPHP 8 中使用 MongoDB 时的索引设计和性能优化建议。

## 索引类型

MongoDB 支持多种索引类型，在 ThinkPHP 8 项目中可以根据不同场景选择合适的索引：

### 1. 单字段索引

适用于常见的查询条件，如商品分类、用户ID等。

```php
// 模型内创建索引的示例方法
public function createBaseIndexes()
{
    $connection = $this->getConnection();
    $collection = $connection->getCollection($this->getTable());
    
    // 创建单字段索引
    $collection->createIndex(['user_id' => 1]); // 升序索引
    $collection->createIndex(['create_time' => -1]); // 降序索引
}
```

### 2. 复合索引

当查询条件涉及多个字段时，复合索引可以提高查询效率。

```php
// 复合索引 - 适用于同时按设备ID和时间查询的场景
$collection->createIndex([
    'device_id' => 1, 
    'create_time' => -1
]);

// 复合索引 - 适用于产品分类和价格范围查询
$collection->createIndex([
    'category' => 1, 
    'price' => 1
]);
```

### 3. 地理空间索引

支持地理位置相关查询，如附近的餐厅、指定区域内的商店等。

```php
// 2dsphere 索引 - 适用于地球表面地理位置查询
$collection->createIndex(['location' => '2dsphere']);

// 为满足 Location 模型的 findNearby 和 findInPolygon 方法的需要创建地理空间索引
public function createGeoIndexes()
{
    $connection = $this->getConnection();
    $collection = $connection->getCollection($this->getTable());
    
    // 创建地理空间索引
    $collection->createIndex(['location' => '2dsphere']);
    
    // 同时包含地理位置和类型的复合索引
    $collection->createIndex([
        'location' => '2dsphere',
        'type' => 1
    ]);
}
```

### 4. 文本索引

适用于全文搜索场景，例如产品描述、文章内容等。

```php
// 文本索引 - 支持全文搜索
$collection->createIndex([
    'name' => 'text', 
    'description' => 'text'
], [
    'weights' => [
        'name' => 10,  // 名称字段权重高
        'description' => 5 // 描述字段权重低
    ],
    'default_language' => 'simplified chinese'
]);

// 使用文本索引进行搜索
$products = Product::where([
    '$text' => [
        '$search' => '智能手机',
        '$language' => 'simplified chinese'
    ]
])->select()->toArray();
```

### 5. 哈希索引

适用于精确匹配的查询场景。

```php
// 哈希索引 - 精确匹配但不支持范围查询
$collection->createIndex(['product_code' => 'hashed']);
```

### 6. TTL 索引

用于自动删除过期文档，适合日志、会话等临时数据。

```php
// TTL索引 - 设置数据过期时间
$collection->createIndex(
    ['create_time' => 1],
    ['expireAfterSeconds' => 604800] // 一周后自动删除
);
```

## 索引管理和最佳实践

在 ThinkPHP 8 框架中管理 MongoDB 索引的建议：

### 1. 在模型中集中管理索引

```php
<?php
namespace app\model;

use think\Model;

class UserAction extends Model
{
    // MongoDB连接
    protected $connection = 'mongo';
    
    // 集合名称
    protected $table = 'user_actions';
    
    /**
     * 创建模型所需的所有索引
     */
    public static function createIndexes()
    {
        $model = new self();
        $connection = $model->getConnection();
        $collection = $connection->getCollection($model->getTable());
        
        // 常用查询索引
        $collection->createIndex(['user_id' => 1, 'create_time' => -1]);
        $collection->createIndex(['action_type' => 1]);
        
        // 支持聚合统计的索引
        $collection->createIndex(['action_type' => 1, 'create_time' => 1]);
        
        // TTL索引 - 30天后自动删除
        $collection->createIndex(
            ['create_time' => 1],
            ['expireAfterSeconds' => 2592000]
        );
    }
}
```

### 2. 通过命令行工具管理索引

创建控制台命令用于管理索引：

```php
<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use app\model\Product;
use app\model\IoTData;
use app\model\Location;
use app\model\Analytics;
use app\model\GlobalData;

class CreateMongoIndexes extends Command
{
    protected function configure()
    {
        $this->setName('mongo:create-indexes')
            ->setDescription('创建MongoDB索引')
            ->addArgument('model', Argument::OPTIONAL, '模型名称');
    }

    protected function execute(Input $input, Output $output)
    {
        $model = $input->getArgument('model');
        
        if ($model) {
            $this->createIndexForModel($model, $output);
        } else {
            $this->createAllIndexes($output);
        }
        
        $output->writeln('索引创建完成');
    }
    
    protected function createIndexForModel($modelName, Output $output)
    {
        $output->writeln("为 {$modelName} 创建索引...");
        
        switch ($modelName) {
            case 'Product':
                Product::createIndexes();
                break;
            case 'IoTData':
                IoTData::createIndexes();
                break;
            case 'Location':
                Location::createIndexes();
                break;
            case 'Analytics':
                Analytics::createIndexes();
                break;
            case 'GlobalData':
                GlobalData::createIndexes();
                break;
            default:
                $output->writeln("未知模型: {$modelName}");
        }
    }
    
    protected function createAllIndexes(Output $output)
    {
        $output->writeln('创建所有模型的索引...');
        
        Product::createIndexes();
        IoTData::createIndexes();
        Location::createIndexes();
        Analytics::createIndexes();
        GlobalData::createIndexes();
    }
}
```

使用方法：

```bash
php think mongo:create-indexes        # 创建所有索引
php think mongo:create-indexes Product # 只创建Product模型的索引
```

## 性能优化建议

### 1. 查询优化

```php
// 优化前：不使用索引的查询
$results = IoTData::where('temperature', '>', 25)
    ->select()
    ->toArray();

// 优化后：利用索引的查询
$results = IoTData::where('device_id', 'device001')  // 使用索引字段
    ->where('create_time', '>=', strtotime('-1 day'))  // 使用索引字段
    ->where('temperature', '>', 25)  // 非索引字段放在后面
    ->select()
    ->toArray();
```

### 2. 投影查询

只返回需要的字段，减少数据传输：

```php
// 优化前：返回所有字段
$products = Product::where('category', '手机')
    ->select()
    ->toArray();

// 优化后：只返回需要的字段
$products = Product::where('category', '手机')
    ->field('name,price,image')  // 只返回这些字段
    ->select()
    ->toArray();
```

### 3. 分页查询

避免大量数据一次性返回：

```php
// 分页查询
$page = 1;
$limit = 20;
$products = Product::where('category', '手机')
    ->page($page, $limit)
    ->select()
    ->toArray();
```

### 4. 聚合管道优化

```php
// 优化前：先获取数据再在PHP中处理
$allData = Analytics::where('create_time', '>=', strtotime('-30 days'))
    ->select()
    ->toArray();
    
$result = [];
foreach ($allData as $item) {
    $date = date('Y-m-d', $item['create_time']);
    if (!isset($result[$date])) {
        $result[$date] = 0;
    }
    $result[$date]++;
}

// 优化后：直接使用MongoDB聚合管道
$pipeline = [
    [
        '$match' => [
            'create_time' => ['$gte' => strtotime('-30 days')]
        ]
    ],
    [
        '$group' => [
            '_id' => [
                'date' => [
                    '$dateToString' => [
                        'format' => '%Y-%m-%d',
                        'date' => ['$toDate' => ['$multiply' => ['$create_time', 1000]]]
                    ]
                ]
            ],
            'count' => ['$sum' => 1]
        ]
    ],
    [
        '$sort' => ['_id.date' => 1]
    ]
];

$model = new Analytics();
$connection = $model->getConnection();
$collection = $connection->getCollection($model->getTable());
$result = $collection->aggregate($pipeline)->toArray();
```

### 5. 批量操作

使用批量插入替代单条插入：

```php
// 优化前：循环单条插入
foreach ($dataList as $data) {
    IoTData::create($data);
}

// 优化后：批量插入
$model = new IoTData();
$connection = $model->getConnection();
$collection = $connection->getCollection($model->getTable());
$collection->insertMany($dataList);
```

### 6. 利用缓存

对于频繁查询但不常变化的数据使用缓存：

```php
/**
 * 获取热门产品
 */
public function getHotProducts(int $limit = 10): array
{
    // 缓存键
    $cacheKey = "product:hot:{$limit}";
    
    // 优先从缓存获取
    if (Cache::has($cacheKey)) {
        return Cache::get($cacheKey);
    }
    
    // 查询热门产品
    $products = Product::where('status', 1)
        ->order('sales', 'desc')
        ->limit($limit)
        ->select()
        ->toArray();
    
    // 缓存结果，1小时过期
    Cache::set($cacheKey, $products, 3600);
    
    return $products;
}
```

## 索引维护

定期检查和优化索引：

```php
/**
 * 分析索引使用情况
 */
public function analyzeIndexes($collection)
{
    $model = new Product();
    $connection = $model->getConnection();
    $collection = $connection->getCollection($model->getTable());
    
    // 获取索引使用统计
    $result = $connection->command([
        'aggregate' => $model->getTable(),
        'pipeline' => [
            ['$indexStats' => new \stdClass()]
        ],
        'cursor' => new \stdClass()
    ]);
    
    return $result->toArray();
}
```

## 分片和集群优化

针对 GlobalData 模型的分片策略：

```php
// 创建分片索引
public static function setupSharding()
{
    $model = new self();
    $connection = $model->getConnection();
    
    // 选择管理数据库
    $adminDB = $connection->selectDatabase('admin');
    
    // 对数据库启用分片
    $adminDB->command(['enableSharding' => $model->getConfig('database')]);
    
    // 对集合进行分片 - 使用 region 字段作为分片键
    $adminDB->command([
        'shardCollection' => $model->getConfig('database') . '.' . $model->getTable(),
        'key' => ['region' => 1]
    ]);
}
```

## 监控和调优

在 ThinkPHP 8 的 MongoDB 应用中监控性能：

```php
// 注册慢查询监听器
\think\facade\Db::listen(function($sql, $time, $master) {
    // 记录执行时间超过100ms的查询
    if ($time > 100) {
        \think\facade\Log::warning('MongoDB慢查询', [
            'query' => $sql,
            'time' => $time,
            'connection' => $master ? 'master' : 'slave'
        ]);
    }
});
```

总结：合理的索引设计是 MongoDB 性能的关键。在 ThinkPHP 8 项目中，应根据实际查询模式设计索引，并定期维护和优化索引以保持最佳性能。 