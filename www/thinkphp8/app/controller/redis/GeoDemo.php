<?php
declare(strict_types=1);

namespace app\controller\redis;

use app\controller\RedisDemo;
use app\facade\Redis;
use think\facade\Log;
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
        Log::info('访问Geo演示页面');
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
        $validCount = max($min, min($max, $count));
        if ($validCount !== $count) {
            Log::debug('计数限制调整，原计数: {original}, 调整后计数: {adjusted}, 最小值: {min}, 最大值: {max}', [
                'original' => $count,
                'adjusted' => $validCount,
                'min'      => $min,
                'max'      => $max
            ]);
        }
        return $validCount;
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
        Log::debug('生成搜索选项，count: {count}, withCoord: {withCoord}', [
            'count'     => $count,
            'withCoord' => $withCoord ? 'true' : 'false'
        ]);

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
        Log::debug('计算两点之间距离，起点: [{startLon}, {startLat}], 终点: [{endLon}, {endLat}]', [
            'startLon' => $startLon,
            'startLat' => $startLat,
            'endLon'   => $endLon,
            'endLat'   => $endLat
        ]);

        $earthRadius = 6371; // 地球半径，单位公里
        $dLat        = deg2rad($endLat - $startLat);
        $dLon        = deg2rad($endLon - $startLon);
        $a           = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($startLat)) * cos(deg2rad($endLat)) *
            sin($dLon / 2) * sin($dLon / 2);
        $c           = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distance    = $earthRadius * $c;

        Log::debug('距离计算结果: {distance} km', ['distance' => $distance]);
        return $distance;
    }

    /**
     * 基本用法示例
     *
     * @return Response
     */
    public function basic()
    {
        try {
            Log::info('执行Geo基本用法示例');

            $redis = $this->getRedisGeo();
            $key   = 'geo_demo_cities';

            // 清空之前的测试数据
            $redis->delete($key);
            Log::debug('清空Geo测试数据，key: {key}', ['key' => $key]);

            // 添加城市地理位置
            $addResults = [];
            foreach ($this->cities as $city => $location) {
                $addResults[$city] = $redis->geoAdd(
                    $key,
                    (float) $location[0],
                    (float) $location[1],
                    $city
                );
                Log::debug('添加城市地理位置，city: {city}, longitude: {longitude}, latitude: {latitude}, result: {result}', [
                    'city'      => $city,
                    'longitude' => $location[0],
                    'latitude'  => $location[1],
                    'result'    => $addResults[$city]
                ]);
            }

            // 获取数据
            $beijingPos = $redis->geoPos($key, 'beijing');
            Log::debug('获取北京位置，result: {result}', ['result' => json_encode($beijingPos)]);

            $distBjToSh = $redis->geoDist($key, 'beijing', 'shanghai', 'km');
            Log::debug('获取北京到上海距离，distance: {distance} km', ['distance' => $distBjToSh]);

            $distHzToSh = $redis->geoDist($key, 'hangzhou', 'shanghai', 'km');
            Log::debug('获取杭州到上海距离，distance: {distance} km', ['distance' => $distHzToSh]);

            $citiesNearSh = $redis->geoRadius(
                $key,
                121.472644,
                31.231706,
                300,
                'km',
                $this->getSearchOptions(10, true)
            );
            Log::debug('获取上海附近城市，radius: {radius} km, count: {count}', [
                'radius' => 300,
                'count'  => count($citiesNearSh)
            ]);

            $bjGeohash = $redis->geoHash($key, 'beijing');
            Log::debug('获取北京Geohash，hash: {hash}', ['hash' => $bjGeohash]);

            $cityHashes = $redis->geoBatchHash($key, ['beijing', 'shanghai', 'guangzhou']);
            Log::debug('获取多个城市Geohash', []);

            $data = [
                'add_results'                   => $addResults,
                'beijing_position'              => $beijingPos,
                'distance_beijing_to_shanghai'  => $distBjToSh,
                'distance_hangzhou_to_shanghai' => $distHzToSh,
                'cities_near_shanghai'          => $citiesNearSh,
                'beijing_geohash'               => $bjGeohash,
                'city_geohashes'                => $cityHashes,
            ];

            Log::info('Geo基本用法演示成功');
            return $this->success('Geo基本用法演示成功', $data);
        } catch (\Throwable $e) {
            Log::error('Geo基本用法演示失败，error: {error}, trace: {trace}', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
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
        Log::debug('添加用户位置，key: {key}, userId: {userId}, longitude: {longitude}, latitude: {latitude}', [
            'key'       => $key,
            'userId'    => $userId,
            'longitude' => $longitude,
            'latitude'  => $latitude
        ]);

        if (!$this->isValidCoordinates($longitude, $latitude)) {
            Log::warning('添加用户位置失败，经纬度范围无效，userId: {userId}, longitude: {longitude}, latitude: {latitude}', [
                'userId'    => $userId,
                'longitude' => $longitude,
                'latitude'  => $latitude
            ]);

            return [
                'status'  => 'error',
                'message' => '经纬度范围无效'
            ];
        }

        if ($userId <= 0 || !$longitude || !$latitude) {
            Log::warning('添加用户位置失败，用户ID和位置信息不能为空，userId: {userId}', ['userId' => $userId]);

            return [
                'status'  => 'error',
                'message' => '用户ID和位置信息不能为空'
            ];
        }

        $redis  = $this->getRedisGeo();
        $result = $redis->geoAdd($key, (float) $longitude, (float) $latitude, "user:{$userId}");

        Log::info('用户位置添加成功，userId: {userId}, longitude: {longitude}, latitude: {latitude}, result: {result}', [
            'userId'    => $userId,
            'longitude' => $longitude,
            'latitude'  => $latitude,
            'result'    => $result
        ]);

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
            $userData   = [
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
        Log::debug('根据用户ID查找附近用户，key: {key}, userId: {userId}, radius: {radius}, unit: {unit}, count: {count}', [
            'key'    => $key,
            'userId' => $userId,
            'radius' => $radius,
            'unit'   => $unit,
            'count'  => $count
        ]);

        if ($radius <= 0) {
            Log::warning('查找附近用户失败，搜索半径必须大于0，userId: {userId}, radius: {radius}', [
                'userId' => $userId,
                'radius' => $radius
            ]);

            return [
                'status'  => 'error',
                'message' => '搜索半径必须大于0'
            ];
        }

        $unit    = $this->getValidUnit($unit);
        $count   = $this->getValidCount($count);
        $userKey = "user:{$userId}";
        $redis   = $this->getRedisGeo();

        $nearby = $redis->geoRadiusByMember(
            $key,
            $userKey,
            $radius,
            $unit,
            $this->getSearchOptions($count)
        );

        Log::debug('获取附近用户列表，原始结果数量: {count}', ['count' => count($nearby)]);

        $formattedNearby = $this->formatNearbyUsers($nearby, $userKey, $unit);

        Log::info('根据用户ID查找附近用户成功，userId: {userId}, 找到用户数: {count}', [
            'userId' => $userId,
            'count'  => count($formattedNearby)
        ]);

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
        Log::debug('根据坐标查找附近用户，key: {key}, longitude: {longitude}, latitude: {latitude}, radius: {radius}, unit: {unit}, count: {count}', [
            'key'       => $key,
            'longitude' => $longitude,
            'latitude'  => $latitude,
            'radius'    => $radius,
            'unit'      => $unit,
            'count'     => $count
        ]);

        if (!$this->isValidCoordinates($longitude, $latitude)) {
            Log::warning('根据坐标查找附近用户失败，经纬度范围无效，longitude: {longitude}, latitude: {latitude}', [
                'longitude' => $longitude,
                'latitude'  => $latitude
            ]);

            return [
                'status'  => 'error',
                'message' => '经纬度范围无效'
            ];
        }

        if ($radius <= 0) {
            Log::warning('根据坐标查找附近用户失败，搜索半径必须大于0，radius: {radius}', ['radius' => $radius]);

            return [
                'status'  => 'error',
                'message' => '搜索半径必须大于0'
            ];
        }

        if (!$longitude || !$latitude) {
            Log::warning('根据坐标查找附近用户失败，位置信息不能为空');

            return [
                'status'  => 'error',
                'message' => '位置信息不能为空'
            ];
        }

        $unit  = $this->getValidUnit($unit);
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

        Log::debug('获取附近用户列表，原始结果数量: {count}', ['count' => count($nearby)]);

        $formattedNearby = $this->formatNearbyUsers($nearby, '', $unit, true);

        Log::info('根据坐标查找附近用户成功，找到用户数: {count}', [
            'count' => count($formattedNearby)
        ]);

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
        Log::debug('获取所有用户位置列表，key: {key}', ['key' => $key]);

        $redis = $this->getRedisGeo();
        $count = Redis::zset()->zCard($key); // GEO实际上是使用有序集合实现的
        $users = [];

        if ($count > 0) {
            Log::debug('用户位置数据存在，总数: {count}', ['count' => $count]);
            $members = Redis::zset()->zRange($key, 0, -1);

            if (is_array($members)) {
                foreach ($members as $member) {
                    if (empty($member)) {
                        Log::warning('跳过空成员数据');
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
                        Log::debug('获取用户位置信息，userId: {userId}, longitude: {longitude}, latitude: {latitude}', [
                            'userId'    => $userId,
                            'longitude' => $position[0] ?? 0,
                            'latitude'  => $position[1] ?? 0
                        ]);
                    } else {
                        Log::warning('获取用户位置失败，userId: {userId}', ['userId' => $userId]);
                    }
                }
            }
        } else {
            Log::info('用户位置数据为空');
        }

        Log::info('获取用户位置列表完成，总用户数: {count}', ['count' => count($users)]);
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
            Log::info('访问附近的人示例');

            $action = $this->request->param('action', 'list');
            $key    = 'geo_demo_users';

            Log::debug('附近的人操作，action: {action}, key: {key}', [
                'action' => $action,
                'key'    => $key
            ]);

            switch ($action) {
                case 'add':
                    // 添加用户位置
                    $userId = $this->request->param('user_id', 0, 'intval');
                    $longitude = $this->request->param('longitude', 0, 'floatval');
                    $latitude = $this->request->param('latitude', 0, 'floatval');

                    Log::debug('添加用户位置请求，userId: {userId}, longitude: {longitude}, latitude: {latitude}', [
                        'userId'    => $userId,
                        'longitude' => $longitude,
                        'latitude'  => $latitude
                    ]);

                    $result = $this->addUserLocation($key, $userId, $longitude, $latitude);
                    break;

                case 'nearby':
                    // 查找附近的人
                    $userId = $this->request->param('user_id', 0, 'intval');
                    $radius = $this->request->param('radius', 5, 'floatval');
                    $unit = $this->request->param('unit', 'km', 'trim');
                    $count = $this->request->param('count', 10, 'intval');

                    Log::debug('查找附近的人请求，userId: {userId}, radius: {radius}, unit: {unit}, count: {count}', [
                        'userId' => $userId,
                        'radius' => $radius,
                        'unit'   => $unit,
                        'count'  => $count
                    ]);

                    if ($userId > 0) {
                        // 根据用户ID查找附近的人
                        Log::debug('根据用户ID查找附近的人');
                        $result = $this->findNearbyUsersByUserId($key, $userId, $radius, $unit, $count);
                    } else {
                        // 根据坐标查找附近的人
                        $longitude = $this->request->param('longitude', 0, 'floatval');
                        $latitude  = $this->request->param('latitude', 0, 'floatval');

                        Log::debug('根据坐标查找附近的人，longitude: {longitude}, latitude: {latitude}', [
                            'longitude' => $longitude,
                            'latitude'  => $latitude
                        ]);

                        $result = $this->findNearbyUsersByCoordinates($key, $longitude, $latitude, $radius, $unit, $count);
                    }
                    break;

                case 'list':
                default:
                    // 列出所有用户位置
                    Log::debug('列出所有用户位置');
                    $result = $this->listAllUserLocations($key);
                    break;
            }

            Log::info('附近的人操作成功完成，action: {action}', ['action' => $action]);
            return $this->success('附近的人操作成功', $result);
        } catch (\Throwable $e) {
            Log::error('附近的人操作失败，error: {error}, trace: {trace}', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
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
        Log::debug('添加店铺位置，key: {key}, storeId: {storeId}, storeName: {storeName}, longitude: {longitude}, latitude: {latitude}', [
            'key'       => $key,
            'storeId'   => $storeId,
            'storeName' => $storeName,
            'longitude' => $longitude,
            'latitude'  => $latitude
        ]);

        if (!$this->isValidCoordinates($longitude, $latitude)) {
            Log::warning('添加店铺位置失败，经纬度范围无效，storeId: {storeId}, longitude: {longitude}, latitude: {latitude}', [
                'storeId'   => $storeId,
                'longitude' => $longitude,
                'latitude'  => $latitude
            ]);

            return [
                'status'  => 'error',
                'message' => '经纬度范围无效'
            ];
        }

        if ($storeId <= 0 || empty($storeName) || !$longitude || !$latitude) {
            Log::warning('添加店铺位置失败，店铺信息不完整，storeId: {storeId}, storeName: {storeName}', [
                'storeId'   => $storeId,
                'storeName' => $storeName
            ]);

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

        Log::debug('店铺信息已存储到Hash，storeId: {storeId}', ['storeId' => $storeId]);

        // 添加到地理位置索引
        $result = $this->getRedisGeo()->geoAdd($key, (float) $longitude, (float) $latitude, "store:{$storeId}");

        Log::info('店铺位置添加成功，storeId: {storeId}, storeName: {storeName}, result: {result}', [
            'storeId'   => $storeId,
            'storeName' => $storeName,
            'result'    => $result
        ]);

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
        Log::debug('搜索附近店铺，key: {key}, longitude: {longitude}, latitude: {latitude}, radius: {radius}, unit: {unit}, count: {count}', [
            'key'       => $key,
            'longitude' => $longitude,
            'latitude'  => $latitude,
            'radius'    => $radius,
            'unit'      => $unit,
            'count'     => $count
        ]);

        if (!$this->isValidCoordinates($longitude, $latitude)) {
            Log::warning('搜索附近店铺失败，经纬度范围无效，longitude: {longitude}, latitude: {latitude}', [
                'longitude' => $longitude,
                'latitude'  => $latitude
            ]);

            return [
                'status'  => 'error',
                'message' => '经纬度范围无效'
            ];
        }

        if ($radius <= 0) {
            Log::warning('搜索附近店铺失败，搜索半径必须大于0，radius: {radius}', ['radius' => $radius]);

            return [
                'status'  => 'error',
                'message' => '搜索半径必须大于0'
            ];
        }

        if (!$longitude || !$latitude) {
            Log::warning('搜索附近店铺失败，位置信息不能为空');

            return [
                'status'  => 'error',
                'message' => '位置信息不能为空'
            ];
        }

        $unit  = $this->getValidUnit($unit);
        $count = $this->getValidCount($count);
        $redis = $this->getRedisGeo();

        // 确保搜索半径不小于0.01公里（10米），以应对精度问题
        if ($unit == 'km' && $radius < 0.01) {
            $radius = 0.01;
            Log::debug('搜索半径太小，自动调整为: {radius} km', ['radius' => $radius]);
        } elseif ($unit == 'm' && $radius < 10) {
            $radius = 10;
            Log::debug('搜索半径太小，自动调整为: {radius} m', ['radius' => $radius]);
        }

        // 记录所有店铺的位置，用于调试
        $allStores = $this->listAllStores($key);
        Log::debug('当前所有店铺: {stores}', ['stores' => json_encode($allStores['stores'])]);

        // 使用 geoRadius 查询附近的店铺
        $options = [
            'withdist' => true,  // 返回距离
            'count'    => $count,
            'asc'      => true,  // 按距离升序排序
        ];
        
        $nearbyStores = $redis->geoRadius(
            $key,
            $longitude,
            $latitude,
            $radius,
            $unit,
            $options
        );
        
        Log::debug('获取附近店铺列表，原始结果数量: {count}，结果: {result}', [
            'count'  => count($nearbyStores),
            'result' => json_encode($nearbyStores)
        ]);

        // 格式化结果
        $formattedStores = [];
        
        // 如果 geoRadius 返回的不是预期的格式，则手动计算距离
        if (!empty($nearbyStores)) {
            foreach ($nearbyStores as $item) {
                // 处理不同的返回格式
                if (is_array($item) && count($item) >= 2) {
                    // 标准格式：[member, distance]
                    $member = $item[0];
                    $distance = $item[1];
                } else {
                    // 只返回了 member，需要手动计算距离
                    $member = $item;
                    $distance = null;
                }
                
                if (empty($member)) {
                    Log::warning('店铺标识为空，跳过处理');
                    continue;
                }
                
                $storeId = str_replace('store:', '', $member);
                $storeInfo = Redis::hash()->hGetAll("store:{$storeId}");
                
                if ($storeInfo && !empty($storeInfo)) {
                    // 如果没有距离信息，手动计算
                    if ($distance === null && isset($storeInfo['longitude']) && isset($storeInfo['latitude'])) {
                        $storeLon = (float)$storeInfo['longitude'];
                        $storeLat = (float)$storeInfo['latitude'];
                        $distance = $this->calculateDistance($longitude, $latitude, $storeLon, $storeLat);
                        
                        // 如果单位是米，转换一下
                        if ($unit === 'm') {
                            $distance = $distance * 1000;
                        }
                        
                        Log::debug('手动计算店铺距离，storeId: {storeId}, distance: {distance} {unit}', [
                            'storeId'  => $storeId,
                            'distance' => $distance,
                            'unit'     => $unit
                        ]);
                    }
                    
                    $storeInfo['distance'] = $distance;
                    $storeInfo['unit'] = $unit;
                    $formattedStores[] = $storeInfo;
                    
                    Log::debug('获取店铺信息，storeId: {storeId}, name: {name}, distance: {distance} {unit}', [
                        'storeId'  => $storeId,
                        'name'     => $storeInfo['name'] ?? '',
                        'distance' => $distance,
                        'unit'     => $unit
                    ]);
                } else {
                    Log::warning('无法获取店铺信息，storeId: {storeId}', ['storeId' => $storeId]);
                }
            }
        }
        
        // 按距离排序
        usort($formattedStores, function($a, $b) {
            return $a['distance'] <=> $b['distance'];
        });

        // 如果没有找到店铺，尝试使用更大的半径重新搜索
        if (empty($formattedStores) && $radius < 10 && $unit == 'km') {
            Log::info('未找到店铺，尝试使用更大半径重新搜索');
            return $this->searchNearbyStores($key, $longitude, $latitude, 10, $unit, $count);
        }

        Log::info('搜索附近店铺成功，找到店铺数: {count}', ['count' => count($formattedStores)]);

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
        Log::debug('获取所有店铺列表，key: {key}', ['key' => $key]);

        $redis  = $this->getRedisGeo();
        $count  = Redis::zset()->zCard($key); // GEO实际上是使用有序集合实现的
        $stores = [];

        Log::debug('店铺数据数量: {count}', ['count' => $count]);

        if ($count > 0) {
            $members = Redis::zset()->zRange($key, 0, -1);

            if (is_array($members)) {
                foreach ($members as $member) {
                    if (empty($member)) {
                        Log::warning('跳过空店铺数据');
                        continue; // 跳过无效数据
                    }

                    $storeId   = str_replace('store:', '', $member);
                    $storeInfo = Redis::hash()->hGetAll("store:{$storeId}");

                    if ($storeInfo && !empty($storeInfo)) {
                        $stores[] = $storeInfo;
                        Log::debug('获取店铺信息，storeId: {storeId}, name: {name}', [
                            'storeId' => $storeId,
                            'name'    => $storeInfo['name'] ?? ''
                        ]);
                    } else {
                        Log::warning('无法获取店铺信息，storeId: {storeId}', ['storeId' => $storeId]);
                    }
                }
            }
        } else {
            Log::info('店铺数据为空，添加示例数据');
            // 预设一些示例店铺
            $sampleStores = [
                ['id' => 1, 'name' => '星巴克(国贸店)', 'longitude' => 116.46, 'latitude' => 39.91],
                ['id' => 2, 'name' => '肯德基(王府井店)', 'longitude' => 116.41, 'latitude' => 39.92],
                ['id' => 3, 'name' => '麦当劳(东单店)', 'longitude' => 116.42, 'latitude' => 39.91],
                ['id' => 4, 'name' => '必胜客(西单店)', 'longitude' => 116.37, 'latitude' => 39.91],
                ['id' => 5, 'name' => '星巴克(中关村店)', 'longitude' => 116.32, 'latitude' => 39.98],
                ['id' => 7, 'name' => '华莱士(王府井店)', 'longitude' => 116.41, 'latitude' => 39.92],
            ];

            foreach ($sampleStores as $store) {
                // 将店铺信息存储到Hash中
                Redis::hash()->hMSet("store:{$store['id']}", array_merge($store, ['create_time' => time()]));

                Log::debug('添加示例店铺，storeId: {storeId}, name: {name}, longitude: {longitude}, latitude: {latitude}', [
                    'storeId'   => $store['id'],
                    'name'      => $store['name'],
                    'longitude' => $store['longitude'],
                    'latitude'  => $store['latitude']
                ]);

                // 添加到地理位置索引
                $redis->geoAdd($key, (float) $store['longitude'], (float) $store['latitude'], "store:{$store['id']}");

                $stores[] = array_merge($store, ['create_time' => time()]);
            }
        }

        Log::info('获取店铺列表完成，总店铺数: {count}', ['count' => count($stores)]);

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
            Log::info('访问店铺查找示例');

            $action = $this->request->param('action', 'list');
            $key    = 'geo_demo_stores';

            Log::debug('店铺查找操作，action: {action}, key: {key}', [
                'action' => $action,
                'key'    => $key
            ]);

            switch ($action) {
                case 'add':
                    // 添加店铺
                    $storeId = $this->request->param('store_id', 0, 'intval');
                    $storeName = $this->request->param('store_name', '', 'trim');
                    $longitude = $this->request->param('longitude', 0, 'floatval');
                    $latitude = $this->request->param('latitude', 0, 'floatval');

                    Log::debug('添加店铺请求，storeId: {storeId}, storeName: {storeName}, longitude: {longitude}, latitude: {latitude}', [
                        'storeId'   => $storeId,
                        'storeName' => $storeName,
                        'longitude' => $longitude,
                        'latitude'  => $latitude
                    ]);

                    $result = $this->addStoreLocation($key, $storeId, $storeName, $longitude, $latitude);
                    break;

                case 'search':
                    // 查找附近的店铺
                    $longitude = $this->request->param('longitude', 0, 'floatval');
                    $latitude = $this->request->param('latitude', 0, 'floatval');
                    $radius = $this->request->param('radius', 5, 'floatval');
                    $unit = $this->request->param('unit', 'km', 'trim');
                    $count = $this->request->param('count', 10, 'intval');

                    Log::debug('查找附近店铺请求，longitude: {longitude}, latitude: {latitude}, radius: {radius}, unit: {unit}, count: {count}', [
                        'longitude' => $longitude,
                        'latitude'  => $latitude,
                        'radius'    => $radius,
                        'unit'      => $unit,
                        'count'     => $count
                    ]);

                    //执行搜索
                    $result = $this->searchNearbyStores($key, $longitude, $latitude, $radius, $unit, $count);
                    break;

                case 'debug':
                    // 调试特定经纬度的查询问题
                    Log::debug('执行店铺查询调试');

                    // 清空现有数据
                    $redis = $this->getRedisGeo();
                    $redis->delete($key);

                    // 添加一个测试店铺，经纬度与查询完全一致
                    $testStore = ['id' => 7, 'name' => '测试店铺', 'longitude' => 116.41, 'latitude' => 39.92];
                    Redis::hash()->hMSet("store:{$testStore['id']}", array_merge($testStore, ['create_time' => time()]));
                    $redis->geoAdd($key, (float) $testStore['longitude'], (float) $testStore['latitude'], "store:{$testStore['id']}");

                    Log::debug('添加测试店铺，经纬度: [{longitude}, {latitude}]', [
                        'longitude' => $testStore['longitude'],
                        'latitude'  => $testStore['latitude']
                    ]);

                    // 使用完全相同的经纬度进行查询
                    $searchResult = $redis->geoRadius(
                        $key,
                        (float) $testStore['longitude'],
                        (float) $testStore['latitude'],
                        0.1, // 很小的半径
                        'km',
                        ['withdist' => true]
                    );

                    Log::debug('使用完全相同经纬度查询结果: {result}', ['result' => json_encode($searchResult)]);

                    // 使用稍微不同的经纬度进行查询
                    $searchResult2 = $redis->geoRadius(
                        $key,
                        (float) $testStore['longitude'] + 0.0001,
                        (float) $testStore['latitude'] + 0.0001,
                        0.1,
                        'km',
                        ['withdist' => true]
                    );

                    Log::debug('使用稍微不同经纬度查询结果: {result}', ['result' => json_encode($searchResult2)]);

                    // 使用更大半径查询
                    $searchResult3 = $redis->geoRadius(
                        $key,
                        (float) $testStore['longitude'],
                        (float) $testStore['latitude'],
                        1,
                        'km',
                        ['withdist' => true]
                    );

                    Log::debug('使用更大半径查询结果: {result}', ['result' => json_encode($searchResult3)]);

                    $result = [
                        'status'               => 'success',
                        'test_store'           => $testStore,
                        'exact_match_result'   => $searchResult,
                        'slight_diff_result'   => $searchResult2,
                        'larger_radius_result' => $searchResult3
                    ];
                    break;

                case 'list':
                default:
                    // 列出所有店铺
                    Log::debug('列出所有店铺');
                    $result = $this->listAllStores($key);
                    break;
            }

            Log::info('店铺查找操作成功完成，action: {action}', ['action' => $action]);
            return $this->success('店铺查找操作成功', $result);
        } catch (\Throwable $e) {
            Log::error('店铺查找操作失败，error: {error}, trace: {trace}', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
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
        Log::debug('添加兴趣点，key: {key}, poiId: {poiId}, poiName: {poiName}, longitude: {longitude}, latitude: {latitude}', [
            'key'       => $key,
            'poiId'     => $poiId,
            'poiName'   => $poiName,
            'longitude' => $longitude,
            'latitude'  => $latitude
        ]);

        if (!$this->isValidCoordinates($longitude, $latitude)) {
            Log::warning('添加兴趣点失败，经纬度范围无效，poiId: {poiId}, longitude: {longitude}, latitude: {latitude}', [
                'poiId'     => $poiId,
                'longitude' => $longitude,
                'latitude'  => $latitude
            ]);

            return [
                'status'  => 'error',
                'message' => '经纬度范围无效'
            ];
        }

        if (empty($poiId) || empty($poiName) || !$longitude || !$latitude) {
            Log::warning('添加兴趣点失败，兴趣点信息不完整，poiId: {poiId}, poiName: {poiName}', [
                'poiId'   => $poiId,
                'poiName' => $poiName
            ]);

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

        Log::debug('兴趣点信息已存储到Hash，poiId: {poiId}', ['poiId' => $poiId]);

        // 添加到地理位置索引
        $result = $this->getRedisGeo()->geoAdd($key, (float) $longitude, (float) $latitude, "poi:{$poiId}");

        Log::info('兴趣点添加成功，poiId: {poiId}, poiName: {poiName}, result: {result}', [
            'poiId'   => $poiId,
            'poiName' => $poiName,
            'result'  => $result
        ]);

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
        Log::debug('初始化示例POI数据，key: {key}', ['key' => $key]);

        $redis      = $this->getRedisGeo();
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

        Log::debug('添加 {count} 个示例POI数据', ['count' => count($samplePOIs)]);

        foreach ($samplePOIs as $poi) {
            // 将POI信息存储到Hash中
            Redis::hash()->hMSet("poi:{$poi['id']}", $poi);

            Log::debug('添加示例POI，id: {id}, name: {name}, longitude: {longitude}, latitude: {latitude}', [
                'id'        => $poi['id'],
                'name'      => $poi['name'],
                'longitude' => $poi['longitude'],
                'latitude'  => $poi['latitude']
            ]);

            // 添加到地理位置索引
            $redis->geoAdd($key, (float) $poi['longitude'], (float) $poi['latitude'], "poi:{$poi['id']}");
        }

        Log::info('示例POI数据初始化完成，共添加 {count} 个POI', ['count' => count($samplePOIs)]);
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
        Log::debug('开始路径规划计算，key: {key}, 起点: [{startLon}, {startLat}], 终点: [{endLon}, {endLat}], 半径: {radius} km', [
            'key'      => $key,
            'startLon' => $startLon,
            'startLat' => $startLat,
            'endLon'   => $endLon,
            'endLat'   => $endLat,
            'radius'   => $radius
        ]);

        $redis = $this->getRedisGeo();

        // 计算两点间距离
        $distance = $this->calculateDistance($startLon, $startLat, $endLon, $endLat);
        Log::debug('两点间直线距离: {distance} km', ['distance' => round($distance, 2)]);

        // 生成路径上的一系列点
        $points = [];
        $steps  = max(5, ceil($distance / max(0.1, $radius))); // 至少生成5个点

        Log::debug('生成路径点，步骤数: {steps}', ['steps' => $steps]);

        for ($i = 0; $i <= $steps; $i++) {
            $t        = $i / $steps;
            $lon      = $startLon + $t * ($endLon - $startLon);
            $lat      = $startLat + $t * ($endLat - $startLat);
            $points[] = [$lon, $lat];
        }

        // 查询每个点附近的POI
        $routePOIs   = [];
        $visitedPOIs = [];

        Log::debug('开始查询路径上每个点附近的POI');
        $pointCount = count($points);

        foreach ($points as $index => $point) {
            $lon = $point[0];
            $lat = $point[1];

            Log::debug('查询路径点 {index}/{total} 附近POI, 坐标: [{lon}, {lat}]', [
                'index' => $index + 1,
                'total' => $pointCount,
                'lon'   => $lon,
                'lat'   => $lat
            ]);

            $nearbyPOIs = $redis->geoRadius($key, $lon, $lat, $radius, 'km', [
                'withdist' => true,
                'asc'      => true,
            ]);

            Log::debug('路径点 {index}/{total} 找到 {count} 个POI', [
                'index' => $index + 1,
                'total' => $pointCount,
                'count' => count($nearbyPOIs)
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

                        Log::debug('添加路径上POI，id: {id}, name: {name}, distance: {distance} km', [
                            'id'       => $poiId,
                            'name'     => $poiInfo['name'] ?? '',
                            'distance' => $distance
                        ]);
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

        Log::info('路径规划计算完成，总距离: {distance} km, 找到POI: {count} 个', [
            'distance' => round($distance, 2),
            'count'    => count($routePOIs)
        ]);

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
            Log::info('访问路径规划示例');

            $action = $this->request->param('action', 'route');
            $key    = 'geo_demo_locations';

            Log::debug('路径规划操作，action: {action}, key: {key}', [
                'action' => $action,
                'key'    => $key
            ]);

            switch ($action) {
                case 'add_poi':
                    // 添加兴趣点
                    $poiId = $this->request->param('poi_id', '', 'trim');
                    $poiName = $this->request->param('poi_name', '', 'trim');
                    $longitude = $this->request->param('longitude', 0, 'floatval');
                    $latitude = $this->request->param('latitude', 0, 'floatval');

                    Log::debug('添加兴趣点请求，poiId: {poiId}, poiName: {poiName}, longitude: {longitude}, latitude: {latitude}', [
                        'poiId'     => $poiId,
                        'poiName'   => $poiName,
                        'longitude' => $longitude,
                        'latitude'  => $latitude
                    ]);

                    $result = $this->addPoi($key, $poiId, $poiName, $longitude, $latitude);
                    break;

                case 'route':
                default:
                    // 路径规划（查找从起点到终点途径的POI）
                    $startLon = $this->request->param('start_lon', 0, 'floatval');
                    $startLat = $this->request->param('start_lat', 0, 'floatval');
                    $endLon = $this->request->param('end_lon', 0, 'floatval');
                    $endLat = $this->request->param('end_lat', 0, 'floatval');
                    $radius = $this->request->param('radius', 1, 'floatval');

                    Log::debug('路径规划请求，startLon: {startLon}, startLat: {startLat}, endLon: {endLon}, endLat: {endLat}, radius: {radius}', [
                        'startLon' => $startLon,
                        'startLat' => $startLat,
                        'endLon'   => $endLon,
                        'endLat'   => $endLat,
                        'radius'   => $radius
                    ]);

                    // 确保半径为正数
                    $radius = max(0.1, $radius);

                    // 验证经纬度范围
                    $validCoords = true;
                    if ($startLon && $startLat && $endLon && $endLat) {
                        $validCoords = $this->isValidCoordinates($startLon, $startLat) &&
                            $this->isValidCoordinates($endLon, $endLat);
                    }

                    if (!$validCoords && ($startLon || $startLat || $endLon || $endLat)) {
                        Log::warning('路径规划失败，经纬度范围无效，startLon: {startLon}, startLat: {startLat}, endLon: {endLon}, endLat: {endLat}', [
                            'startLon' => $startLon,
                            'startLat' => $startLat,
                            'endLon'   => $endLon,
                            'endLat'   => $endLat
                        ]);

                        return $this->error('经纬度范围无效');
                    }

                    // 如果没有指定坐标或数据为空，初始化示例数据
                    if (!$startLon || !$startLat || !$endLon || !$endLat || Redis::zset()->zCard($key) == 0) {
                        Log::info('未指定坐标或数据为空，初始化示例POI数据');

                        // 初始化示例POI数据
                        $this->initSamplePois($key);

                        // 使用默认的起点和终点
                        $startLon = 116.46; // 国贸
                        $startLat = 39.91;
                        $endLon   = 116.30; // 公主坟
                        $endLat   = 39.90;

                        Log::debug('使用默认坐标，startLon: {startLon}, startLat: {startLat}, endLon: {endLon}, endLat: {endLat}', [
                            'startLon' => $startLon,
                            'startLat' => $startLat,
                            'endLon'   => $endLon,
                            'endLat'   => $endLat
                        ]);
                    }

                    $result = $this->calculateRoute($key, $startLon, $startLat, $endLon, $endLat, $radius);
                    break;
            }

            Log::info('路径规划操作成功完成，action: {action}', ['action' => $action]);
            return $this->success('路径规划操作成功', $result);
        } catch (\Throwable $e) {
            Log::error('路径规划操作失败，error: {error}, trace: {trace}', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('路径规划操作失败：' . $e->getMessage());
        }
    }
}