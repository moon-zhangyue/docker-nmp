<?php
declare(strict_types=1);

namespace app\controller\redis;

use app\controller\RedisDemo;
use app\facade\Redis;
use think\facade\View;
use think\Response;

/**
 * Redis Geo类型演示控制器
 *
 * 演示Redis Geo类型的常见应用场景
 *
 * @OA\Tag(
 *     name="Redis Geo",
 *     description="Redis Geo地理位置相关操作"
 * )
 */
class GeoDemo extends RedisDemo
{
    /**
     * 城市坐标数据
     *
     * @var array
     */
    protected array $cities = [
        'beijing'   => ['116.405285', '39.904989', '北京'],
        'shanghai'  => ['121.472644', '31.231706', '上海'],
        'guangzhou' => ['113.280637', '23.125178', '广州'],
        'shenzhen'  => ['114.085947', '22.547', '深圳'],
        'hangzhou'  => ['120.155070', '30.274084', '杭州'],
        'chengdu'   => ['104.065735', '30.659462', '成都'],
        'wuhan'     => ['114.298572', '30.584355', '武汉'],
        'xian'      => ['108.948024', '34.263161', '西安'],
        'nanjing'   => ['118.767413', '32.041544', '南京'],
        'suzhou'    => ['120.585315', '31.298886', '苏州'],
    ];

    /**
     * 演示页面
     *
     * @return \think\Response
     */
    public function index(): Response
    {
        return response(View::fetch('redis/geo/index'));
    }

    /**
     * 获取Redis Geo实例
     *
     * @return \app\service\redis\GeoService
     */
    protected function getRedisGeo()
    {
        return Redis::geo();
    }

    /**
     * 基本用法示例
     *
     * @return Response
     */
    public function basic()
    {
        try {
            $redis = $this->getRedisGeo();
            $key   = 'geo_demo_cities';

            // 清空之前的测试数据
            $redis->delete($key);

            // 添加城市地理位置
            $addResults = [];
            foreach ($this->cities as $city => $location) {
                $result            = $redis->geoAdd($key, (float)$location[0], (float)$location[1], $city);
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
                'withdist'  => true, // 返回距离
                'withcoord' => true, // 返回坐标
                'asc'       => true, // 按距离正序排列
            ]);

            // 获取北京的GeoHash值
            $beijingHash = $redis->geoHash($key, 'beijing');

            // 批量获取GeoHash值
            $cityHashes = $redis->geoBatchHash($key, ['beijing', 'shanghai', 'guangzhou']);

            return $this->success('Geo基本用法演示成功', [
                'add_results'                   => $addResults,
                'beijing_position'              => $beijingPos,
                'distance_beijing_to_shanghai'  => $distanceBeijingToShanghai,
                'distance_hangzhou_to_shanghai' => $distanceHangzhouToShanghai,
                'cities_near_shanghai'          => $nearShanghai,
                'beijing_geohash'               => $beijingHash,
                'city_geohashes'                => $cityHashes,
            ]);
        } catch (\Throwable $e) {
            return $this->error('Geo基本用法演示失败：' . $e->getMessage());
        }
    }

    /**
     * 附近的人示例
     *
     * @return Response
     */
    public function nearbyUsers()
    {
        try {
            $redis  = $this->getRedisGeo();
            $action = $this->request->param('action', 'list');

            $key = 'geo_demo_users';

            switch ($action) {
                case 'add':
                    // 添加用户位置
                    $userId = $this->request->param('user_id', 0, 'intval');
                    $longitude = $this->request->param('longitude', 0, 'floatval');
                    $latitude = $this->request->param('latitude', 0, 'floatval');

                    // 验证经纬度范围
                    if ($longitude < -180 || $longitude > 180 || $latitude < -90 || $latitude > 90) {
                        return $this->error('经纬度范围无效');
                    }

                    if ($userId > 0 && $longitude && $latitude) {
                        $redis->geoAdd($key, (float)$longitude, (float)$latitude, "user:{$userId}");

                        $result = [
                            'status'    => 'success',
                            'message'   => "用户 {$userId} 位置已更新",
                            'user_id'   => $userId,
                            'longitude' => $longitude,
                            'latitude'  => $latitude,
                        ];
                    } else {
                        $result = [
                            'status'  => 'error',
                            'message' => '用户ID和位置信息不能为空',
                        ];
                    }
                    break;

                case 'nearby':
                    // 查找附近的人
                    $userId = $this->request->param('user_id', 0, 'intval');
                    $radius = $this->request->param('radius', 5, 'floatval'); // 默认5公里
                    $unit = $this->request->param('unit', 'km', 'trim');// 默认单位为公里
                    $count = $this->request->param('count', 10, 'intval');// 默认返回10条数据

                    // 验证参数
                    if ($radius <= 0) {
                        return $this->error('搜索半径必须大于0');
                    }

                    if (!in_array($unit, ['m', 'km', 'mi', 'ft'])) {
                        $unit = 'km'; // 默认使用公里
                    }

                    $count = max(1, min(100, $count)); // 限制返回数量在1-100之间

                    // 如果指定了用户ID，则查找该用户附近的人
                    if ($userId > 0) {
                        $userKey = "user:{$userId}";
                        $options = [
                            'withdist' => true, // 返回距离
                            'count'    => $count, // 限制返回数量
                            'asc'      => true, // 按距离正序排列
                        ];

                        $nearby = $redis->geoRadiusByMember($key, $userKey, $radius, $unit, $options);

                        // 格式化结果
                        $formattedNearby = [];
                        foreach ($nearby as $item) {
                            if (!is_array($item) || count($item) < 2) {
                                continue; // 跳过无效数据
                            }

                            $member   = $item[0] ?? '';
                            $distance = $item[1] ?? 0;

                            // 排除自己
                            if ($member && $member !== $userKey) {
                                $nearUserId        = str_replace('user:', '', $member);
                                $formattedNearby[] = [
                                    'user_id'  => $nearUserId,
                                    'distance' => $distance,
                                    'unit'     => $unit,
                                ];
                            }
                        }

                        $result = [
                            'status'       => 'success',
                            'user_id'      => $userId,
                            'radius'       => $radius,
                            'unit'         => $unit,
                            'nearby_users' => $formattedNearby,
                            'count'        => count($formattedNearby),
                        ];
                    } else {
                        // 如果没有指定用户ID，则使用经纬度查找
                        $longitude = $this->request->param('longitude', 0, 'floatval');
                        $latitude  = $this->request->param('latitude', 0, 'floatval');

                        // 验证经纬度范围
                        if ($longitude < -180 || $longitude > 180 || $latitude < -90 || $latitude > 90) {
                            return $this->error('经纬度范围无效');
                        }

                        if ($longitude && $latitude) {
                            $options = [
                                'withdist'  => true, // 返回距离
                                'withcoord' => true, // 返回坐标
                                'count'     => $count, // 限制返回数量
                                'asc'       => true, // 按距离正序排列
                            ];

                            $nearby = $redis->geoRadius($key, $longitude, $latitude, $radius, $unit, $options);

                            // 格式化结果
                            $formattedNearby = [];
                            foreach ($nearby as $item) {
                                if (!is_array($item) || count($item) < 3) {
                                    continue; // 跳过无效数据
                                }

                                $member   = $item[0] ?? '';
                                $distance = $item[1] ?? 0;
                                $coord    = $item[2] ?? [];

                                if (!$member || !is_array($coord) || count($coord) < 2) {
                                    continue; // 跳过无效数据
                                }

                                $nearUserId        = str_replace('user:', '', $member);
                                $formattedNearby[] = [
                                    'user_id'   => $nearUserId,
                                    'distance'  => $distance,
                                    'unit'      => $unit,
                                    'longitude' => $coord[0] ?? 0,
                                    'latitude'  => $coord[1] ?? 0,
                                ];
                            }

                            $result = [
                                'status'       => 'success',
                                'longitude'    => $longitude,
                                'latitude'     => $latitude,
                                'radius'       => $radius,
                                'unit'         => $unit,
                                'nearby_users' => $formattedNearby,
                                'count'        => count($formattedNearby),
                            ];
                        } else {
                            $result = [
                                'status'  => 'error',
                                'message' => '必须指定用户ID或经纬度',
                            ];
                        }
                    }
                    break;

                case 'list':
                default:
                    // 列出所有用户位置
                    $count = Redis::zset()->zCard($key); // GEO实际上是使用有序集合实现的

                    // 获取所有用户
                    $users = [];
                    if ($count > 0) {
                        $members = Redis::zset()->zRange($key, 0, -1);

                        if (is_array($members)) {
                            foreach ($members as $member) {
                                if (empty($member)) {
                                    continue; // 跳过无效数据
                                }

                                $userId   = str_replace('user:', '', $member);
                                $position = $redis->geoPos($key, $member);

                                if ($position && is_array($position) && isset($position[0])) {
                                    $users[] = [
                                        'user_id'   => $userId,
                                        'longitude' => $position[0] ?? 0,
                                        'latitude'  => $position[1] ?? 0,
                                    ];
                                }
                            }
                        }
                    }

                    $result = [
                        'status'      => 'success',
                        'total_users' => $count,
                        'users'       => $users,
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
     *
     * @return Response
     */
    public function storeLocator()
    {
        try {
            $redis  = $this->getRedisGeo();
            $action = $this->request->param('action', 'list');

            $key = 'geo_demo_stores';

            switch ($action) {
                case 'add':
                    // 添加店铺
                    $storeId = $this->request->param('store_id', 0, 'intval');
                    $storeName = $this->request->param('store_name', '', 'trim');
                    $longitude = $this->request->param('longitude', 0, 'floatval');
                    $latitude = $this->request->param('latitude', 0, 'floatval');

                    // 验证经纬度范围
                    if ($longitude < -180 || $longitude > 180 || $latitude < -90 || $latitude > 90) {
                        return $this->error('经纬度范围无效');
                    }

                    if ($storeId > 0 && !empty($storeName) && $longitude && $latitude) {
                        // 将店铺信息存储到Hash中
                        Redis::hash()->hMSet("store:{$storeId}", [
                            'id'          => $storeId,
                            'name'        => $storeName,
                            'longitude'   => $longitude,
                            'latitude'    => $latitude,
                            'create_time' => time(),
                        ]);

                        // 添加到地理位置索引
                        $redis->geoAdd($key, (float)$longitude, (float)$latitude, "store:{$storeId}");

                        $result = [
                            'status'     => 'success',
                            'message'    => "店铺 {$storeName} 已添加",
                            'store_id'   => $storeId,
                            'store_name' => $storeName,
                            'longitude'  => $longitude,
                            'latitude'   => $latitude,
                        ];
                    } else {
                        $result = [
                            'status'  => 'error',
                            'message' => '店铺信息不完整',
                        ];
                    }
                    break;

                case 'search':
                    // 查找附近的店铺
                    $longitude = $this->request->param('longitude', 0, 'floatval');
                    $latitude = $this->request->param('latitude', 0, 'floatval');
                    $radius = $this->request->param('radius', 5, 'floatval'); // 默认5公里
                    $unit = $this->request->param('unit', 'km', 'trim');
                    $count = $this->request->param('count', 10, 'intval');

                    // 验证参数
                    if ($radius <= 0) {
                        return $this->error('搜索半径必须大于0');
                    }

                    if (!in_array($unit, ['m', 'km', 'mi', 'ft'])) {
                        $unit = 'km'; // 默认使用公里
                    }

                    $count = max(1, min(100, $count)); // 限制返回数量在1-100之间

                    if ($longitude && $latitude) {
                        $options = [
                            'withdist' => true, // 返回距离
                            'count'    => $count, // 限制返回数量
                            'asc'      => true, // 按距离正序排列
                        ];

                        $nearbyStores = $redis->geoRadius($key, $longitude, $latitude, $radius, $unit, $options);

                        // 格式化结果
                        $formattedStores = [];
                        foreach ($nearbyStores as $item) {
                            if (!is_array($item) || count($item) < 2) {
                                continue; // 跳过无效数据
                            }

                            $member   = $item[0] ?? '';
                            $distance = $item[1] ?? 0;

                            if (empty($member)) {
                                continue; // 跳过无效数据
                            }

                            $storeId   = str_replace('store:', '', $member);
                            $storeInfo = Redis::hash()->hGetAll("store:{$storeId}");

                            if ($storeInfo && !empty($storeInfo)) {
                                $storeInfo['distance'] = $distance;
                                $storeInfo['unit']     = $unit;
                                $formattedStores[]     = $storeInfo;
                            }
                        }

                        $result = [
                            'status'        => 'success',
                            'longitude'     => $longitude,
                            'latitude'      => $latitude,
                            'radius'        => $radius,
                            'unit'          => $unit,
                            'nearby_stores' => $formattedStores,
                            'count'         => count($formattedStores),
                        ];
                    } else {
                        $result = [
                            'status'  => 'error',
                            'message' => '位置信息不能为空',
                        ];
                    }
                    break;

                case 'list':
                default:
                    // 列出所有店铺
                    $count = Redis::zset()->zCard($key); // GEO实际上是使用有序集合实现的

                    // 获取所有店铺
                    $stores = [];
                    if ($count > 0) {
                        $members = Redis::zset()->zRange($key, 0, -1);

                        if (is_array($members)) {
                            foreach ($members as $member) {
                                if (empty($member)) {
                                    continue; // 跳过无效数据
                                }

                                $storeId   = str_replace('store:', '', $member);
                                $storeInfo = Redis::hash()->hGetAll("store:{$storeId}");

                                if ($storeInfo && !empty($storeInfo)) {
                                    $stores[] = $storeInfo;
                                }
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
                            Redis::hash()->hMSet("store:{$store['id']}", array_merge($store, ['create_time' => time()]));

                            // 添加到地理位置索引
                            $redis->geoAdd($key, (float)$store['longitude'], (float)$store['latitude'], "store:{$store['id']}");

                            $stores[] = array_merge($store, ['create_time' => time()]);
                        }
                    }

                    $result = [
                        'status'       => 'success',
                        'total_stores' => count($stores),
                        'stores'       => $stores,
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
     *
     * @return Response
     */
    public function routePlanning()
    {
        try {
            $redis  = $this->getRedisGeo();
            $action = $this->request->param('action', 'route');

            $key = 'geo_demo_locations';

            switch ($action) {
                case 'add_poi':
                    // 添加兴趣点
                    $poiId = $this->request->param('poi_id', '', 'trim');
                    $poiName = $this->request->param('poi_name', '', 'trim');
                    $longitude = $this->request->param('longitude', 0, 'floatval');
                    $latitude = $this->request->param('latitude', 0, 'floatval');

                    // 验证经纬度范围
                    if ($longitude < -180 || $longitude > 180 || $latitude < -90 || $latitude > 90) {
                        return $this->error('经纬度范围无效');
                    }

                    if (!empty($poiId) && !empty($poiName) && $longitude && $latitude) {
                        // 将POI信息存储到Hash中
                        Redis::hash()->hMSet("poi:{$poiId}", [
                            'id'        => $poiId,
                            'name'      => $poiName,
                            'longitude' => $longitude,
                            'latitude'  => $latitude,
                        ]);

                        // 添加到地理位置索引
                        $redis->geoAdd($key, (float)$longitude, (float)$latitude, "poi:{$poiId}");

                        $result = [
                            'status'    => 'success',
                            'message'   => "兴趣点 {$poiName} 已添加",
                            'poi_id'    => $poiId,
                            'poi_name'  => $poiName,
                            'longitude' => $longitude,
                            'latitude'  => $latitude,
                        ];
                    } else {
                        $result = [
                            'status'  => 'error',
                            'message' => '兴趣点信息不完整',
                        ];
                    }
                    break;

                case 'route':
                default:
                    // 路径规划（查找从起点到终点途径的POI）
                    $startLon = $this->request->param('start_lon', 0, 'floatval');// 获取起始经度
                    $startLat = $this->request->param('start_lat', 0, 'floatval');// 获取起始纬度
                    $endLon = $this->request->param('end_lon', 0, 'floatval');
                    $endLat = $this->request->param('end_lat', 0, 'floatval');// 获取结束纬度
                    $radius = $this->request->param('radius', 1, 'floatval'); // 默认1公里

                    // 验证参数
                    if ($radius <= 0) {
                        $radius = 1; // 确保半径为正数
                    }

                    // 验证经纬度范围
                    $validCoords = true;
                    if ($startLon && $startLat && $endLon && $endLat) {
                        if (
                            $startLon < -180 || $startLon > 180 || $startLat < -90 || $startLat > 90 ||
                            $endLon < -180 || $endLon > 180 || $endLat < -90 || $endLat > 90
                        ) {
                            $validCoords = false;
                        }
                    }

                    if (!$validCoords && ($startLon || $startLat || $endLon || $endLat)) {
                        return $this->error('经纬度范围无效');
                    }

                    if (!$startLon || !$startLat || !$endLon || !$endLat) {
                        // 如果没有指定坐标，使用默认的示例数据
                        // 初始化一些示例POI数据
                        if (Redis::zset()->zCard($key) == 0) {
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
                                Redis::hash()->hMSet("poi:{$poi['id']}", $poi);

                                // 添加到地理位置索引
                                $redis->geoAdd($key, (float)$poi['longitude'], (float)$poi['latitude'], "poi:{$poi['id']}");
                            }
                        }

                        // 使用默认的起点和终点
                        $startLon = 116.46; // 国贸
                        $startLat = 39.91;
                        $endLon   = 116.30; // 公主坟
                        $endLat   = 39.90;
                    }

                    // 使用Haversine公式计算球面距离，更准确
                    $earthRadius = 6371; // 地球半径，单位公里
                    $dLat = deg2rad($endLat - $startLat);
                    $dLon = deg2rad($endLon - $startLon);
                    $a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($startLat)) * cos(deg2rad($endLat)) * sin($dLon / 2) * sin($dLon / 2);
                    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
                    $distance = $earthRadius * $c; // 距离，单位公里

                    // 生成路径上的一系列点
                    $points = [];
                    $steps = max(5, ceil($distance / max(0.1, $radius))); // 至少生成5个点，避免除以0或极小值

                    for ($i = 0; $i <= $steps; $i++) {
                        $t        = $i / $steps;
                        $lon      = $startLon + $t * $dLon;
                        $lat      = $startLat + $t * $dLat;
                        $points[] = [$lon, $lat];
                    }

                    // 查询每个点附近的POI
                    $routePOIs = [];
                    $visitedPOIs = [];

                    foreach ($points as $point) {
                        $lon = $point[0];
                        $lat = $point[1];

                        $nearbyPOIs = $redis->geoRadius($key, $lon, $lat, $radius, 'km', [
                            'withdist' => true,
                            'asc'      => true,
                        ]);

                        foreach ($nearbyPOIs as $item) {
                            if (!is_array($item) || count($item) < 2) {
                                continue; // 跳过无效数据
                            }

                            $poiKey   = $item[0] ?? '';
                            $distance = $item[1] ?? 0;

                            if (empty($poiKey)) {
                                continue; // 跳过无效数据
                            }

                            // 避免重复添加同一个POI
                            if (!isset($visitedPOIs[$poiKey])) {
                                $poiId   = str_replace('poi:', '', $poiKey);
                                $poiInfo = Redis::hash()->hGetAll("poi:{$poiId}");

                                if ($poiInfo && !empty($poiInfo)) {
                                    $poiInfo['distance']  = $distance;
                                    $routePOIs[]          = $poiInfo;
                                    $visitedPOIs[$poiKey] = true;
                                }
                            }
                        }
                    }

                    // 按照与路径的位置排序
                    usort($routePOIs, function ($a, $b) use ($startLon, $startLat) {
                        // 确保经纬度是浮点数
                        $aLon = (float) ($a['longitude'] ?? 0);
                        $aLat = (float) ($a['latitude'] ?? 0);
                        $bLon = (float) ($b['longitude'] ?? 0);
                        $bLat = (float) ($b['latitude'] ?? 0);

                        $distA = sqrt(pow($aLon - $startLon, 2) + pow($aLat - $startLat, 2));
                        $distB = sqrt(pow($bLon - $startLon, 2) + pow($bLat - $startLat, 2));
                        return $distA <=> $distB;
                    });

                    $result = [
                        'status'         => 'success',
                        'start'          => ['longitude' => $startLon, 'latitude' => $startLat],
                        'end'            => ['longitude' => $endLon, 'latitude' => $endLat],
                        'route_distance' => round($distance, 2) . ' km',
                        'radius'         => $radius,
                        'route_points'   => $points,
                        'route_pois'     => $routePOIs,
                        'poi_count'      => count($routePOIs),
                    ];
                    break;
            }

            return $this->success('路径规划操作成功', $result);
        } catch (\Throwable $e) {
            return $this->error('路径规划操作失败：' . $e->getMessage());
        }
    }
}