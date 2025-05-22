<?php
declare(strict_types=1);

namespace app\model;

use think\Model;
use think\facade\Log;

class Location extends Model
{
    // 设置MongoDB连接
    protected $connection = 'mongo';
    
    // 设置集合名称
    protected $table = 'locations';
    
    // 设置主键
    protected $pk = '_id';
    
    // 自动时间戳
    protected $autoWriteTimestamp = true;
    
    /**
     * 创建位置信息
     * 
     * @param array $data 位置数据
     * @return Location|null
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
            Log::error('创建位置信息失败: {message}', ['data' => $data, 'message' => $e->getMessage()]);
            return null;
        }
    }
    
    /**
     * 更新位置信息
     * 
     * @param mixed $id 位置ID
     * @param array $data 更新数据
     * @return bool
     */
    public static function updateLocation($id, array $data): bool
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
            
            return self::find($id)->save($data);
        } catch (\Exception $e) {
            Log::error('更新位置信息失败', ['id' => $id, 'data' => $data, 'message' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * 根据距离查询附近的位置
     * 
     * @param float $longitude 经度
     * @param float $latitude 纬度
     * @param int $distance 距离(米)
     * @param array $filter 附加查询条件
     * @param int $limit 限制数量
     * @return array
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
            Log::error('查询附近位置失败: {message}, 坐标: {coordinates}, 距离: {distance}', [
                'coordinates' => [$longitude, $latitude],
                'distance' => $distance,
                'message' => $e->getMessage()
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
            Log::error('根据多边形区域查询位置失败: {message}, 多边形: {polygon}', [
                'polygon' => $polygon,
                'message' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * 执行MongoDB聚合查询
     * 
     * @param array $pipeline 聚合管道
     * @return array
     */
    protected static function mongoAggregate(array $pipeline): array
    {
        try {
            $model = new self();
            $connection = $model->getConnection();
            $collection = $connection->getCollection($model->getTable());
            
            $result = $collection->aggregate($pipeline)->toArray();
            
            return $result;
        } catch (\Exception $e) {
            Log::error('MongoDB聚合查询失败', ['pipeline' => $pipeline, 'message' => $e->getMessage()]);
            return [];
        }
    }
} 