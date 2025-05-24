<?php

namespace app\service\mongo;

use think\facade\Db;
use think\facade\Log;

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
        if (empty($locationData) || !isset($locationData['location']) || !isset($locationData['location']['coordinates'])) {
            Log::warning('[MongoLocationService] Attempted to add location with invalid or missing geospatial data.');
            return false;
        }
        // Basic validation for coordinate structure
        if (!is_array($locationData['location']['coordinates']) || count($locationData['location']['coordinates']) !== 2) {
            Log::warning('[MongoLocationService] Invalid coordinates format. Must be [longitude, latitude]. Data: ' . json_encode($locationData));
            return false;
        }


        try {
            $insertedId = Db::connect($this->connection)->table($this->collection)->insertGetId($locationData);
            if ($insertedId) {
                Log::info('[MongoLocationService] Location added. ID: ' . $insertedId . ', Data: ' . json_encode($locationData));
                return $insertedId;
            } else {
                Log::error('[MongoLocationService] Failed to add location. Data: ' . json_encode($locationData));
                return false;
            }
        } catch (\Exception $e) {
            Log::error('[MongoLocationService] Error adding location: ' . $e->getMessage() . ', Data: ' . json_encode($locationData));
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
            // MongoDB's $near or $geoNear typically requires coordinates in [longitude, latitude] order.
            $query = [
                'location' => [
                    '$near' => [
                        '$geometry' => [
                            'type'        => 'Point',
                            'coordinates' => [$longitude, $latitude],
                        ],
                        '$maxDistance' => $maxDistanceMeters,
                    ],
                ],
            ];

            $locations = Db::connect($this->connection)
                            ->table($this->collection)
                            ->where($query) // Using where() with the geospatial query structure
                            ->limit($limit)
                            ->select();

            Log::info('[MongoLocationService] Found ' . count($locations) . ' nearby locations. Point: [' . $longitude . ', ' . $latitude . '], MaxDistance: ' . $maxDistanceMeters . 'm');
            return $locations->all();
        } catch (\Exception $e) {
            Log::error('[MongoLocationService] Error finding nearby locations: ' . $e->getMessage() . ' Point: [' . $longitude . ', ' . $latitude . ']');
            return [];
        }
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
