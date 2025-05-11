<?php
declare(strict_types=1);

namespace app\controller\redis;

use app\controller\RedisDemo;
use app\facade\Redis;
use think\facade\View;

/**
 * Redis Geo类型演示控制器
 * 
 * 演示Redis Geo类型的常见应用场景
 */
class GeoDemo extends RedisDemo
{
    /**
     * 城市坐标数据
     * 
     * @var array
     */
    protected array $cities = [
        'beijing' => ['116.405285', '39.904989', '北京'],
        'shanghai' => ['121.472644', '31.231706', '上海'],
        'guangzhou' => ['113.280637', '23.125178', '广州'],
        'shenzhen' => ['114.085947', '22.547', '深圳'],
        'hangzhou' => ['120.155070', '30.274084', '杭州'],
        'chengdu' => ['104.065735', '30.659462', '成都'],
        'wuhan' => ['114.298572', '30.584355', '武汉'],
        'xian' => ['108.948024', '34.263161', '西安'],
        'nanjing' => ['118.767413', '32.041544', '南京'],
        'suzhou' => ['120.585315', '31.298886', '苏州'],
    ];
    
    /**
     * 演示页面
     */
    public function index()
    {
        return View::fetch('redis/geo/index');
    }
    
    /**
     * 基本用法示例
     */
    public function basic()
    {
        try {
            $redis = Redis::geo();
            $key = 'geo_demo_cities';
            
            // 清空之前的测试数据
            $redis->delete($key);
            
            // 添加城市地理位置
            $addResults = [];
            foreach ($this->cities as $city => $location) {
                $result = $redis->geoAdd($key, (float)$location[0], (float)$location[1], $city);
                $addResults[$city] = $result;
            }
            
            // 获取北京的坐标
            $beijingPos = $redis->geoPos($key, 'beijing');
            
            // 获取北京到上海的距离（单位：公里）
            $distanceBeijingToShanghai = $redis->geoDist($key, 'beijing', 'shanghai', 'km');
            
            // 获取杭州到上海的距离（单位：公里）
            $distanceHangzhouToShanghai = $redis->geoDist($key, 'hangzhou', 'shanghai', 'km');
            
            // 获取上海半径300公里内的城市
            $nearShanghai = $redis->geoRadius($key, 121.472644, 31.231706, 300, 'km', [
                'WITHDIST' => true, // 返回距离
                'WITHCOORD' => true, // 返回坐标
                'ASC' => true, // 按距离正序排列
            ]);
            
            // 获取北京的GeoHash值
            $beijingHash = $redis->geoHash($key, 'beijing');
            
            // 批量获取GeoHash值
            $cityHashes = $redis->geoBatchHash($key, ['beijing', 'shanghai', 'guangzhou']);
            
            return $this->success('Geo基本用法演示成功', [
                'add_results' => $addResults,
                'beijing_position' => $beijingPos,
                'distance_beijing_to_shanghai' => $distanceBeijingToShanghai,
                'distance_hangzhou_to_shanghai' => $distanceHangzhouToShanghai,
                'cities_near_shanghai' => $nearShanghai,
                'beijing_geohash' => $beijingHash,
                'city_geohashes' => $cityHashes,
            ]);
        } catch (\Throwable $e) {
            return $this->error('Geo基本用法演示失败：' . $e->getMessage());
        }
    }
    
    /**
     * 附近的人示例
     */
    public function nearbyUsers()
    {
        try {
            $redis = Redis::geo();
            $action = $this->request->param('action', 'list');
            
            $key = 'geo_demo_users';
            
            switch ($action) {
                case 'add':
                    // 添加用户位置
                    $userId = $this->request->param('user_id', 0, 'intval');
                    $longitude = $this->request->param('longitude', 0, 'floatval');
                    $latitude = $this->request->param('latitude', 0, 'floatval');
                    
                    if ($userId > 0 && $longitude && $latitude) {
                        $redis->geoAdd($key, $longitude, $latitude, "user:{$userId}");
                        
                        $result = [
                            'status' => 'success',
                            'message' => "用户 {$userId} 位置已更新",
                            'user_id' => $userId,
                            'longitude' => $longitude,
                            'latitude' => $latitude,
                        ];
                    } else {
                        $result = [
                            'status' => 'error',
                            'message' => '用户ID和位置信息不能为空',
                        ];
                    }
                    break;
                    
                case 'nearby':
                    // 查找附近的人
                    $userId = $this->request->param('user_id', 0, 'intval');
                    $radius = $this->request->param('radius', 5, 'floatval'); // 默认5公里
                    $unit = $this->request->param('unit', 'km');
                    $count = $this->request->param('count', 10, 'intval');
                    
                    // 如果指定了用户ID，则查找该用户附近的人
                    if ($userId > 0) {
                        $userKey = "user:{$userId}";
                        $options = [
                            'WITHDIST' => true, // 返回距离
                            'COUNT' => $count, // 限制返回数量
                            'ASC' => true, // 按距离正序排列
                        ];
                        
                        $nearby = $redis->geoRadiusByMember($key, $userKey, $radius, $unit, $options);
                        
                        // 格式化结果
                        $formattedNearby = [];
                        foreach ($nearby as $item) {
                            $member = $item[0];
                            $distance = $item[1];
                            
                            // 排除自己
                            if ($member !== $userKey) {
                                $nearUserId = str_replace('user:', '', $member);
                                $formattedNearby[] = [
                                    'user_id' => $nearUserId,
                                    'distance' => $distance,
                                    'unit' => $unit,
                                ];
                            }
                        }
                        
                        $result = [
                            'status' => 'success',
                            'user_id' => $userId,
                            'radius' => $radius,
                            'unit' => $unit,
                            'nearby_users' => $formattedNearby,
                            'count' => count($formattedNearby),
                        ];
                    } else {
                        // 如果没有指定用户ID，则使用经纬度查找
                        $longitude = $this->request->param('longitude', 0, 'floatval');
                        $latitude = $this->request->param('latitude', 0, 'floatval');
                        
                        if ($longitude && $latitude) {
                            $options = [
                                'WITHDIST' => true, // 返回距离
                                'WITHCOORD' => true, // 返回坐标
                                'COUNT' => $count, // 限制返回数量
                                'ASC' => true, // 按距离正序排列
                            ];
                            
                            $nearby = $redis->geoRadius($key, $longitude, $latitude, $radius, $unit, $options);
                            
                            // 格式化结果
                            $formattedNearby = [];
                            foreach ($nearby as $item) {
                                $member = $item[0];
                                $distance = $item[1];
                                $coord = $item[2];
                                
                                $nearUserId = str_replace('user:', '', $member);
                                $formattedNearby[] = [
                                    'user_id' => $nearUserId,
                                    'distance' => $distance,
                                    'unit' => $unit,
                                    'longitude' => $coord[0],
                                    'latitude' => $coord[1],
                                ];
                            }
                            
                            $result = [
                                'status' => 'success',
                                'longitude' => $longitude,
                                'latitude' => $latitude,
                                'radius' => $radius,
                                'unit' => $unit,
                                'nearby_users' => $formattedNearby,
                                'count' => count($formattedNearby),
                            ];
                        } else {
                            $result = [
                                'status' => 'error',
                                'message' => '必须指定用户ID或经纬度',
                            ];
                        }
                    }
                    break;
                    
                case 'list':
                default:
                    // 列出所有用户位置
                    $count = $redis->zCard($key); // GEO实际上是使用有序集合实现的
                    
                    // 获取所有用户
                    $users = [];
                    if ($count > 0) {
                        $members = $redis->zRange($key, 0, -1);
                        
                        foreach ($members as $member) {
                            $userId = str_replace('user:', '', $member);
                            $position = $redis->geoPos($key, $member);
                            
                            if ($position) {
                                $users[] = [
                                    'user_id' => $userId,
                                    'longitude' => $position[0],
                                    'latitude' => $position[1],
                                ];
                            }
                        }
                    }
                    
                    $result = [
                        'status' => 'success',
                        'total_users' => $count,
                        'users' => $users,
                    ];
                    break;
            }
            
            return $this->success('附近的人操作成功', $result);
        } catch (\Throwable $e) {
            return $this->error('附近的人操作失败：' . $e->getMessage());
        }
    }
    
    /**
     * 店铺查找示例
     */
    public function storeLocator()
    {
        try {
            $redis = Redis::geo();
            $action = $this->request->param('action', 'list');
            
            $key = 'geo_demo_stores';
            
            switch ($action) {
                case 'add':
                    // 添加店铺
                    $storeId = $this->request->param('store_id', 0, 'intval');
                    $storeName = $this->request->param('store_name', '');
                    $longitude = $this->request->param('longitude', 0, 'floatval');
                    $latitude = $this->request->param('latitude', 0, 'floatval');
                    
                    if ($storeId > 0 && !empty($storeName) && $longitude && $latitude) {
                        // 将店铺信息存储到Hash中
                        $redis->hash()->hMSet("store:{$storeId}", [
                            'id' => $storeId,
                            'name' => $storeName,
                            'longitude' => $longitude,
                            'latitude' => $latitude,
                            'create_time' => time(),
                        ]);
                        
                        // 添加到地理位置索引
                        $redis->geoAdd($key, $longitude, $latitude, "store:{$storeId}");
                        
                        $result = [
                            'status' => 'success',
                            'message' => "店铺 {$storeName} 已添加",
                            'store_id' => $storeId,
                            'store_name' => $storeName,
                            'longitude' => $longitude,
                            'latitude' => $latitude,
                        ];
                    } else {
                        $result = [
                            'status' => 'error',
                            'message' => '店铺信息不完整',
                        ];
                    }
                    break;
                    
                case 'search':
                    // 查找附近的店铺
                    $longitude = $this->request->param('longitude', 0, 'floatval');
                    $latitude = $this->request->param('latitude', 0, 'floatval');
                    $radius = $this->request->param('radius', 5, 'floatval'); // 默认5公里
                    $unit = $this->request->param('unit', 'km');
                    $count = $this->request->param('count', 10, 'intval');
                    
                    if ($longitude && $latitude) {
                        $options = [
                            'WITHDIST' => true, // 返回距离
                            'COUNT' => $count, // 限制返回数量
                            'ASC' => true, // 按距离正序排列
                        ];
                        
                        $nearbyStores = $redis->geoRadius($key, $longitude, $latitude, $radius, $unit, $options);
                        
                        // 格式化结果
                        $formattedStores = [];
                        foreach ($nearbyStores as $item) {
                            $member = $item[0];
                            $distance = $item[1];
                            
                            $storeId = str_replace('store:', '', $member);
                            $storeInfo = $redis->hash()->hGetAll("store:{$storeId}");
                            
                            if ($storeInfo) {
                                $storeInfo['distance'] = $distance;
                                $storeInfo['unit'] = $unit;
                                $formattedStores[] = $storeInfo;
                            }
                        }
                        
                        $result = [
                            'status' => 'success',
                            'longitude' => $longitude,
                            'latitude' => $latitude,
                            'radius' => $radius,
                            'unit' => $unit,
                            'nearby_stores' => $formattedStores,
                            'count' => count($formattedStores),
                        ];
                    } else {
                        $result = [
                            'status' => 'error',
                            'message' => '位置信息不能为空',
                        ];
                    }
                    break;
                    
                case 'list':
                default:
                    // 列出所有店铺
                    $count = $redis->zCard($key); // GEO实际上是使用有序集合实现的
                    
                    // 获取所有店铺
                    $stores = [];
                    if ($count > 0) {
                        $members = $redis->zRange($key, 0, -1);
                        
                        foreach ($members as $member) {
                            $storeId = str_replace('store:', '', $member);
                            $storeInfo = $redis->hash()->hGetAll("store:{$storeId}");
                            
                            if ($storeInfo) {
                                $stores[] = $storeInfo;
                            }
                        }
                    }
                    
                    // 如果没有店铺，添加一些示例数据
                    if (empty($stores)) {
                        // 预设一些示例店铺
                        $sampleStores = [
                            ['id' => 1, 'name' => '星巴克(国贸店)', 'longitude' => 116.46, 'latitude' => 39.91],
                            ['id' => 2, 'name' => '肯德基(王府井店)', 'longitude' => 116.41, 'latitude' => 39.92],
                            ['id' => 3, 'name' => '麦当劳(东单店)', 'longitude' => 116.42, 'latitude' => 39.91],
                            ['id' => 4, 'name' => '必胜客(西单店)', 'longitude' => 116.37, 'latitude' => 39.91],
                            ['id' => 5, 'name' => '星巴克(中关村店)', 'longitude' => 116.32, 'latitude' => 39.98],
                        ];
                        
                        foreach ($sampleStores as $store) {
                            // 将店铺信息存储到Hash中
                            $redis->hash()->hMSet("store:{$store['id']}", array_merge($store, ['create_time' => time()]));
                            
                            // 添加到地理位置索引
                            $redis->geoAdd($key, $store['longitude'], $store['latitude'], "store:{$store['id']}");
                            
                            $stores[] = array_merge($store, ['create_time' => time()]);
                        }
                    }
                    
                    $result = [
                        'status' => 'success',
                        'total_stores' => count($stores),
                        'stores' => $stores,
                    ];
                    break;
            }
            
            return $this->success('店铺查找操作成功', $result);
        } catch (\Throwable $e) {
            return $this->error('店铺查找操作失败：' . $e->getMessage());
        }
    }
    
    /**
     * 路径规划示例
     */
    public function routePlanning()
    {
        try {
            $redis = Redis::geo();
            $action = $this->request->param('action', 'route');
            
            $key = 'geo_demo_locations';
            
            switch ($action) {
                case 'add_poi':
                    // 添加兴趣点
                    $poiId = $this->request->param('poi_id', '');
                    $poiName = $this->request->param('poi_name', '');
                    $longitude = $this->request->param('longitude', 0, 'floatval');
                    $latitude = $this->request->param('latitude', 0, 'floatval');
                    
                    if (!empty($poiId) && !empty($poiName) && $longitude && $latitude) {
                        // 将POI信息存储到Hash中
                        $redis->hash()->hMSet("poi:{$poiId}", [
                            'id' => $poiId,
                            'name' => $poiName,
                            'longitude' => $longitude,
                            'latitude' => $latitude,
                        ]);
                        
                        // 添加到地理位置索引
                        $redis->geoAdd($key, $longitude, $latitude, "poi:{$poiId}");
                        
                        $result = [
                            'status' => 'success',
                            'message' => "兴趣点 {$poiName} 已添加",
                            'poi_id' => $poiId,
                            'poi_name' => $poiName,
                            'longitude' => $longitude,
                            'latitude' => $latitude,
                        ];
                    } else {
                        $result = [
                            'status' => 'error',
                            'message' => '兴趣点信息不完整',
                        ];
                    }
                    break;
                    
                case 'route':
                default:
                    // 路径规划（查找从起点到终点途径的POI）
                    $startLon = $this->request->param('start_lon', 0, 'floatval');
                    $startLat = $this->request->param('start_lat', 0, 'floatval');
                    $endLon = $this->request->param('end_lon', 0, 'floatval');
                    $endLat = $this->request->param('end_lat', 0, 'floatval');
                    $radius = $this->request->param('radius', 1, 'floatval'); // 默认1公里
                    
                    if (!$startLon || !$startLat || !$endLon || !$endLat) {
                        // 如果没有指定坐标，使用默认的示例数据
                        // 初始化一些示例POI数据
                        if ($redis->zCard($key) == 0) {
                            $samplePOIs = [
                                ['id' => 'station1', 'name' => '地铁1号线国贸站', 'longitude' => 116.46, 'latitude' => 39.91],
                                ['id' => 'station2', 'name' => '地铁1号线王府井站', 'longitude' => 116.41, 'latitude' => 39.92],
                                ['id' => 'station3', 'name' => '地铁1号线西单站', 'longitude' => 116.37, 'latitude' => 39.91],
                                ['id' => 'station4', 'name' => '地铁1号线复兴门站', 'longitude' => 116.35, 'latitude' => 39.90],
                                ['id' => 'station5', 'name' => '地铁1号线军事博物馆站', 'longitude' => 116.33, 'latitude' => 39.90],
                                ['id' => 'station6', 'name' => '地铁1号线公主坟站', 'longitude' => 116.30, 'latitude' => 39.90],
                                ['id' => 'poi1', 'name' => '故宫博物院', 'longitude' => 116.40, 'latitude' => 39.92],
                                ['id' => 'poi2', 'name' => '天安门广场', 'longitude' => 116.39, 'latitude' => 39.90],
                                ['id' => 'poi3', 'name' => '北京动物园', 'longitude' => 116.34, 'latitude' => 39.94],
                                ['id' => 'poi4', 'name' => '国家图书馆', 'longitude' => 116.32, 'latitude' => 39.94],
                            ];
                            
                            foreach ($samplePOIs as $poi) {
                                // 将POI信息存储到Hash中
                                $redis->hash()->hMSet("poi:{$poi['id']}", $poi);
                                
                                // 添加到地理位置索引
                                $redis->geoAdd($key, $poi['longitude'], $poi['latitude'], "poi:{$poi['id']}");
                            }
                        }
                        
                        // 使用默认的起点和终点
                        $startLon = 116.46; // 国贸
                        $startLat = 39.91;
                        $endLon = 116.30; // 公主坟
                        $endLat = 39.90;
                    }
                    
                    // 计算路径的大致方向和距离
                    $dLon = $endLon - $startLon;
                    $dLat = $endLat - $startLat;
                    $distance = sqrt($dLon * $dLon + $dLat * $dLat) * 111; // 粗略计算，每度约111公里
                    
                    // 生成路径上的一系列点
                    $points = [];
                    $steps = max(5, ceil($distance / $radius)); // 至少生成5个点
                    
                    for ($i = 0; $i <= $steps; $i++) {
                        $t = $i / $steps;
                        $lon = $startLon + $t * $dLon;
                        $lat = $startLat + $t * $dLat;
                        $points[] = [$lon, $lat];
                    }
                    
                    // 查询每个点附近的POI
                    $routePOIs = [];
                    $visitedPOIs = [];
                    
                    foreach ($points as $point) {
                        $lon = $point[0];
                        $lat = $point[1];
                        
                        $nearbyPOIs = $redis->geoRadius($key, $lon, $lat, $radius, 'km', [
                            'WITHDIST' => true,
                            'ASC' => true,
                        ]);
                        
                        foreach ($nearbyPOIs as $item) {
                            $poiKey = $item[0];
                            $distance = $item[1];
                            
                            // 避免重复添加同一个POI
                            if (!isset($visitedPOIs[$poiKey])) {
                                $poiId = str_replace('poi:', '', $poiKey);
                                $poiInfo = $redis->hash()->hGetAll("poi:{$poiId}");
                                
                                if ($poiInfo) {
                                    $poiInfo['distance'] = $distance;
                                    $routePOIs[] = $poiInfo;
                                    $visitedPOIs[$poiKey] = true;
                                }
                            }
                        }
                    }
                    
                    // 按照与路径的位置排序
                    usort($routePOIs, function($a, $b) use ($startLon, $startLat) {
                        $distA = sqrt(pow($a['longitude'] - $startLon, 2) + pow($a['latitude'] - $startLat, 2));
                        $distB = sqrt(pow($b['longitude'] - $startLon, 2) + pow($b['latitude'] - $startLat, 2));
                        return $distA <=> $distB;
                    });
                    
                    $result = [
                        'status' => 'success',
                        'start' => ['longitude' => $startLon, 'latitude' => $startLat],
                        'end' => ['longitude' => $endLon, 'latitude' => $endLat],
                        'route_distance' => round($distance, 2) . ' km',
                        'radius' => $radius,
                        'route_points' => $points,
                        'route_pois' => $routePOIs,
                        'poi_count' => count($routePOIs),
                    ];
                    break;
            }
            
            return $this->success('路径规划操作成功', $result);
        } catch (\Throwable $e) {
            return $this->error('路径规划操作失败：' . $e->getMessage());
        }
    }
} 