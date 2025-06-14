<?php
declare(strict_types=1);

namespace app\controller\mongo;

use app\BaseController;
use app\service\mongo\LocationService;
use think\facade\Log;
use think\Request;
use app\service\LocationService as LocationServiceModel;
use think\App;
use think\Response;
use think\exception\ValidateException;

class LocationController extends BaseController
{

    protected $locationService;
    protected $locationServiceModel;

    public function __construct(App $app, LocationService $locationService)
    {
        parent::__construct($app);
        $this->locationService = $locationService;

        $this->locationServiceModel = new LocationServiceModel();
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
        $longitude   = $request->get('longitude');
        $latitude    = $request->get('latitude');
        $maxDistance = $request->get('distance', 5000); // Default 5km
        $limit       = $request->get('limit', 10);

        if (is_null($longitude) || is_null($latitude)) {
            Log::warning('[MongoLocationController] Nearby: Missing longitude or latitude.');
            return json(['status' => 'error', 'message' => 'Longitude and latitude are required query parameters.'], 400);
        }

        Log::info('[MongoLocationController] Nearby: Received request. Lon: ' . $longitude . ', Lat: ' . $latitude . ', Distance: ' . $maxDistance . 'm, Limit: ' . $limit);
        $locations = $this->locationService->findNearbyLocations((float) $longitude, (float) $latitude, (int) $maxDistance, (int) $limit);

        Log::info('[MongoLocationController] Nearby: Found ' . count($locations) . ' locations.');
        return json(['status' => 'success', 'data' => $locations]);
    }

    /**
     * 保存位置信息
     * 
     * @return Response
     */
    public function save(): Response
    {
        try {
            // 获取POST数据
            $data = $this->request->post();

            // 保存位置
            $location = $this->locationServiceModel->saveLocation($data);

            return json(['code' => 200, 'message' => '保存成功', 'data' => $location]);
        } catch (ValidateException $e) {
            return json(['code' => 400, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('保存位置信息接口异常', ['message' => $e->getMessage()]);
            return json(['code' => 500, 'message' => '服务器错误：' . $e->getMessage()]);
        }
    }

    /**
     * 更新位置信息
     * 
     * @param string $id 位置ID
     * @return Response
     */
    public function update(string $id): Response
    {
        try {
            // 获取PUT数据
            $data = $this->request->put();

            // 更新位置
            $result = $this->locationServiceModel->updateLocation($id, $data);

            return json(['code' => 200, 'message' => '更新成功', 'data' => $result]);
        } catch (ValidateException $e) {
            return json(['code' => 400, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('更新位置信息接口异常', ['id' => $id, 'message' => $e->getMessage()]);
            return json(['code' => 500, 'message' => '服务器错误：' . $e->getMessage()]);
        }
    }

    /**
     * 查询附近的位置
     * 
     * @return Response
     */
    public function nearbyLocation(): Response
    {
        try {
            // 获取请求参数
            $longitude = floatval($this->request->param('longitude', 0));
            $latitude  = floatval($this->request->param('latitude', 0));
            $distance  = intval($this->request->param('distance', 1000));
            $type      = $this->request->param('type', '');
            $limit     = intval($this->request->param('limit', 20));

            // 参数验证
            if ($longitude === 0 || $latitude === 0) {
                return json(['code' => 400, 'message' => '经纬度不能为空']);
            }

            // 构建过滤条件
            $filter = [];
            if (!empty($type)) {
                $filter['type'] = $type;
            }

            // 查询附近位置
            $locations = $this->locationServiceModel->findNearby($longitude, $latitude, $distance, $filter, $limit);

            return json(['code' => 200, 'message' => '查询成功', 'data' => $locations]);
        } catch (\Exception $e) {
            Log::error('查询附近位置接口异常', ['message' => $e->getMessage()]);
            return json(['code' => 500, 'message' => '服务器错误：' . $e->getMessage()]);
        }
    }

    /**
     * 根据区域查询位置
     * 
     * polygon: 区域多边形坐标，格式为："116.397428,39.90923;116.405234,39.90923;116.405234,39.917036;116.397428,39.917036"
     * type: 位置类型，可选值为："office", "home", "school", "hospital", "other", "point"
     * @return Response
     */
    public function area(): Response
    {
        try {
            // 获取请求参数
            $polygon = $this->request->param('polygon', '');
            $type    = $this->request->param('type', '');

            // 参数验证
            if (empty($polygon)) {
                return json(['code' => 400, 'message' => '区域多边形不能为空']);
            }

            // 解析多边形坐标
            $polygonPoints = [];
            $points        = explode(';', $polygon);
            foreach ($points as $point) {
                $coordinates = explode(',', $point);
                if (count($coordinates) === 2) {
                    $polygonPoints[] = [floatval($coordinates[0]), floatval($coordinates[1])];
                }
            }

            // 构建过滤条件
            $filter = [];
            if (!empty($type)) {
                $filter['type'] = $type;
            }

            // 查询区域内位置
            $locations = $this->locationServiceModel->findInPolygon($polygonPoints, $filter);

            return json(['code' => 200, 'message' => '查询成功', 'data' => $locations]);
        } catch (\Exception $e) {
            Log::error('根据区域查询位置接口异常', ['message' => $e->getMessage()]);
            return json(['code' => 500, 'message' => '服务器错误：' . $e->getMessage()]);
        }
    }

    /**
     * 计算两点距离
     * 
     * @return Response
     */
    public function distance(): Response
    {
        try {
            // 获取请求参数
            $longitude1 = floatval($this->request->param('longitude1', 0));
            $latitude1  = floatval($this->request->param('latitude1', 0));
            $longitude2 = floatval($this->request->param('longitude2', 0));
            $latitude2  = floatval($this->request->param('latitude2', 0));

            // 参数验证
            if ($longitude1 === 0 || $latitude1 === 0 || $longitude2 === 0 || $latitude2 === 0) {
                return json(['code' => 400, 'message' => '经纬度不能为空']);
            }

            // 计算距离
            $distance = $this->locationServiceModel->calcDistance(
                $longitude1,
                $latitude1,
                $longitude2,
                $latitude2
            );

            return json([
                'code'    => 200,
                'message' => '计算成功',
                'data'    => [
                    'distance'    => $distance,
                    'distance_km' => round($distance / 1000, 2)
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('计算两点距离接口异常', ['message' => $e->getMessage()]);
            return json(['code' => 500, 'message' => '服务器错误：' . $e->getMessage()]);
        }
    }
}
