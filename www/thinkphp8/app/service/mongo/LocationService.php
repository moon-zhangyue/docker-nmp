<?php

namespace app\service\mongo;

use think\facade\Db;
use think\facade\Log;
use MongoDB\Driver\Command;

class LocationService
{
    private $connection = 'mongo';
    private $collection = 'locations'; // Example collection for geospatial data

    /**
     * Adds a new location with geospatial data.
     *
     * The $locationData should include a field for coordinates, typically named 'location',
     * structured as a GeoJSON Point: ['type' => 'Point', 'coordinates' => [longitude, latitude]]
     *
     * IMPORTANT: For geospatial queries to work efficiently, a 2dsphere index must be created
     * on the 'location' field in the MongoDB 'locations' collection.
     * Example mongo shell command:
     * db.locations.createIndex({ "location": "2dsphere" })
     *
     * @param array $locationData e.g., ['name' => 'Central Park', 'location' => ['type' => 'Point', 'coordinates' => [-73.97, 40.77]]]
     * @return bool|string Inserted ID or false on failure
     */
    public function addLocation(array $locationData)
    {
        if (empty($locationData) || !isset($locationData['location'])) {
            Log::warning('[MongoLocationService] Attempted to add location with invalid or missing geospatial data.', []);
            return false;
        }

        $location = json_decode($locationData['location'], true);
        if (!isset($location)) {
            Log::warning('[MongoLocationService] Attempted to add location with invalid or missing geospatial data.', []);
            return false;
        }

        // Basic validation for coordinate structure
        if (!is_array($location['coordinates']) || count($location['coordinates']) !== 2) {
            Log::warning('[MongoLocationService] Invalid coordinates format. Must be [longitude, latitude]. Data: {data}', ['data' => json_encode($locationData)]);
            return false;
        }

        try {
            $insertedId = Db::connect($this->connection)->table($this->collection)->insertGetId($locationData);
            if ($insertedId) {
                Log::info('[MongoLocationService] Location added. ID: {id}, Data: {data}', ['id' => $insertedId, 'data' => json_encode($locationData)]);
                return $insertedId;
            } else {
                Log::error('[MongoLocationService] Failed to add location. Data: {data}', ['data' => json_encode($locationData)]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('[MongoLocationService] Error adding location: {message}, Data: {data}', ['message' => $e->getMessage(), 'data' => json_encode($locationData)]);
            return false;
        }
    }

    /**
     * Finds locations near a given point.
     *
     * IMPORTANT: Requires a 2dsphere index on the 'location' field.
     *
     * @param float $longitude
     * @param float $latitude
     * @param int $maxDistanceMeters Max distance in meters
     * @param int $limit
     * @return array
     */
    public function findNearbyLocations(float $longitude, float $latitude, int $maxDistanceMeters = 5000, int $limit = 10): array
    {
        try {
            // 获取所有位置
            $allLocations = Db::connect($this->connection)
                ->table($this->collection)
                ->select()
                ->toArray();
            
            // 手动计算距离并筛选结果
            $result = [];
            foreach ($allLocations as $location) {
                if (isset($location['location']) && isset($location['location']['coordinates'])) {
                    $locCoords = $location['location']['coordinates'];
                    
                    // 确保坐标是数组并且有两个元素
                    if (is_array($locCoords) && count($locCoords) === 2) {
                        $locLng = $locCoords[0];
                        $locLat = $locCoords[1];
                        
                        // 计算距离（使用球面余弦定理计算大圆距离）
                        $distance = $this->calculateDistance($latitude, $longitude, $locLat, $locLng);
                        
                        // 如果距离小于最大距离，添加到结果中
                        if ($distance <= $maxDistanceMeters) {
                            $location['distance'] = $distance;
                            $result[] = $location;
                        }
                    }
                }
            }
            
            // 按距离排序
            usort($result, function($a, $b) {
                return $a['distance'] - $b['distance'];
            });
            
            // 限制结果数量
            $result = array_slice($result, 0, $limit);
            
            Log::info('[MongoLocationService] Found {count} nearby locations. Point: [{lng}, {lat}], MaxDistance: {distance}m', 
                ['count' => count($result), 'lng' => $longitude, 'lat' => $latitude, 'distance' => $maxDistanceMeters]);
            return $result;
        } catch (\Exception $e) {
            Log::error('[MongoLocationService] Error finding nearby locations: {message} Point: [{lng}, {lat}]', 
                ['message' => $e->getMessage(), 'lng' => $longitude, 'lat' => $latitude]);
            return [];
        }
    }
    
    /**
     * 计算两个坐标点之间的距离（米）
     *
     * @param float $lat1 第一点纬度
     * @param float $lng1 第一点经度
     * @param float $lat2 第二点纬度
     * @param float $lng2 第二点经度
     * @return float 距离（米）
     */
    private function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        // 地球半径（米）
        $earthRadius = 6371000;
        
        // 将经纬度转换为弧度
        $lat1Rad = deg2rad($lat1);
        $lng1Rad = deg2rad($lng1);
        $lat2Rad = deg2rad($lat2);
        $lng2Rad = deg2rad($lng2);
        
        // 差值
        $latDiff = $lat2Rad - $lat1Rad;
        $lngDiff = $lng2Rad - $lng1Rad;
        
        // Haversine公式
        $a = sin($latDiff / 2) * sin($latDiff / 2) + 
             cos($lat1Rad) * cos($lat2Rad) * sin($lngDiff / 2) * sin($lngDiff / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distance = $earthRadius * $c;
        
        return $distance;
    }
}

/*
 * =============================================================================
 *  Conceptual Testing Notes for LocationService (Geospatial)
 * =============================================================================
 *
 * **Crucial Prerequisite for Integration Tests:**
 * - A MongoDB instance with a 'locations' collection that has a '2dsphere' index
 *   on the 'location' field. (e.g., `db.locations.createIndex({ "location": "2dsphere" })`)
 *
 * **Unit Tests:**
 * - Mock `think\facade\Db` and `think\facade\Log`.
 * - Test `addLocation()`:
 *   - With valid GeoJSON data: Verify `Db::connect()->table()->insertGetId()` is called.
 *   - With invalid/missing 'location' data or malformed 'coordinates': Verify returns false and logs warning.
 *   - With DB exception: Verify catches, logs, and returns false.
 * - Test `findNearbyLocations()`:
 *   - Verify `Db::connect()->table()->where(...geospatial query...)->limit()->select()` is called with correct query structure.
 *     The geospatial query part (`$near` with `$geometry` and `$maxDistance`) is key.
 *   - Mock `select()` to return a mock collection, then mock `all()` on it.
 *   - With DB exception: Verify catches, logs, and returns an empty array.
 *
 * **Integration Tests (Requires MongoDB with 2dsphere index):**
 * - Test `addLocation()`:
 *   - Call with valid location data (e.g., `['name' => 'Test Point', 'location' => ['type' => 'Point', 'coordinates' => [10, 20]]]`).
 *   - Query DB directly or use another method to verify correct insertion.
 * - Test `findNearbyLocations()`:
 *   - Add several known locations with varying coordinates.
 *   - Call `findNearbyLocations()` with a central point and a specific radius:
 *     - Verify that locations within the radius are returned.
 *     - Verify that locations outside the radius are not returned.
 *     - Verify the `limit` parameter is respected.
 *     - Test edge cases (e.g., point exactly on the radius boundary, no locations nearby).
 *
 * **Controller-Level Integration Tests (HTTP requests):**
 * - Test `app\controller\mongo\LocationController` actions.
 * - Example:
 *   - POST to `/mongo/location/add` with valid GeoJSON (e.g., `{"name": "City Hall", "location": {"type": "Point", "coordinates": [-74.006, 40.7128]}}`).
 *     Check for 200 status and ID in response.
 *   - Add locations, then GET from `/mongo/location/nearby?longitude=-74.006&latitude=40.7128&distance=1000`.
 *     Check for 200 status and an array of nearby locations.
 *   - POST to `/mongo/location/add` with invalid data (e.g., no `location` field), check for 400.
 *   - GET from `/mongo/location/nearby` without `longitude` or `latitude`, check for 400.
 */
