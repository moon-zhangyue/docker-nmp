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
     * 有效的距离单位
     * 
     * @var array
     */
    protected array $validUnits = ['m', 'km', 'mi', 'ft'];

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
     * 验证经纬度是否有效
     *
     * @param float $longitude 经度
     * @param float $latitude 纬度
     * @return bool
     */
    protected function isValidCoordinates(float $longitude, float $latitude): bool
    {
        return !($longitude < -180 || $longitude > 180 || $latitude < -90 || $latitude > 90);
    }
    
    /**
     * 获取标准化的距离单位
     *
     * @param string $unit 距离单位
     * @return string
     */
    protected function getValidUnit(string $unit): string
    {
        return in_array($unit, $this->validUnits) ? $unit : 'km';
    }
    
    /**
     * 获取有效的计数限制
     *
     * @param int $count 请求的计数
     * @param int $min 最小值
     * @param int $max 最大值
     * @return int
     */
    protected function getValidCount(int $count, int $min = 1, int $max = 100): int
    {
        return max($min, min($max, $count));
    }
    
    /**
     * 获取标准搜索选项
     *
     * @param int $count 结果数量限制
     * @param bool $withCoord 是否返回坐标
     * @return array
     */
    protected function getSearchOptions(int $count, bool $withCoord = false): array
    {
        $options = [
            'withdist' => true,
            'count'    => $count,
            'asc'      => true,
        ];
        
        if ($withCoord) {
            $options['withcoord'] = true;
        }
        
        return $options;
    }
    
    /**
     * 计算两点之间的球面距离
     *
     * @param float $startLon 起点经度
     * @param float $startLat 起点纬度
     * @param float $endLon 终点经度
     * @param float $endLat 终点纬度
     * @return float 距离（公里）
     */
    protected function calculateDistance(float $startLon, float $startLat, float $endLon, float $endLat): float
    {
        $earthRadius = 6371; // 地球半径，单位公里
        $dLat = deg2rad($endLat - $startLat);
        $dLon = deg2rad($endLon - $startLon);
        $a = sin($dLat / 2) * sin($dLat / 2) + 
             cos(deg2rad($startLat)) * cos(deg2rad($endLat)) * 
             sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
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
                $addResults[$city] = $redis->geoAdd(
                    $key, 
                    (float)$location[0], 
                    (float)$location[1], 
                    $city
                );
            }

            // 获取数据
            $data = [
                'add_results'                   => $addResults,
                'beijing_position'              => $redis->geoPos($key, 'beijing'),
                'distance_beijing_to_shanghai'  => $redis->geoDist($key, 'beijing', 'shanghai', 'km'),
                'distance_hangzhou_to_shanghai' => $redis->geoDist($key, 'hangzhou', 'shanghai', 'km'),
                'cities_near_shanghai'          => $redis->geoRadius(
                    $key, 
                    121.472644, 
                    31.231706, 
                    300, 
                    'km', 
                    $this->getSearchOptions(10, true)
                ),
                'beijing_geohash'               => $redis->geoHash($key, 'beijing'),
                'city_geohashes'                => $redis->geoBatchHash($key, ['beijing', 'shanghai', 'guangzhou']),
            ];

            return $this->success('Geo基本用法演示成功', $data);
        } catch (\Throwable $e) {
            return $this->error('Geo基本用法演示失败：' . $e->getMessage());
        }
    }

    /**
     * 添加用户位置
     *
     * @param string $key Redis键名
     * @param int $userId 用户ID
     * @param float $longitude 经度
     * @param float $latitude 纬度
     * @return array 结果数组
     */
    protected function addUserLocation(string $key, int $userId, float $longitude, float $latitude): array
    {
        if (!$this->isValidCoordinates($longitude, $latitude)) {
            return [
                'status'  => 'error',
                'message' => '经纬度范围无效'
            ];
        }

        if ($userId <= 0 || !$longitude || !$latitude) {
            return [
                'status'  => 'error',
                'message' => '用户ID和位置信息不能为空'
            ];
        }

        $redis = $this->getRedisGeo();
        $redis->geoAdd($key, (float)$longitude, (float)$latitude, "user:{$userId}");

        return [
            'status'    => 'success',
            'message'   => "用户 {$userId} 位置已更新",
            'user_id'   => $userId,
            'longitude' => $longitude,
            'latitude'  => $latitude,
        ];
    }

    /**
     * 格式化附近用户数据
     *
     * @param array $nearby 附近用户原始数据
     * @param string $userKey 当前用户键名
     * @param string $unit 距离单位
     * @param bool $withCoord 是否包含坐标
     * @return array 格式化后的数据
     */
    protected function formatNearbyUsers(array $nearby, string $userKey = '', string $unit = 'km', bool $withCoord = false): array
    {
        $formattedNearby = [];
        foreach ($nearby as $item) {
            if (!is_array($item) || count($item) < ($withCoord ? 3 : 2)) {
                continue; // 跳过无效数据
            }

            $member   = $item[0] ?? '';
            $distance = $item[1] ?? 0;
            $coord    = $withCoord ? ($item[2] ?? []) : [];

            // 排除自己
            if (empty($member) || (!empty($userKey) && $member === $userKey)) {
                continue;
            }

            $nearUserId = str_replace('user:', '', $member);
            $userData = [
                'user_id'  => $nearUserId,
                'distance' => $distance,
                'unit'     => $unit,
            ];

            if ($withCoord && is_array($coord) && count($coord) >= 2) {
                $userData['longitude'] = $coord[0] ?? 0;
                $userData['latitude']  = $coord[1] ?? 0;
            }

            $formattedNearby[] = $userData;
        }

        return $formattedNearby;
    }

    /**
     * 查找附近的用户
     *
     * @param string $key Redis键名
     * @param int $userId 用户ID
     * @param float $radius 搜索半径
     * @param string $unit 距离单位
     * @param int $count 返回数量
     * @return array 结果数组
     */
    protected function findNearbyUsersByUserId(string $key, int $userId, float $radius, string $unit, int $count): array
    {
        if ($radius <= 0) {
            return [
                'status'  => 'error',
                'message' => '搜索半径必须大于0'
            ];
        }

        $unit = $this->getValidUnit($unit);
        $count = $this->getValidCount($count);
        $userKey = "user:{$userId}";
        $redis = $this->getRedisGeo();
        
        $nearby = $redis->geoRadiusByMember(
            $key, 
            $userKey, 
            $radius, 
            $unit, 
            $this->getSearchOptions($count)
        );

        $formattedNearby = $this->formatNearbyUsers($nearby, $userKey, $unit);

        return [
            'status'       => 'success',
            'user_id'      => $userId,
            'radius'       => $radius,
            'unit'         => $unit,
            'nearby_users' => $formattedNearby,
            'count'        => count($formattedNearby),
        ];
    }

    /**
     * 根据坐标查找附近的用户
     *
     * @param string $key Redis键名
     * @param float $longitude 经度
     * @param float $latitude 纬度
     * @param float $radius 搜索半径
     * @param string $unit 距离单位
     * @param int $count 返回数量
     * @return array 结果数组
     */
    protected function findNearbyUsersByCoordinates(string $key, float $longitude, float $latitude, float $radius, string $unit, int $count): array
    {
        if (!$this->isValidCoordinates($longitude, $latitude)) {
            return [
                'status'  => 'error',
                'message' => '经纬度范围无效'
            ];
        }

        if ($radius <= 0) {
            return [
                'status'  => 'error',
                'message' => '搜索半径必须大于0'
            ];
        }

        if (!$longitude || !$latitude) {
            return [
                'status'  => 'error',
                'message' => '位置信息不能为空'
            ];
        }

        $unit = $this->getValidUnit($unit);
        $count = $this->getValidCount($count);
        $redis = $this->getRedisGeo();
        
        $nearby = $redis->geoRadius(
            $key, 
            $longitude, 
            $latitude, 
            $radius, 
            $unit, 
            $this->getSearchOptions($count, true)
        );

        $formattedNearby = $this->formatNearbyUsers($nearby, '', $unit, true);

        return [
            'status'       => 'success',
            'longitude'    => $longitude,
            'latitude'     => $latitude,
            'radius'       => $radius,
            'unit'         => $unit,
            'nearby_users' => $formattedNearby,
            'count'        => count($formattedNearby),
        ];
    }

    /**
     * 获取所有用户位置列表
     *
     * @param string $key Redis键名
     * @return array 结果数组
     */
    protected function listAllUserLocations(string $key): array
    {
        $redis = $this->getRedisGeo();
        $count = Redis::zset()->zCard($key); // GEO实际上是使用有序集合实现的
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

        return [
            'status'      => 'success',
            'total_users' => $count,
            'users'       => $users,
        ];
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
            $key    = 'geo_demo_users';

            switch ($action) {
                case 'add':
                    // 添加用户位置
                    $userId    = $this->request->param('user_id', 0, 'intval');
                    $longitude = $this->request->param('longitude', 0, 'floatval');
                    $latitude  = $this->request->param('latitude', 0, 'floatval');
                    
                    $result = $this->addUserLocation($key, $userId, $longitude, $latitude);
                    break;

                case 'nearby':
                    // 查找附近的人
                    $userId   = $this->request->param('user_id', 0, 'intval');
                    $radius   = $this->request->param('radius', 5, 'floatval'); 
                    $unit     = $this->request->param('unit', 'km', 'trim');
                    $count    = $this->request->param('count', 10, 'intval');
                    
                    if ($userId > 0) {
                        // 根据用户ID查找附近的人
                        $result = $this->findNearbyUsersByUserId($key, $userId, $radius, $unit, $count);
                    } else {
                        // 根据坐标查找附近的人
                        $longitude = $this->request->param('longitude', 0, 'floatval');
                        $latitude  = $this->request->param('latitude', 0, 'floatval');
                        $result = $this->findNearbyUsersByCoordinates($key, $longitude, $latitude, $radius, $unit, $count);
                    }
                    break;

                case 'list':
                default:
                    // 列出所有用户位置
                    $result = $this->listAllUserLocations($key);
                    break;
            }

            return $this->success('附近的人操作成功', $result);
        } catch (\Throwable $e) {
            return $this->error('附近的人操作失败：' . $e->getMessage());
        }
    }

    /**
     * 添加店铺位置
     *
     * @param string $key Redis键名
     * @param int $storeId 店铺ID
     * @param string $storeName 店铺名称
     * @param float $longitude 经度
     * @param float $latitude 纬度
     * @return array 结果数组
     */
    protected function addStoreLocation(string $key, int $storeId, string $storeName, float $longitude, float $latitude): array
    {
        if (!$this->isValidCoordinates($longitude, $latitude)) {
            return [
                'status'  => 'error',
                'message' => '经纬度范围无效'
            ];
        }

        if ($storeId <= 0 || empty($storeName) || !$longitude || !$latitude) {
            return [
                'status'  => 'error',
                'message' => '店铺信息不完整'
            ];
        }

        // 将店铺信息存储到Hash中
        Redis::hash()->hMSet("store:{$storeId}", [
            'id'          => $storeId,
            'name'        => $storeName,
            'longitude'   => $longitude,
            'latitude'    => $latitude,
            'create_time' => time(),
        ]);

        // 添加到地理位置索引
        $this->getRedisGeo()->geoAdd($key, (float)$longitude, (float)$latitude, "store:{$storeId}");

        return [
            'status'     => 'success',
            'message'    => "店铺 {$storeName} 已添加",
            'store_id'   => $storeId,
            'store_name' => $storeName,
            'longitude'  => $longitude,
            'latitude'   => $latitude,
        ];
    }

    /**
     * 搜索附近的店铺
     *
     * @param string $key Redis键名
     * @param float $longitude 经度
     * @param float $latitude 纬度
     * @param float $radius 搜索半径
     * @param string $unit 距离单位
     * @param int $count 返回数量
     * @return array 结果数组
     */
    protected function searchNearbyStores(string $key, float $longitude, float $latitude, float $radius, string $unit, int $count): array
    {
        if (!$this->isValidCoordinates($longitude, $latitude)) {
            return [
                'status'  => 'error',
                'message' => '经纬度范围无效'
            ];
        }

        if ($radius <= 0) {
            return [
                'status'  => 'error',
                'message' => '搜索半径必须大于0'
            ];
        }

        if (!$longitude || !$latitude) {
            return [
                'status'  => 'error',
                'message' => '位置信息不能为空'
            ];
        }

        $unit = $this->getValidUnit($unit);
        $count = $this->getValidCount($count);
        $redis = $this->getRedisGeo();
        
        $nearbyStores = $redis->geoRadius(
            $key, 
            $longitude, 
            $latitude, 
            $radius, 
            $unit, 
            $this->getSearchOptions($count)
        );

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

        return [
            'status'        => 'success',
            'longitude'     => $longitude,
            'latitude'      => $latitude,
            'radius'        => $radius,
            'unit'          => $unit,
            'nearby_stores' => $formattedStores,
            'count'         => count($formattedStores),
        ];
    }

    /**
     * 获取所有店铺列表
     *
     * @param string $key Redis键名
     * @return array 结果数组
     */
    protected function listAllStores(string $key): array
    {
        $redis = $this->getRedisGeo();
        $count = Redis::zset()->zCard($key); // GEO实际上是使用有序集合实现的
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

        return [
            'status'       => 'success',
            'total_stores' => count($stores),
            'stores'       => $stores,
        ];
    }

    /**
     * 店铺查找示例
     *
     * @return Response
     */
    public function storeLocator()
    {
        try {
            $action = $this->request->param('action', 'list');
            $key = 'geo_demo_stores';

            switch ($action) {
                case 'add':
                    // 添加店铺
                    $storeId   = $this->request->param('store_id', 0, 'intval');
                    $storeName = $this->request->param('store_name', '', 'trim');
                    $longitude = $this->request->param('longitude', 0, 'floatval');
                    $latitude  = $this->request->param('latitude', 0, 'floatval');
                    
                    $result = $this->addStoreLocation($key, $storeId, $storeName, $longitude, $latitude);
                    break;

                case 'search':
                    // 查找附近的店铺
                    $longitude = $this->request->param('longitude', 0, 'floatval');
                    $latitude  = $this->request->param('latitude', 0, 'floatval');
                    $radius    = $this->request->param('radius', 5, 'floatval');
                    $unit      = $this->request->param('unit', 'km', 'trim');
                    $count     = $this->request->param('count', 10, 'intval');
                    
                    $result = $this->searchNearbyStores($key, $longitude, $latitude, $radius, $unit, $count);
                    break;

                case 'list':
                default:
                    // 列出所有店铺
                    $result = $this->listAllStores($key);
                    break;
            }

            return $this->success('店铺查找操作成功', $result);
        } catch (\Throwable $e) {
            return $this->error('店铺查找操作失败：' . $e->getMessage());
        }
    }

    /**
     * 添加兴趣点
     *
     * @param string $key Redis键名
     * @param string $poiId POI ID
     * @param string $poiName POI名称
     * @param float $longitude 经度
     * @param float $latitude 纬度
     * @return array 结果数组
     */
    protected function addPoi(string $key, string $poiId, string $poiName, float $longitude, float $latitude): array
    {
        if (!$this->isValidCoordinates($longitude, $latitude)) {
            return [
                'status'  => 'error',
                'message' => '经纬度范围无效'
            ];
        }

        if (empty($poiId) || empty($poiName) || !$longitude || !$latitude) {
            return [
                'status'  => 'error',
                'message' => '兴趣点信息不完整'
            ];
        }

        // 将POI信息存储到Hash中
        Redis::hash()->hMSet("poi:{$poiId}", [
            'id'        => $poiId,
            'name'      => $poiName,
            'longitude' => $longitude,
            'latitude'  => $latitude,
        ]);

        // 添加到地理位置索引
        $this->getRedisGeo()->geoAdd($key, (float)$longitude, (float)$latitude, "poi:{$poiId}");

        return [
            'status'    => 'success',
            'message'   => "兴趣点 {$poiName} 已添加",
            'poi_id'    => $poiId,
            'poi_name'  => $poiName,
            'longitude' => $longitude,
            'latitude'  => $latitude,
        ];
    }

    /**
     * 初始化示例POI数据
     *
     * @param string $key Redis键名
     * @return void
     */
    protected function initSamplePois(string $key): void
    {
        $redis = $this->getRedisGeo();
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

    /**
     * 路径规划计算
     *
     * @param string $key Redis键名
     * @param float $startLon 起点经度
     * @param float $startLat 起点纬度
     * @param float $endLon 终点经度
     * @param float $endLat 终点纬度
     * @param float $radius 搜索半径
     * @return array 结果数组
     */
    protected function calculateRoute(string $key, float $startLon, float $startLat, float $endLon, float $endLat, float $radius): array
    {
        $redis = $this->getRedisGeo();
        
        // 计算两点间距离
        $distance = $this->calculateDistance($startLon, $startLat, $endLon, $endLat);
        
        // 生成路径上的一系列点
        $points = [];
        $steps = max(5, ceil($distance / max(0.1, $radius))); // 至少生成5个点
        
        for ($i = 0; $i <= $steps; $i++) {
            $t        = $i / $steps;
            $lon      = $startLon + $t * ($endLon - $startLon);
            $lat      = $startLat + $t * ($endLat - $startLat);
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
        
        return [
            'status'         => 'success',
            'start'          => ['longitude' => $startLon, 'latitude' => $startLat],
            'end'            => ['longitude' => $endLon, 'latitude' => $endLat],
            'route_distance' => round($distance, 2) . ' km',
            'radius'         => $radius,
            'route_points'   => $points,
            'route_pois'     => $routePOIs,
            'poi_count'      => count($routePOIs),
        ];
    }

    /**
     * 路径规划示例
     *
     * @return Response
     */
    public function routePlanning()
    {
        try {
            $action = $this->request->param('action', 'route');
            $key = 'geo_demo_locations';

            switch ($action) {
                case 'add_poi':
                    // 添加兴趣点
                    $poiId     = $this->request->param('poi_id', '', 'trim');
                    $poiName   = $this->request->param('poi_name', '', 'trim');
                    $longitude = $this->request->param('longitude', 0, 'floatval');
                    $latitude  = $this->request->param('latitude', 0, 'floatval');
                    
                    $result = $this->addPoi($key, $poiId, $poiName, $longitude, $latitude);
                    break;

                case 'route':
                default:
                    // 路径规划（查找从起点到终点途径的POI）
                    $startLon = $this->request->param('start_lon', 0, 'floatval');
                    $startLat = $this->request->param('start_lat', 0, 'floatval');
                    $endLon   = $this->request->param('end_lon', 0, 'floatval');
                    $endLat   = $this->request->param('end_lat', 0, 'floatval');
                    $radius   = $this->request->param('radius', 1, 'floatval');
                    
                    // 确保半径为正数
                    $radius = max(0.1, $radius);
                    
                    // 验证经纬度范围
                    $validCoords = true;
                    if ($startLon && $startLat && $endLon && $endLat) {
                        $validCoords = $this->isValidCoordinates($startLon, $startLat) && 
                                       $this->isValidCoordinates($endLon, $endLat);
                    }

                    if (!$validCoords && ($startLon || $startLat || $endLon || $endLat)) {
                        return $this->error('经纬度范围无效');
                    }

                    // 如果没有指定坐标或数据为空，初始化示例数据
                    if (!$startLon || !$startLat || !$endLon || !$endLat || Redis::zset()->zCard($key) == 0) {
                        // 初始化示例POI数据
                        $this->initSamplePois($key);
                        
                        // 使用默认的起点和终点
                        $startLon = 116.46; // 国贸
                        $startLat = 39.91;
                        $endLon   = 116.30; // 公主坟
                        $endLat   = 39.90;
                    }
                    
                    $result = $this->calculateRoute($key, $startLon, $startLat, $endLon, $endLat, $radius);
                    break;
            }

            return $this->success('路径规划操作成功', $result);
        } catch (\Throwable $e) {
            return $this->error('路径规划操作失败：' . $e->getMessage());
        }
    }
}