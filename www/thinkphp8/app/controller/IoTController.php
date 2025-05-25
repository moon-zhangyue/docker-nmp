<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\service\IoTService;
use think\App;
use think\facade\Log;
use think\Response;
use think\exception\ValidateException;
use think\facade\Db;

class IoTController extends BaseController
{
    protected $iotService;

    public function __construct(App $app, IoTService $iotService)
    {
        parent::__construct($app);
        $this->iotService = $iotService;
    }

    /**
     * 接收设备数据
     * 
     * @return Response
     */
    public function receiveData(): Response
    {
        // 方式二：使用 data 方法后调用 insert，同样返回影响数量
        $result = Db::name('users')->data(['username' => '李四1', 'email' => 'lisi@example.com', 'age' => 30, 'sex' => 1])->insert();

        if ($result) {
            return json(['code' => 200, 'message' => '数据保存成功']);
        } else {
            return json(['code' => 500, 'message' => '数据保存失败']);

        }
        // try {
        //     // 获取POST数据
        //     $data = $this->request->post();

        //     // 保存设备数据
        //     $result = $this->iotService->saveData($data);

        //     return json(['code' => 200, 'message' => $result ? '数据保存成功' : '数据保存失败']);
        // } catch (ValidateException $e) {
        //     return json(['code' => 400, 'message' => $e->getMessage()]);
        // } catch (\Exception $e) {
        //     Log::error('接收设备数据异常', ['message' => $e->getMessage()]);
        //     return json(['code' => 500, 'message' => '服务器错误：' . $e->getMessage()]);
        // }
    }

    /**
     * 批量接收设备数据
     * 
     * @return Response
     */
    public function batchReceiveData(): Response
    {
        try {
            // 获取POST数据
            $dataList = $this->request->post();

            // 检查数据格式
            if (!is_array($dataList) || !isset($dataList[0])) {
                return json(['code' => 400, 'message' => '数据格式错误，应为数组']);
            }

            // 批量保存设备数据
            $result = $this->iotService->batchSaveData($dataList);

            return json(['code' => 200, 'message' => $result ? '数据保存成功' : '数据保存失败']);
        } catch (\Exception $e) {
            Log::error('批量接收设备数据异常', ['message' => $e->getMessage()]);
            return json(['code' => 500, 'message' => '服务器错误：' . $e->getMessage()]);
        }
    }

    /**
     * 获取设备历史数据
     * 
     * @param string $deviceId 设备ID
     * @return Response
     */
    public function getHistoryData(string $deviceId): Response
    {
        try {
            // 获取请求参数
            $startTime = $this->request->param('start_time', date('Y-m-d H:i:s', strtotime('-1 day')));
            $endTime   = $this->request->param('end_time', date('Y-m-d H:i:s'));
            $page      = intval($this->request->param('page', 1));
            $limit     = intval($this->request->param('limit', 20));

            // 参数验证
            if (empty($deviceId)) {
                return json(['code' => 400, 'message' => '设备ID不能为空']);
            }

            // 获取历史数据
            $data = $this->iotService->getHistoryData($deviceId, $startTime, $endTime, $page, $limit);

            return json(['code' => 200, 'message' => '查询成功', 'data' => $data]);
        } catch (\Exception $e) {
            Log::error('获取设备历史数据异常', ['device_id' => $deviceId, 'message' => $e->getMessage()]);
            return json(['code' => 500, 'message' => '服务器错误：' . $e->getMessage()]);
        }
    }

    /**
     * 获取设备最新数据
     * 
     * @param string $deviceId 设备ID
     * @return Response
     */
    public function getLatestData(string $deviceId): Response
    {
        try {
            // 参数验证
            if (empty($deviceId)) {
                return json(['code' => 400, 'message' => '设备ID不能为空']);
            }

            // 获取最新数据
            $data = $this->iotService->getLatestData($deviceId);

            if ($data) {
                return json(['code' => 200, 'message' => '查询成功', 'data' => $data]);
            } else {
                return json(['code' => 404, 'message' => '未找到设备数据']);
            }
        } catch (\Exception $e) {
            Log::error('获取设备最新数据异常', ['device_id' => $deviceId, 'message' => $e->getMessage()]);
            return json(['code' => 500, 'message' => '服务器错误：' . $e->getMessage()]);
        }
    }
}