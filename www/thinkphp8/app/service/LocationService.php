<?php
declare(strict_types=1);

namespace app\service;

use app\model\Location;
use think\facade\Log;
use think\facade\Cache;

class LocationService
{
    /**
     * 保存位置信息
     * 
     * @param array $data 位置数据
     * @return array|null
     */
    public function saveLocation(array $data): ?array
    {
        try {
            // 验证必要字段
            if (empty($data['longitude']) || empty($data['latitude']) || empty($data['name'])) {
                throw new \Exception('经度、纬度和名称不能为空');
            }

            // 记录日志
            Log::info('保存位置信息: {data}', ['data' => json_encode($data)]);

            // 创建位置
            $location = Location::createLocation($data);

            return $location ? $location->toArray() : null;
        } catch (\Exception $e) {
            Log::error('保存位置信息失败', ['data' => $data, 'message' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * 更新位置信息
     * 
     * @param mixed $id 位置ID
     * @param array $data 更新数据
     * @return bool
     */
    public function updateLocation($id, array $data): bool
    {
        try {
            // 检查位置是否存在
            $location = Location::find($id);
            if (!$location) {
                throw new \Exception('位置不存在');
            }

            // 记录日志
            Log::info('更新位置信息: ID:{id}, 数据:{data}', ['id' => $id, 'data' => json_encode($data)]);

            // 更新位置
            return Location::updateLocation($id, $data);
        } catch (\Exception $e) {
            Log::error('更新位置信息失败', ['id' => $id, 'data' => $data, 'message' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * 查询附近的位置
     * 
     * @param float $longitude 经度
     * @param float $latitude 纬度
     * @param int $distance 距离(米)
     * @param array $filter 附加查询条件
     * @param int $limit 限制数量
     * @return array
     */
    public function findNearby(float $longitude, float $latitude, int $distance = 1000, array $filter = [], int $limit = 20): array
    {
        try {
            // 缓存键
            $cacheKey = "location:nearby:{$longitude}:{$latitude}:{$distance}:" . md5(json_encode($filter)) . ":{$limit}";

            // 优先从缓存获取
            if (Cache::has($cacheKey)) {
                return Cache::get($cacheKey);
            }

            // 记录日志
            Log::info('查询附近位置: 坐标({longitude},{latitude}), 距离: {distance}米', [
                'coordinates' => [$longitude, $latitude],
                'longitude'   => $longitude,
                'latitude'    => $latitude,
                'distance'    => $distance,
                'filter'      => $filter,
                'limit'       => $limit
            ]);

            // 查询附近位置
            $result = Location::findNearby($longitude, $latitude, $distance, $filter, $limit);

            // 缓存结果，5分钟过期
            Cache::set($cacheKey, $result, 300);

            return $result;
        } catch (\Exception $e) {
            Log::error('查询附近位置失败: {message}, 坐标: ({longitude},{latitude})', [
                'coordinates' => [$longitude, $latitude],
                'longitude'   => $longitude,
                'latitude'    => $latitude,
                'message'     => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * 根据多边形区域查询位置
     * 
     * @param array $polygon 多边形坐标点数组 [[$lng1, $lat1], [$lng2, $lat2], ...]
     * @param array $filter 附加查询条件
     * @return array
     */
    public function findInPolygon(array $polygon, array $filter = []): array
    {
        try {
            // 缓存键
            $cacheKey = "location:polygon:" . md5(json_encode($polygon)) . ":" . md5(json_encode($filter));

            // 优先从缓存获取
            if (Cache::has($cacheKey)) {
                return Cache::get($cacheKey);
            }

            // 记录日志
            Log::info('根据多边形区域查询位置: {point_count}个坐标点', [
                'polygon'     => $polygon,
                'filter'      => $filter,
                'point_count' => count($polygon)
            ]);

            // 查询区域内位置
            $result = Location::findInPolygon($polygon, $filter);

            // 缓存结果，5分钟过期
            Cache::set($cacheKey, $result, 300);

            return $result;
        } catch (\Exception $e) {
            Log::error('根据多边形区域查询位置失败: {message}', [
                'polygon' => $polygon,
                'message' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * 计算两点之间距离
     * 
     * @param float $longitude1 起点经度
     * @param float $latitude1 起点纬度
     * @param float $longitude2 终点经度
     * @param float $latitude2 终点纬度
     * @return float 距离(米)
     */
    public function calcDistance(
        float $longitude1,
        float $latitude1,
        float $longitude2,
        float $latitude2
    ): float {
        // 地球半径
        $earthRadius = 6371000;

        // 将经纬度转换为弧度
        $lat1 = deg2rad($latitude1);
        $lon1 = deg2rad($longitude1);
        $lat2 = deg2rad($latitude2);
        $lon2 = deg2rad($longitude2);

        // 半正矢公式
        $latDelta = $lat2 - $lat1;
        $lonDelta = $lon2 - $lon1;
        $a        = sin($latDelta / 2) * sin($latDelta / 2) +
            cos($lat1) * cos($lat2) *
            sin($lonDelta / 2) * sin($lonDelta / 2);
        $c        = 2 * atan2(sqrt($a), sqrt(1 - $a));

        // 计算距离
        $distance = $earthRadius * $c;

        return $distance;
    }
}