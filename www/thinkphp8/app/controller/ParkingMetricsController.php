<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\ParkingLot;
use app\model\ParkingRecord;
use app\model\GateDevice;
use app\service\InfluxDBService;
use think\facade\Db;
use think\Request;

/**
 * 停车场监控指标控制器
 * 
 * 该控制器用于处理停车场相关的监控指标数据，包括车位占用率、高峰期分析、设备状态等
 */
class ParkingMetricsController extends BaseController
{
    /**
     * @var InfluxDBService
     */
    protected $influxDBService;

    /**
     * 构造函数
     */
    public function __construct(InfluxDBService $influxDBService)
    {
        $this->influxDBService = $influxDBService;
    }

    /**
     * 记录车位占用率数据
     * 
     * @param Request $request
     * @return \think\Response
     */
    public function recordOccupancyRate(Request $request)
    {
        $parkingLotId = $request->param('parking_lot_id');

        if (empty($parkingLotId)) {
            return json(['code' => 1, 'msg' => '停车场ID不能为空']);
        }

        $parkingLot = ParkingLot::find($parkingLotId);
        if (!$parkingLot) {
            return json(['code' => 1, 'msg' => '停车场不存在']);
        }

        // 计算占用率
        $totalSpaces     = $parkingLot->total_spaces;
        $occupiedSpaces  = $parkingLot->occupied_spaces;
        $availableSpaces = $totalSpaces - $occupiedSpaces;
        $occupancyRate   = $totalSpaces > 0 ? ($occupiedSpaces / $totalSpaces) * 100 : 0;

        // 准备数据
        $occupancyData = [
            'total_spaces'     => $totalSpaces,
            'occupied_spaces'  => $occupiedSpaces,
            'available_spaces' => $availableSpaces,
            'occupancy_rate'   => $occupancyRate,
            'timestamp'        => time(),
            'area'             => $request->param('area', 'main'),
        ];

        // 写入InfluxDB
        $result = $this->influxDBService->writeOccupancyRate($parkingLotId, $occupancyData);

        return json([
            'code' => $result ? 0 : 1,
            'msg'  => $result ? '记录成功' : '记录失败',
            'data' => $occupancyData
        ]);
    }

    /**
     * 记录设备状态数据
     * 
     * @param Request $request
     * @return \think\Response
     */
    public function recordDeviceStatus(Request $request)
    {
        $deviceId = $request->param('device_id');

        if (empty($deviceId)) {
            return json(['code' => 1, 'msg' => '设备ID不能为空']);
        }

        $device = GateDevice::find($deviceId);
        if (!$device) {
            return json(['code' => 1, 'msg' => '设备不存在']);
        }

        // 准备数据
        $statusData = [
            'device_type'    => $device->type,
            'parking_lot_id' => $request->param('parking_lot_id', ''),
            'location'       => $device->location,
            'status'         => $device->status,
            'is_online'      => $device->status == GateDevice::STATUS_ONLINE,
            'last_heartbeat' => strtotime($device->last_heartbeat ?: date('Y-m-d H:i:s')),
            'response_time'  => $request->param('response_time', 0),
            'error_count'    => $request->param('error_count', 0),
            'timestamp'      => time(),
        ];

        // 写入InfluxDB
        $result = $this->influxDBService->writeDeviceStatus($deviceId, $statusData);

        return json([
            'code' => $result ? 0 : 1,
            'msg'  => $result ? '记录成功' : '记录失败',
            'data' => $statusData
        ]);
    }

    /**
     * 记录车牌识别性能数据
     * 
     * @param Request $request
     * @return \think\Response
     */
    public function recordPlateRecognition(Request $request)
    {
        $deviceId = $request->param('device_id');

        if (empty($deviceId)) {
            return json(['code' => 1, 'msg' => '设备ID不能为空']);
        }

        // 准备数据
        $recognitionData = [
            'device_type'      => $request->param('device_type', ''),
            'parking_lot_id'   => $request->param('parking_lot_id', ''),
            'camera_type'      => $request->param('camera_type', 'standard'),
            'recognition_time' => $request->param('recognition_time', 0),
            'confidence'       => $request->param('confidence', 0.0),
            'success'          => $request->param('success', true),
            'error_type'       => $request->param('error_type', ''),
            'light_condition'  => $request->param('light_condition', 'normal'),
            'timestamp'        => time(),
        ];

        // 写入InfluxDB
        $result = $this->influxDBService->writePlateRecognitionMetrics($deviceId, $recognitionData);

        return json([
            'code' => $result ? 0 : 1,
            'msg'  => $result ? '记录成功' : '记录失败',
            'data' => $recognitionData
        ]);
    }

    /**
     * 记录闸机操作日志
     * 
     * @param Request $request
     * @return \think\Response
     */
    public function recordGateOperation(Request $request)
    {
        $deviceId = $request->param('device_id');

        if (empty($deviceId)) {
            return json(['code' => 1, 'msg' => '设备ID不能为空']);
        }

        // 准备数据
        $operationData = [
            'operation_type' => $request->param('operation_type', ''), // open, close, reset
            'trigger_source' => $request->param('trigger_source', ''), // auto, manual, remote
            'parking_lot_id' => $request->param('parking_lot_id', ''),
            'plate_number'   => $request->param('plate_number', ''),
            'operator'       => $request->param('operator', 'system'),
            'success'        => $request->param('success', true),
            'error_message'  => $request->param('error_message', ''),
            'response_time'  => $request->param('response_time', 0),
            'timestamp'      => time(),
        ];

        // 写入InfluxDB
        $result = $this->influxDBService->writeGateOperation($deviceId, $operationData);

        return json([
            'code' => $result ? 0 : 1,
            'msg'  => $result ? '记录成功' : '记录失败',
            'data' => $operationData
        ]);
    }

    /**
     * 分析高峰期数据
     * 
     * @param Request $request
     * @return \think\Response
     */
    public function analyzePeakHours(Request $request)
    {
        $parkingLotId = $request->param('parking_lot_id');
        $startTime    = $request->param('start_time', date('Y-m-d', strtotime('-7 days')));
        $endTime      = $request->param('end_time', date('Y-m-d'));
        $groupBy      = $request->param('group_by', '1h');

        if (empty($parkingLotId)) {
            return json(['code' => 1, 'msg' => '停车场ID不能为空']);
        }

        // 查询高峰期数据
        $peakData = $this->influxDBService->getPeakHoursAnalysis($parkingLotId, $startTime, $endTime, $groupBy);

        // 处理数据，计算每个时间段的进出车辆数
        $processedData = [];
        foreach ($peakData as $record) {
            $time      = isset($record['_time']) ? date('Y-m-d H:i:s', strtotime($record['_time'])) : '';
            $eventType = $record['event_type'] ?? '';
            $count     = $record['_value'] ?? 0;

            if (!isset($processedData[$time])) {
                $processedData[$time] = [
                    'time'        => $time,
                    'entry_count' => 0,
                    'exit_count'  => 0,
                    'total_count' => 0
                ];
            }

            if ($eventType == 'entry') {
                $processedData[$time]['entry_count'] = $count;
            } elseif ($eventType == 'exit') {
                $processedData[$time]['exit_count'] = $count;
            }

            $processedData[$time]['total_count'] =
                $processedData[$time]['entry_count'] + $processedData[$time]['exit_count'];
        }

        // 按总数排序，找出高峰期
        usort($processedData, function ($a, $b) {
            return $b['total_count'] - $a['total_count'];
        });

        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => array_values($processedData)
        ]);
    }

    /**
     * 获取车位占用率历史数据
     * 
     * @param Request $request
     * @return \think\Response
     */
    public function getOccupancyHistory(Request $request)
    {
        $parkingLotId = $request->param('parking_lot_id');
        $startTime    = $request->param('start_time', date('Y-m-d', strtotime('-30 days')));
        $endTime      = $request->param('end_time', date('Y-m-d'));
        $groupBy      = $request->param('group_by', '1d');

        if (empty($parkingLotId)) {
            return json(['code' => 1, 'msg' => '停车场ID不能为空']);
        }

        // 查询占用率历史数据
        $occupancyData = $this->influxDBService->getOccupancyRateHistory(
            $parkingLotId,
            $startTime,
            $endTime,
            $groupBy
        );

        // 处理数据，格式化时间和占用率
        $processedData = [];
        foreach ($occupancyData as $record) {
            $time = isset($record['_time']) ? date('Y-m-d H:i:s', strtotime($record['_time'])) : '';
            $rate = $record['_value'] ?? 0;

            $processedData[] = [
                'time'           => $time,
                'occupancy_rate' => $rate
            ];
        }

        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => $processedData
        ]);
    }

    /**
     * 生成停车时长统计数据
     * 
     * @param Request $request
     * @return \think\Response
     */
    public function generateDurationStats(Request $request)
    {
        $parkingLotId = $request->param('parking_lot_id');
        $date         = $request->param('date', date('Y-m-d'));

        if (empty($parkingLotId)) {
            return json(['code' => 1, 'msg' => '停车场ID不能为空']);
        }

        // 查询当天完成的停车记录
        $startTime = $date . ' 00:00:00';
        $endTime   = $date . ' 23:59:59';

        $records = ParkingRecord::where('status', ParkingRecord::STATUS_COMPLETED)
            ->where('exit_time', 'between', [$startTime, $endTime])
            ->select();

        // 统计不同时长的停车数量
        $shortTermCount  = 0;  // < 1小时
        $mediumTermCount = 0; // 1-4小时
        $longTermCount   = 0;   // > 4小时
        $totalDuration   = 0;
        $maxDuration     = 0;
        $minDuration     = PHP_INT_MAX;

        foreach ($records as $record) {
            $duration = $record->duration ?? 0;

            if ($duration < 60) { // 小于1小时
                $shortTermCount++;
            } elseif ($duration < 240) { // 1-4小时
                $mediumTermCount++;
            } else { // 大于4小时
                $longTermCount++;
            }

            $totalDuration += $duration;
            $maxDuration   = max($maxDuration, $duration);
            $minDuration   = min($minDuration, $duration);
        }

        $avgDuration = count($records) > 0 ? $totalDuration / count($records) : 0;
        $minDuration = $minDuration == PHP_INT_MAX ? 0 : $minDuration;

        // 准备数据
        $durationData = [
            'short_term_count'  => $shortTermCount,
            'medium_term_count' => $mediumTermCount,
            'long_term_count'   => $longTermCount,
            'avg_duration'      => $avgDuration,
            'max_duration'      => $maxDuration,
            'min_duration'      => $minDuration,
            'timestamp'         => strtotime($date),
            'time_period'       => 'daily',
            'vehicle_type'      => 'all',
            'user_type'         => 'all'
        ];

        // 写入InfluxDB
        $result = $this->influxDBService->writeParkingDurationStats($parkingLotId, $durationData);

        return json([
            'code' => $result ? 0 : 1,
            'msg'  => $result ? '统计成功' : '统计失败',
            'data' => $durationData
        ]);
    }

    /**
     * 记录高峰期分析数据
     * 
     * @param Request $request
     * @return \think\Response
     */
    public function recordPeakHoursData(Request $request)
    {
        $parkingLotId = $request->param('parking_lot_id');
        $date         = $request->param('date', date('Y-m-d'));
        $timeSlot     = $request->param('time_slot', 'hourly');

        if (empty($parkingLotId)) {
            return json(['code' => 1, 'msg' => '停车场ID不能为空']);
        }

        // 确定时间范围
        $startTime = $date . ' 00:00:00';
        $endTime   = $date . ' 23:59:59';

        // 获取进出场记录
        $entryCount = ParkingRecord::where('entry_time', 'between', [$startTime, $endTime])->count();
        $exitCount  = ParkingRecord::where('exit_time', 'between', [$startTime, $endTime])->count();

        // 获取平均停车时长
        $records = ParkingRecord::where('status', ParkingRecord::STATUS_COMPLETED)
            ->where('exit_time', 'between', [$startTime, $endTime])
            ->select();

        $totalDuration = 0;
        foreach ($records as $record) {
            $totalDuration += $record->duration ?? 0;
        }

        $avgParkingTime = count($records) > 0 ? $totalDuration / count($records) : 0;

        // 获取车位占用率
        $parkingLot    = ParkingLot::find($parkingLotId);
        $occupancyRate = 0;

        if ($parkingLot) {
            $occupancyRate = $parkingLot->getOccupancyRate();
        }

        // 计算周转率（每个车位平均使用次数）
        $totalSpaces  = $parkingLot ? $parkingLot->total_spaces : 1;
        $turnoverRate = $exitCount / $totalSpaces;

        // 准备数据
        $peakData = [
            'entry_count'      => $entryCount,
            'exit_count'       => $exitCount,
            'occupancy_rate'   => $occupancyRate,
            'avg_parking_time' => $avgParkingTime,
            'turnover_rate'    => $turnoverRate,
            'timestamp'        => strtotime($date),
            'day_type'         => date('N', strtotime($date)) >= 6 ? 'weekend' : 'weekday',
            'time_slot'        => $timeSlot
        ];

        // 写入InfluxDB
        $result = $this->influxDBService->writePeakHoursData($parkingLotId, $peakData);

        return json([
            'code' => $result ? 0 : 1,
            'msg'  => $result ? '记录成功' : '记录失败',
            'data' => $peakData
        ]);
    }

    /**
     * 获取设备状态历史数据
     * 
     * @param Request $request
     * @return \think\Response
     */
    public function getDeviceStatusHistory(Request $request)
    {
        $deviceId  = $request->param('device_id');
        $startTime = $request->param('start_time', date('Y-m-d', strtotime('-7 days')));
        $endTime   = $request->param('end_time', date('Y-m-d'));

        if (empty($deviceId)) {
            return json(['code' => 1, 'msg' => '设备ID不能为空']);
        }

        $device = GateDevice::find($deviceId);
        if (!$device) {
            return json(['code' => 1, 'msg' => '设备不存在']);
        }

        // 查询设备状态历史数据
        $statusData = $this->influxDBService->getDeviceStatusHistory($deviceId, $startTime, $endTime);

        // 处理数据，格式化时间和状态
        $processedData = [];
        foreach ($statusData as $record) {
            $time  = isset($record['_time']) ? date('Y-m-d H:i:s', strtotime($record['_time'])) : '';
            $field = $record['_field'] ?? '';
            $value = $record['_value'] ?? null;

            if (!isset($processedData[$time])) {
                $processedData[$time] = [
                    'time'        => $time,
                    'device_id'   => $deviceId,
                    'device_type' => $device->type,
                    'location'    => $device->location
                ];
            }

            if ($field && $value !== null) {
                $processedData[$time][$field] = $value;
            }
        }

        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => array_values($processedData)
        ]);
    }

    /**
     * 诊断InfluxDB连接和配置问题
     * 
     * @return \think\Response
     */
    public function diagnoseInfluxDB()
    {
        // 执行诊断
        $diagnosticResult = $this->influxDBService->diagnose();

        // 检查.env文件中的配置
        $envConfig = [
            'INFLUXDB_ENABLED' => env('INFLUXDB_ENABLED', 'false'),
            'INFLUXDB_URL'     => env('INFLUXDB_URL', 'not_set'),
            'INFLUXDB_TOKEN'   => env('INFLUXDB_TOKEN') ? '已设置' : '未设置',
            'INFLUXDB_BUCKET'  => env('INFLUXDB_BUCKET', 'not_set'),
            'INFLUXDB_ORG'     => env('INFLUXDB_ORG', 'not_set'),
        ];

        // 获取日志文件路径
        $logPath = runtime_path('log');

        return json([
            'code' => 0,
            'msg'  => 'InfluxDB诊断完成',
            'data' => [
                'diagnostic_result' => $diagnosticResult,
                'env_config'        => $envConfig,
                'log_path'          => $logPath,
                'server_info'       => [
                    'php_version'     => PHP_VERSION,
                    'os'              => PHP_OS,
                    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
                    'hostname'        => gethostname(),
                ]
            ]
        ]);
    }
}