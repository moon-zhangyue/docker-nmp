<?php

namespace app\controller\mongo;

use app\BaseController;
use app\service\mongo\LocationService;
use think\facade\Log;
use think\Request;

class LocationController extends BaseController
{
    protected $locationService;

    public function __construct(LocationService $locationService)
    {
        $this->locationService = $locationService;
    }

    /**
     * Add a new location with geospatial data.
     * Example POST data:
     * {"name": "Eiffel Tower", "location": {"type": "Point", "coordinates": [2.2945, 48.8584]}}
     * {"name": "Googleplex", "category": "office", "location": {"type": "Point", "coordinates": [-122.084, 37.422]}}
     */
    public function add(Request $request)
    {
        $data = $request->post();
        if (empty($data) || !isset($data['location'])) {
            Log::warning('[MongoLocationController] Add: Invalid request data for location.');
            return json(['status' => 'error', 'message' => 'Location data is required, including coordinates.'], 400);
        }

        Log::info('[MongoLocationController] Add: Received request to add location. Data: ' . json_encode($data));
        $result = $this->locationService->addLocation($data);

        if ($result) {
            Log::info('[MongoLocationController] Add: Location added successfully. ID: ' . $result);
            return json(['status' => 'success', 'message' => 'Location added successfully', 'id' => $result]);
        } else {
            Log::error('[MongoLocationController] Add: Failed to add location.');
            return json(['status' => 'error', 'message' => 'Failed to add location'], 500);
        }
    }

    /**
     * Find nearby locations.
     * Query params: longitude, latitude, distance (optional, meters, default 5000), limit (optional, default 10)
     * Example GET: /mongo/location/nearby?longitude=-73.985&latitude=40.758&distance=2000
     */
    public function nearby(Request $request)
    {
        $longitude = $request->get('longitude');
        $latitude = $request->get('latitude');
        $maxDistance = $request->get('distance', 5000); // Default 5km
        $limit = $request->get('limit', 10);

        if (is_null($longitude) || is_null($latitude)) {
            Log::warning('[MongoLocationController] Nearby: Missing longitude or latitude.');
            return json(['status' => 'error', 'message' => 'Longitude and latitude are required query parameters.'], 400);
        }

        Log::info('[MongoLocationController] Nearby: Received request. Lon: ' . $longitude . ', Lat: ' . $latitude . ', Distance: ' . $maxDistance . 'm, Limit: ' . $limit);
        $locations = $this->locationService->findNearbyLocations((float)$longitude, (float)$latitude, (int)$maxDistance, (int)$limit);

        Log::info('[MongoLocationController] Nearby: Found ' . count($locations) . ' locations.');
        return json(['status' => 'success', 'data' => $locations]);
    }
}
