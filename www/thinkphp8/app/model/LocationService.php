<?php
declare(strict_types=1);

namespace app\model;

use think\Model;
use think\facade\Log;
use think\exception\DbException;

/**
 * 位置服务模型 - 地理空间支持特性
 * 支持地理空间索引和LBS应用
 */
class LocationService extends Model
{
    // 设置MongoDB连接
    protected $connection = 'mongo';

    // 设置集合名称
    protected $table = 'location_services';

    // 设置主键
    protected $pk = '_id';

    // 自动时间戳
    protected $autoWriteTimestamp = true;

    // JSON字段
    protected $json = ['location', 'properties', 'metadata'];

    /**
     * 创建地理位置点
     * 
     * @param array $data 位置数据
     * @return array|false
     */
    public static function createLocation(array $data)
    {
        try {
            Log::info('创建地理位置点: {name}, {type}', [
                'name' => $data['name'] ?? 'unknown',
                'type' => $data['type'] ?? 'unknown'
            ]);

            // 验证经纬度
            if (!isset($data['longitude']) || !isset($data['latitude'])) {
                throw new \Exception('经纬度不能为空');
            }

            $longitude = (float) $data['longitude'];
            $latitude  = (float) $data['latitude'];

            // 验证经纬度范围
            if ($longitude < -180 || $longitude > 180) {
                throw new \Exception('经度范围必须在-180到180之间');
            }
            if ($latitude < -90 || $latitude > 90) {
                throw new \Exception('纬度范围必须在-90到90之间');
            }

            // 构建GeoJSON格式的位置数据
            $locationData = [
                'name'       => $data['name'] ?? '',
                'type'       => $data['type'] ?? 'point',
                'location'   => [
                    'type'        => 'Point',
                    'coordinates' => [$longitude, $latitude] // MongoDB中经度在前，纬度在后
                ],
                'address'    => $data['address'] ?? '',
                'properties' => $data['properties'] ?? [],
                'metadata'   => $data['metadata'] ?? [],
                'created_at' => time(),
                'updated_at' => time()
            ];

            $location = self::create($locationData);

            if ($location) {
                Log::info('地理位置点创建成功: {location_id}, 坐标: {coordinates}', [
                    'location_id' => $location['_id'],
                    'coordinates' => [$longitude, $latitude]
                ]);
                return $locationData;
            }

            return false;

        } catch (\Exception $e) {
            Log::error('创建地理位置点失败: {error}', [
                'data'  => json_encode($data),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * 查找附近的位置点
     * 
     * @param float $longitude 经度
     * @param float $latitude 纬度
     * @param int $maxDistance 最大距离（米）
     * @param int $limit 限制数量
     * @param string $type 位置类型
     * @return array
     */
    public static function findNearby(float $longitude, float $latitude, int $maxDistance = 1000, int $limit = 20, string $type = ''): array
    {
        try {
            Log::info('查找附近位置点: {center}, 最大距离: {max_distance}米, 限制: {limit}, 类型: {type}', [
                'center'       => json_encode([$longitude, $latitude]),
                'max_distance' => $maxDistance,
                'limit'        => $limit,
                'type'         => $type
            ]);

            // 验证经纬度
            if ($longitude < -180 || $longitude > 180 || $latitude < -90 || $latitude > 90) {
                throw new \Exception('经纬度参数无效');
            }

            $query = self::name('location_services');

            // 添加类型过滤
            if (!empty($type)) {
                $query->where('type', $type);
            }

            // 注意：ThinkPHP的MongoDB驱动可能不完全支持地理空间查询
            // 这里使用简化的距离计算方法
            $result = $query->limit($limit)
                ->order('created_at', 'desc')
                ->select()
                ->toArray();

            // 手动计算距离并过滤
            $nearbyLocations = [];
            foreach ($result as $location) {
                if (isset($location['location']['coordinates'])) {
                    $coords   = $location['location']['coordinates'];
                    $distance = self::calculateDistance($latitude, $longitude, $coords[1], $coords[0]);

                    if ($distance <= $maxDistance) {
                        $location['distance'] = round($distance, 2);
                        $nearbyLocations[]    = $location;
                    }
                }
            }

            // 按距离排序
            usort($nearbyLocations, function ($a, $b) {
                return $a['distance'] <=> $b['distance'];
            });

            // 限制返回数量
            $nearbyLocations = array_slice($nearbyLocations, 0, $limit);

            Log::info('附近位置点查找完成: {found_count}个点, 中心点: {center}', [
                'found_count' => count($nearbyLocations),
                'center'      => [$longitude, $latitude]
            ]);

            return $nearbyLocations;

        } catch (\Exception $e) {
            Log::error('查找附近位置点失败: {error}', [
                'center'       => [$longitude, $latitude],
                'max_distance' => $maxDistance,
                'error'        => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * 查找指定区域内的位置点
     * 
     * @param array $polygon 多边形坐标数组 [[lng1,lat1], [lng2,lat2], ...]
     * @param string $type 位置类型
     * @param int $limit 限制数量
     * @return array
     */
    public static function findWithinPolygon(array $polygon, string $type = '', int $limit = 100): array
    {
        try {
            Log::info('查找多边形区域内位置点: {polygon_points}个点, 类型: {type}, 限制: {limit}', [
                'polygon_points' => count($polygon),
                'type'           => $type,
                'limit'          => $limit
            ]);

            // 验证多边形
            if (count($polygon) < 3) {
                throw new \Exception('多边形至少需要3个点');
            }

            // 确保多边形闭合
            if ($polygon[0] !== $polygon[count($polygon) - 1]) {
                $polygon[] = $polygon[0];
            }

            $query = self::name('location_services');

            // 添加类型过滤
            if (!empty($type)) {
                $query->where('type', $type);
            }

            $result = $query->limit($limit)
                ->select()
                ->toArray();

            // 手动检查点是否在多边形内
            $locationsInPolygon = [];
            foreach ($result as $location) {
                if (isset($location['location']['coordinates'])) {
                    $coords = $location['location']['coordinates'];
                    $point  = [$coords[0], $coords[1]]; // [lng, lat]

                    if (self::isPointInPolygon($point, $polygon)) {
                        $locationsInPolygon[] = $location;
                    }
                }
            }

            Log::info('多边形区域查找完成: {found_count}个点, 多边形点数: {polygon_points}', [
                'found_count'    => count($locationsInPolygon),
                'polygon_points' => count($polygon)
            ]);

            return $locationsInPolygon;

        } catch (\Exception $e) {
            Log::error('多边形区域查找失败: {error}', [
                'polygon' => json_encode($polygon),
                'type'    => $type,
                'error'   => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * 获取位置点的路径距离
     * 
     * @param string $startLocationId 起始位置ID
     * @param string $endLocationId 结束位置ID
     * @return array|null
     */
    public static function getRouteDistance(string $startLocationId, string $endLocationId): ?array
    {
        try {
            Log::info('计算路径距离: 起点ID {start_location_id}, 终点ID {end_location_id}', [
                'start_location_id' => $startLocationId,
                'end_location_id'   => $endLocationId
            ]);

            $startLocation = self::find($startLocationId);
            $endLocation   = self::find($endLocationId);

            if (!$startLocation || !$endLocation) {
                throw new \Exception('位置点不存在');
            }

            $startCoords = $startLocation['location']['coordinates'];
            $endCoords   = $endLocation['location']['coordinates'];

            // 计算直线距离
            $straightDistance = self::calculateDistance(
                $startCoords[1],
                $startCoords[0],
                $endCoords[1],
                $endCoords[0]
            );

            $routeInfo = [
                'start_location'    => [
                    'id'          => $startLocationId,
                    'name'        => $startLocation['name'],
                    'coordinates' => $startCoords
                ],
                'end_location'      => [
                    'id'          => $endLocationId,
                    'name'        => $endLocation['name'],
                    'coordinates' => $endCoords
                ],
                'straight_distance' => round($straightDistance, 2),
                'estimated_time'    => round($straightDistance / 50 * 60, 0), // 假设50km/h的速度，返回分钟
                'calculated_at'     => time()
            ];

            Log::info('路径距离计算完成: 直线距离 {straight_distance}米, 预计时间 {estimated_time}分钟', [
                'straight_distance' => $routeInfo['straight_distance'],
                'estimated_time'    => $routeInfo['estimated_time']
            ]);

            return $routeInfo;

        } catch (\Exception $e) {
            Log::error('计算路径距离失败: {error}', [
                'start_location_id' => $startLocationId,
                'end_location_id'   => $endLocationId,
                'error'             => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * 计算两点间的距离（米）
     * 使用Haversine公式
     * 
     * @param float $lat1 纬度1
     * @param float $lng1 经度1
     * @param float $lat2 纬度2
     * @param float $lng2 经度2
     * @return float 距离（米）
     */
    private static function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000; // 地球半径（米）

        $lat1Rad = deg2rad($lat1);
        $lng1Rad = deg2rad($lng1);
        $lat2Rad = deg2rad($lat2);
        $lng2Rad = deg2rad($lng2);

        $deltaLat = $lat2Rad - $lat1Rad;
        $deltaLng = $lng2Rad - $lng1Rad;

        $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
            cos($lat1Rad) * cos($lat2Rad) *
            sin($deltaLng / 2) * sin($deltaLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * 判断点是否在多边形内
     * 使用射线法
     * 
     * @param array $point [lng, lat]
     * @param array $polygon [[lng, lat], ...]
     * @return bool
     */
    private static function isPointInPolygon(array $point, array $polygon): bool
    {
        $x      = $point[0];
        $y      = $point[1];
        $inside = false;

        $j = count($polygon) - 1;
        for ($i = 0; $i < count($polygon); $i++) {
            $xi = $polygon[$i][0];
            $yi = $polygon[$i][1];
            $xj = $polygon[$j][0];
            $yj = $polygon[$j][1];

            if ((($yi > $y) !== ($yj > $y)) && ($x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi)) {
                $inside = !$inside;
            }
            $j = $i;
        }

        return $inside;
    }

    /**
     * 获取热力图数据
     * 
     * @param float $minLng 最小经度
     * @param float $minLat 最小纬度
     * @param float $maxLng 最大经度
     * @param float $maxLat 最大纬度
     * @param string $type 位置类型
     * @return array
     */
    public static function getHeatmapData(float $minLng, float $minLat, float $maxLng, float $maxLat, string $type = ''): array
    {
        try {
            Log::info('获取热力图数据: 范围 {bounds}, 类型 {type}', [
                'bounds' => [$minLng, $minLat, $maxLng, $maxLat],
                'type'   => $type
            ]);

            $query = self::name('location_services');

            // 添加类型过滤
            if (!empty($type)) {
                $query->where('type', $type);
            }

            $locations = $query->select()->toArray();

            $heatmapData = [];
            foreach ($locations as $location) {
                if (isset($location['location']['coordinates'])) {
                    $coords = $location['location']['coordinates'];
                    $lng    = $coords[0];
                    $lat    = $coords[1];

                    // 检查是否在指定范围内
                    if ($lng >= $minLng && $lng <= $maxLng && $lat >= $minLat && $lat <= $maxLat) {
                        $heatmapData[] = [
                            'lng'    => $lng,
                            'lat'    => $lat,
                            'weight' => $location['properties']['weight'] ?? 1
                        ];
                    }
                }
            }

            Log::info('热力图数据获取完成: {data_count}个数据点', ['data_count' => count($heatmapData)]);
            return $heatmapData;

        } catch (\Exception $e) {
            Log::error('获取热力图数据失败: {error}', [
                'bounds' => [$minLng, $minLat, $maxLng, $maxLat],
                'error'  => $e->getMessage()
            ]);
            return [];
        }
    }
}