# MongoDB 使用示例

本文档提供了 ThinkPHP 8 框架下使用 MongoDB 的实际代码示例，对应 `mongodb-guide.md` 中介绍的五大核心特性。

## 动态模式 - 产品目录示例

### 控制器示例

```php
<?php
namespace app\controller;

use app\BaseController;
use app\service\ProductService;
use think\Response;

class ProductController extends BaseController
{
    protected $productService;
    
    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }
    
    /**
     * 创建产品
     */
    public function create(): Response
    {
        try {
            // 获取POST数据
            $data = $this->request->post();
            
            // 创建产品
            $product = $this->productService->create($data);
            
            return json(['code' => 200, 'message' => '创建成功', 'data' => $product]);
        } catch (\Exception $e) {
            return json(['code' => 500, 'message' => '服务器错误：' . $e->getMessage()]);
        }
    }
    
    /**
     * 查询产品
     */
    public function search(): Response
    {
        try {
            // 获取查询参数
            $params = $this->request->get();
            
            // 查询产品
            $products = $this->productService->search($params);
            
            return json(['code' => 200, 'message' => '查询成功', 'data' => $products]);
        } catch (\Exception $e) {
            return json(['code' => 500, 'message' => '服务器错误：' . $e->getMessage()]);
        }
    }
}
```

### 服务层示例

```php
<?php
namespace app\service;

use app\model\Product;
use think\facade\Log;
use think\Exception;

class ProductService
{
    /**
     * 创建产品
     */
    public function create(array $data): array
    {
        try {
            // 验证必要字段
            if (empty($data['name']) || empty($data['category'])) {
                throw new Exception('产品名称和分类不能为空');
            }
            
            // 记录日志
            Log::info('创建产品', ['data' => $data]);
            
            // 创建产品
            $product = Product::addProduct($data);
            
            return $product->toArray();
        } catch (\Exception $e) {
            Log::error('创建产品失败', ['data' => $data, 'message' => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * 根据动态条件查询产品
     */
    public function search(array $params = []): array
    {
        try {
            // 构建查询条件
            $condition = [];
            
            // 动态添加查询条件 - 支持任意字段查询
            foreach ($params as $key => $value) {
                if (!empty($value)) {
                    $condition[$key] = $value;
                }
            }
            
            // 记录日志
            Log::info('查询产品', ['condition' => $condition]);
            
            // 查询产品
            return Product::findProducts($condition);
        } catch (\Exception $e) {
            Log::error('查询产品失败', ['params' => $params, 'message' => $e->getMessage()]);
            return [];
        }
    }
}
```

## 高扩展性 - 物联网数据示例

### 接口调用示例

```php
// 批量设备数据接口
public function batchReceiveData(): Response
{
    try {
        // 获取POST数据
        $dataList = $this->request->post();
        
        // 检查数据格式
        if (!is_array($dataList) || !isset($dataList[0])) {
            return json(['code' => 400, 'message' => '数据格式错误，应为数组']);
        }
        
        // 批量保存设备数据
        $result = $this->iotService->batchSaveData($dataList);
        
        return json(['code' => 200, 'message' => $result ? '数据保存成功' : '数据保存失败']);
    } catch (\Exception $e) {
        Log::error('批量接收设备数据异常', ['message' => $e->getMessage()]);
        return json(['code' => 500, 'message' => '服务器错误：' . $e->getMessage()]);
    }
}

// 获取设备历史数据接口
public function getHistoryData(string $deviceId): Response
{
    try {
        // 获取请求参数
        $startTime = $this->request->param('start_time', date('Y-m-d H:i:s', strtotime('-1 day')));
        $endTime = $this->request->param('end_time', date('Y-m-d H:i:s'));
        $page = intval($this->request->param('page', 1));
        $limit = intval($this->request->param('limit', 20));
        
        // 参数验证
        if (empty($deviceId)) {
            return json(['code' => 400, 'message' => '设备ID不能为空']);
        }
        
        // 获取历史数据
        $data = $this->iotService->getHistoryData($deviceId, $startTime, $endTime, $page, $limit);
        
        return json(['code' => 200, 'message' => '查询成功', 'data' => $data]);
    } catch (\Exception $e) {
        Log::error('获取设备历史数据异常', ['device_id' => $deviceId, 'message' => $e->getMessage()]);
        return json(['code' => 500, 'message' => '服务器错误：' . $e->getMessage()]);
    }
}
```

### 服务层示例

```php
/**
 * 批量保存设备数据
 */
public function batchSaveData(array $dataList): bool
{
    try {
        // 记录日志
        Log::info('批量保存IoT设备数据', ['count' => count($dataList)]);
        
        // 批量保存数据
        return IoTData::batchSave($dataList);
    } catch (\Exception $e) {
        Log::error('批量保存IoT设备数据失败', ['message' => $e->getMessage()]);
        return false;
    }
}

/**
 * 获取设备历史数据
 */
public function getHistoryData(
    string $deviceId, 
    string $startTime, 
    string $endTime, 
    int $page = 1, 
    int $limit = 20
): array
{
    try {
        // 缓存键
        $cacheKey = "iot:history:{$deviceId}:{$startTime}:{$endTime}:{$page}:{$limit}";
        
        // 优先从缓存获取
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }
        
        // 记录日志
        Log::info('查询设备历史数据', [
            'device_id' => $deviceId,
            'time_range' => [$startTime, $endTime],
            'page' => $page,
            'limit' => $limit
        ]);
        
        // 查询数据
        $data = IoTData::getDataByTimeRange(
            $deviceId,
            $startTime,
            $endTime,
            $page,
            $limit
        );
        
        // 缓存结果，5分钟过期
        Cache::set($cacheKey, $data, 300);
        
        return $data;
    } catch (\Exception $e) {
        Log::error('查询设备历史数据失败', [
            'device_id' => $deviceId,
            'message' => $e->getMessage()
        ]);
        return [];
    }
}
```

## 地理空间支持 - LBS应用示例

### 附近地点查询接口

```php
/**
 * 查询附近的位置
 */
public function nearby(): Response
{
    try {
        // 获取请求参数
        $longitude = floatval($this->request->param('longitude', 0));
        $latitude = floatval($this->request->param('latitude', 0));
        $distance = intval($this->request->param('distance', 1000));
        $type = $this->request->param('type', '');
        $limit = intval($this->request->param('limit', 20));
        
        // 参数验证
        if ($longitude === 0 || $latitude === 0) {
            return json(['code' => 400, 'message' => '经纬度不能为空']);
        }
        
        // 构建过滤条件
        $filter = [];
        if (!empty($type)) {
            $filter['type'] = $type;
        }
        
        // 查询附近位置
        $locations = $this->locationService->findNearby($longitude, $latitude, $distance, $filter, $limit);
        
        return json(['code' => 200, 'message' => '查询成功', 'data' => $locations]);
    } catch (\Exception $e) {
        Log::error('查询附近位置接口异常', ['message' => $e->getMessage()]);
        return json(['code' => 500, 'message' => '服务器错误：' . $e->getMessage()]);
    }
}
```

### 区域查询接口

```php
/**
 * 根据区域查询位置
 */
public function area(): Response
{
    try {
        // 获取请求参数
        $polygon = $this->request->param('polygon', '');
        $type = $this->request->param('type', '');
        
        // 参数验证
        if (empty($polygon)) {
            return json(['code' => 400, 'message' => '区域多边形不能为空']);
        }
        
        // 解析多边形坐标
        $polygonPoints = [];
        $points = explode(';', $polygon);
        foreach ($points as $point) {
            $coordinates = explode(',', $point);
            if (count($coordinates) === 2) {
                $polygonPoints[] = [floatval($coordinates[0]), floatval($coordinates[1])];
            }
        }
        
        // 构建过滤条件
        $filter = [];
        if (!empty($type)) {
            $filter['type'] = $type;
        }
        
        // 查询区域内位置
        $locations = $this->locationService->findInPolygon($polygonPoints, $filter);
        
        return json(['code' => 200, 'message' => '查询成功', 'data' => $locations]);
    } catch (\Exception $e) {
        Log::error('根据区域查询位置接口异常', ['message' => $e->getMessage()]);
        return json(['code' => 500, 'message' => '服务器错误：' . $e->getMessage()]);
    }
}
```

### 服务层示例

```php
/**
 * 查询附近的位置
 */
public function findNearby(
    float $longitude, 
    float $latitude, 
    int $distance = 1000, 
    array $filter = [], 
    int $limit = 20
): array
{
    try {
        // 缓存键
        $cacheKey = "location:nearby:{$longitude}:{$latitude}:{$distance}:" . md5(json_encode($filter)) . ":{$limit}";
        
        // 优先从缓存获取
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }
        
        // 记录日志
        Log::info('查询附近位置', [
            'coordinates' => [$longitude, $latitude],
            'distance' => $distance,
            'filter' => $filter,
            'limit' => $limit
        ]);
        
        // 查询附近位置
        $result = Location::findNearby($longitude, $latitude, $distance, $filter, $limit);
        
        // 缓存结果，5分钟过期
        Cache::set($cacheKey, $result, 300);
        
        return $result;
    } catch (\Exception $e) {
        Log::error('查询附近位置失败', [
            'coordinates' => [$longitude, $latitude],
            'message' => $e->getMessage()
        ]);
        return [];
    }
}
```

## 聚合框架 - 数据分析示例

### 时间段统计接口

```php
/**
 * 按时间段统计用户行为
 */
public function timeStats(): Response
{
    try {
        // 获取请求参数
        $actionType = $this->request->param('action_type', '');
        $startTime = $this->request->param('start_time', '');
        $endTime = $this->request->param('end_time', '');
        $timeUnit = $this->request->param('time_unit', 'day');
        
        // 参数验证
        if (empty($actionType)) {
            return json(['code' => 400, 'message' => '行为类型不能为空']);
        }
        
        // 查询统计数据
        $data = $this->analyticsService->getActionStatsByTime(
            $actionType,
            $startTime,
            $endTime,
            $timeUnit
        );
        
        return json(['code' => 200, 'message' => '查询成功', 'data' => $data]);
    } catch (\Exception $e) {
        Log::error('按时间段统计用户行为接口异常', ['message' => $e->getMessage()]);
        return json(['code' => 500, 'message' => '服务器错误：' . $e->getMessage()]);
    }
}
```

### 行为类型占比接口

```php
/**
 * 获取行为类型占比
 */
public function typeDistribution(): Response
{
    try {
        // 获取请求参数
        $startTime = $this->request->param('start_time', '');
        $endTime = $this->request->param('end_time', '');
        
        // 查询行为类型占比
        $data = $this->analyticsService->getActionTypeDistribution(
            $startTime,
            $endTime
        );
        
        return json(['code' => 200, 'message' => '查询成功', 'data' => $data]);
    } catch (\Exception $e) {
        Log::error('获取行为类型占比接口异常', ['message' => $e->getMessage()]);
        return json(['code' => 500, 'message' => '服务器错误：' . $e->getMessage()]);
    }
}
```

### 服务层示例

```php
/**
 * 按时间段统计用户行为
 */
public function getActionStatsByTime(
    string $actionType, 
    string $startTime = '', 
    string $endTime = '', 
    string $timeUnit = 'day'
): array
{
    try {
        // 设置默认时间范围
        if (empty($startTime)) {
            $startTime = date('Y-m-d H:i:s', strtotime('-7 days'));
        }
        
        if (empty($endTime)) {
            $endTime = date('Y-m-d H:i:s');
        }
        
        // 缓存键
        $cacheKey = "analytics:time:{$actionType}:{$startTime}:{$endTime}:{$timeUnit}";
        
        // 优先从缓存获取
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }
        
        // 记录日志
        Log::info('按时间段统计用户行为', [
            'action_type' => $actionType,
            'time_range' => [$startTime, $endTime],
            'time_unit' => $timeUnit
        ]);
        
        // 查询统计数据
        $result = Analytics::aggregateByTime($actionType, $startTime, $endTime, $timeUnit);
        
        // 格式化结果
        $formattedResult = [];
        foreach ($result as $item) {
            $formattedResult[] = [
                'time' => $item['_id']['time_unit'],
                'count' => $item['count']
            ];
        }
        
        // 缓存结果，30分钟过期
        Cache::set($cacheKey, $formattedResult, 1800);
        
        return $formattedResult;
    } catch (\Exception $e) {
        Log::error('按时间段统计用户行为失败', [
            'action_type' => $actionType,
            'time_range' => [$startTime, $endTime],
            'message' => $e->getMessage()
        ]);
        return [];
    }
}
```

## 副本集与分片 - 全球化应用示例

### 多区域对比接口

```php
/**
 * 多区域数据对比
 */
public function compare(): Response
{
    try {
        // 获取请求参数
        $regions = $this->request->param('regions');
        $metric = $this->request->param('metric');
        
        // 验证参数
        if (empty($regions) || empty($metric)) {
            return json(['code' => 400, 'message' => '区域列表和对比指标不能为空']);
        }
        
        // 解析区域列表
        $regionList = explode(',', $regions);
        
        // 执行对比
        $result = $this->globalDataService->compareRegions($regionList, $metric);
        
        return json(['code' => 200, 'message' => '对比成功', 'data' => $result]);
    } catch (\Exception $e) {
        Log::error('多区域数据对比接口异常', ['message' => $e->getMessage()]);
        return json(['code' => 500, 'message' => '服务器错误：' . $e->getMessage()]);
    }
}
```

### 热门区域接口

```php
/**
 * 获取热门区域
 */
public function hotRegions(): Response
{
    try {
        // 获取请求参数
        $limit = intval($this->request->param('limit', 10));
        
        // 获取热门区域
        $hotRegions = $this->globalDataService->getHotRegions($limit);
        
        return json(['code' => 200, 'message' => '查询成功', 'data' => $hotRegions]);
    } catch (\Exception $e) {
        Log::error('获取热门区域接口异常', ['message' => $e->getMessage()]);
        return json(['code' => 500, 'message' => '服务器错误：' . $e->getMessage()]);
    }
}
```

### 服务层示例

```php
/**
 * 多区域数据对比
 */
public function compareRegions(array $regions, string $metric): array
{
    try {
        // 验证必要参数
        if (empty($regions) || empty($metric)) {
            throw new \Exception('区域列表和对比指标不能为空');
        }
        
        // 缓存键
        $cacheKey = "global:compare:" . md5(json_encode($regions)) . ":{$metric}";
        
        // 优先从缓存获取
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }
        
        // 记录日志
        Log::info('多区域数据对比', [
            'regions' => $regions,
            'metric' => $metric
        ]);
        
        // 执行对比
        $result = GlobalData::compareRegions($regions, $metric);
        
        // 缓存结果，15分钟过期
        Cache::set($cacheKey, $result, 900);
        
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

/**
 * 获取热门区域
 */
public function getHotRegions(int $limit = 10): array
{
    try {
        // 缓存键
        $cacheKey = "global:hot_regions:{$limit}";
        
        // 优先从缓存获取
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }
        
        // 记录日志
        Log::info('获取热门区域', ['limit' => $limit]);
        
        // 查询统计
        $stats = GlobalData::globalAggregate('region');
        
        // 截取指定数量
        $hotRegions = array_slice($stats, 0, $limit);
        
        // 缓存结果，1小时过期
        Cache::set($cacheKey, $hotRegions, 3600);
        
        return $hotRegions;
    } catch (\Exception $e) {
        Log::error('获取热门区域失败', ['message' => $e->getMessage()]);
        return [];
    }
}
```

## 接口测试示例

以下是一些使用Postman或curl测试这些接口的示例：

### 产品目录API测试

```
# 创建动态产品
POST /api/product/create
Content-Type: application/json

{
  "name": "智能手表",
  "category": "可穿戴设备",
  "price": 1299,
  "specifications": {
    "screen": "1.78英寸AMOLED",
    "battery": "14天",
    "waterproof": "5ATM"
  },
  "colors": ["黑色", "银色", "金色"],
  "bluetooth_version": "5.2",
  "has_nfc": true
}

# 动态查询
GET /api/product/search?category=可穿戴设备&has_nfc=true
```

### 地理空间API测试

```
# 查询附近餐厅
GET /api/location/nearby?longitude=116.407526&latitude=39.904030&distance=500&type=restaurant

# 区域查询
GET /api/location/area?polygon=116.400,39.900;116.410,39.900;116.410,39.910;116.400,39.910&type=shopping
```

### 数据分析API测试

```
# 时间段统计
GET /api/analytics/timeStats?action_type=page_view&start_time=2023-10-01 00:00:00&end_time=2023-10-31 23:59:59&time_unit=day

# 行为类型占比
GET /api/analytics/typeDistribution?start_time=2023-10-01 00:00:00&end_time=2023-10-31 23:59:59
```

### 全球化应用API测试

```
# 多区域对比
GET /api/global/compare?regions=eu,asia,na&metric=transaction_volume

# 热门区域
GET /api/global/hotRegions?limit=5
``` 