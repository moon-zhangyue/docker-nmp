<?php

declare(strict_types=1);

namespace app\service;

use InfluxDB2\Client;
use InfluxDB2\Model\WritePrecision;
use InfluxDB2\Point;
use think\facade\Config;
use think\facade\Log;

/**
 * InfluxDB服务类
 * 
 * 该服务类用于处理时序数据的存储和查询，根据配置的筛选条件决定哪些数据应该存储到InfluxDB中
 */
class InfluxDBService
{
    /**
     * @var Client InfluxDB客户端实例
     */
    protected $client;

    /**
     * @var array InfluxDB配置
     */
    protected $config;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->config = Config::get('influxdb');
        // var_dump($this->config);
        // 只有在启用InfluxDB时才初始化客户端
        if ($this->isEnabled()) {
            $this->initClient();
        }
    }

    /**
     * 初始化InfluxDB客户端
     */
    protected function initClient()
    {
        try {
            // 记录连接参数（不包含敏感信息）
            Log::info('InfluxDB连接参数-url:{url}, bucket:{bucket}, org:{org}, precision:{precision}, enabled:{enabled}', [
                'url'       => $this->config['connection']['url'],
                'bucket'    => $this->config['connection']['bucket'],
                'org'       => $this->config['connection']['org'],
                'precision' => $this->config['connection']['precision'] ?? WritePrecision::NS,
                'enabled'   => $this->config['enabled'],
            ]);

            // 验证必要的连接参数
            if (empty($this->config['connection']['url'])) {
                throw new \InvalidArgumentException('InfluxDB URL不能为空');
            }

            if (empty($this->config['connection']['token'])) {
                throw new \InvalidArgumentException('InfluxDB Token不能为空');
            }

            if (empty($this->config['connection']['bucket'])) {
                throw new \InvalidArgumentException('InfluxDB Bucket不能为空');
            }

            if (empty($this->config['connection']['org'])) {
                throw new \InvalidArgumentException('InfluxDB Organization不能为空');
            }

            $this->client = new Client([
                'url'       => $this->config['connection']['url'],
                'token'     => $this->config['connection']['token'],
                'bucket'    => $this->config['connection']['bucket'],
                'org'       => $this->config['connection']['org'],
                'precision' => $this->config['connection']['precision'] ?? WritePrecision::NS,
                'debug'     => Config::get('app.debug', false),
            ]);

            Log::info('InfluxDB客户端初始化成功');
        } catch (\InvalidArgumentException $e) {
            Log::error('InfluxDB客户端初始化失败：配置参数无效:error-{error},trace-{trace}', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        } catch (\Exception $e) {
            Log::error('InfluxDB客户端初始化失败', [
                'error' => $e->getMessage(),
                'code'  => $e->getCode(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * 检查InfluxDB是否已启用
     * 
     * @return bool
     */
    public function isEnabled(): bool
    {
        return (bool) $this->config['enabled'] ?? false;
    }

    /**
     * 检查数据是否符合存储到InfluxDB的条件
     * 
     * @param string $dataType 数据类型
     * @param string $useCase 使用场景
     * @param string $metricName 指标名称
     * @return bool
     */
    public function shouldStoreInInfluxDB(string $dataType, string $useCase, string $metricName): bool
    {
        // 如果InfluxDB未启用，直接返回false
        if (!$this->isEnabled()) {
            return false;
        }

        // 检查数据类型是否适合存储到InfluxDB
        if (!$this->isDataTypeSupported($dataType)) {
            return false;
        }

        // 检查使用场景和指标是否适合存储到InfluxDB
        return $this->isUseCaseMetricSupported($useCase, $metricName);
    }

    /**
     * 检查数据类型是否支持存储到InfluxDB
     * 
     * @param string $dataType 数据类型
     * @return bool
     */
    protected function isDataTypeSupported(string $dataType): bool
    {
        $supportedTypes = $this->config['storage_criteria']['data_types'] ?? [];
        return isset($supportedTypes[$dataType]) && $supportedTypes[$dataType] === true;
    }

    /**
     * 检查使用场景和指标是否支持存储到InfluxDB
     * 
     * @param string $useCase 使用场景
     * @param string $metricName 指标名称
     * @return bool
     */
    protected function isUseCaseMetricSupported(string $useCase, string $metricName): bool
    {
        $useCases = $this->config['storage_criteria']['use_cases'] ?? [];

        // 检查使用场景是否存在
        if (!isset($useCases[$useCase])) {
            return false;
        }

        // 检查指标是否在该使用场景中支持
        return isset($useCases[$useCase][$metricName]) && $useCases[$useCase][$metricName] === true;
    }

    /**
     * 写入数据点到InfluxDB
     * 
     * @param string $measurement 测量名称
     * @param array $tags 标签
     * @param array $fields 字段
     * @param int|null $timestamp 时间戳（毫秒）
     * @return bool 是否写入成功
     */
    public function writePoint(string $measurement, array $tags, array $fields, ?int $timestamp = null): bool
    {
        if (!$this->isEnabled()) {
            Log::info('InfluxDB未启用，跳过写入操作');
            return false;
        }

        if (!$this->client) {
            Log::error('InfluxDB客户端未初始化，无法写入数据');
            return false;
        }

        try {
            // 记录写入操作的基本信息
            Log::debug('准备写入InfluxDB数据 - measurement:{measurement}, tags_count:{tags_count}, fields_count:{fields_count}, timestamp:{timestamp}', [
                'measurement'  => $measurement,
                'tags_count'   => count($tags),
                'fields_count' => count($fields),
                'timestamp'    => $timestamp
            ]);

            // 创建数据点
            $point = Point::measurement($measurement);

            // 添加标签
            foreach ($tags as $key => $value) {
                if ($value === null || $value === '') {
                    Log::warning('InfluxDB标签值为空 - tag_key:{tag_key}, measurement:{measurement}', ['tag_key' => $key, 'measurement' => $measurement]);
                    $value = 'unknown'; // 使用默认值避免写入失败
                }
                $point->addTag($key, (string) $value);
            }

            // 添加字段
            $fieldCount = 0;
            foreach ($fields as $key => $value) {
                if ($value === null) {
                    Log::warning('InfluxDB字段值为null，已跳过 - field_key:{field_key}, measurement:{measurement}', ['field_key' => $key, 'measurement' => $measurement]);
                    continue; // 跳过null值
                }

                if (is_int($value)) {
                    $point->addField($key, $value);
                    $fieldCount++;
                } elseif (is_float($value)) {
                    $point->addField($key, $value);
                    $fieldCount++;
                } elseif (is_bool($value)) {
                    $point->addField($key, $value);
                    $fieldCount++;
                } elseif (is_string($value)) {
                    $point->addField($key, $value);
                    $fieldCount++;
                } else {
                    Log::warning('InfluxDB不支持的字段类型 - field_key:{field_key}, field_type:{field_type}, measurement:{measurement}', [
                        'field_key'   => $key,
                        'field_type'  => gettype($value),
                        'measurement' => $measurement
                    ]);
                }
            }

            // 检查是否有有效字段
            if ($fieldCount === 0) {
                Log::error('InfluxDB写入失败：没有有效的字段值 - measurement:{measurement}', [
                    'measurement' => $measurement,
                    'tags'        => $tags
                ]);
                return false;
            }

            // 设置时间戳（如果提供）
            if ($timestamp !== null) {
                $point->time($timestamp);
            }

            // 获取写入API
            $writeApi = $this->client->createWriteApi([
                'writeType'     => $this->config['batch']['enabled'] ? 1 : 2, // 1=批量写入，2=同步写入
                'batchSize'     => $this->config['batch']['size'] ?? 1000,
                'flushInterval' => $this->config['batch']['interval'] ?? 1000,
            ]);

            // 写入数据点
            $writeApi->write($point);

            // 如果不是批量写入，则关闭写入API
            if (!$this->config['batch']['enabled']) {
                $writeApi->close();
            }

            Log::info('InfluxDB写入成功 - measurement:{measurement}', ['measurement' => $measurement]);
            return true;
        } catch (\InfluxDB2\ApiException $e) {
            // InfluxDB API异常，通常是认证或权限问题
            Log::error('InfluxDB API异常:error-{error},code-{code},measurement-{measurement},response-{response},headers-{headers}', [
                'error'       => $e->getMessage(),
                'code'        => $e->getCode(),
                'measurement' => $measurement,
                'response'    => $e->getResponseBody(),
                'headers'     => $e->getResponseHeaders()
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error('InfluxDB写入失败 - error:{error}, code:{code}, class:{class}, measurement:{measurement}', [
                'error'       => $e->getMessage(),
                'code'        => $e->getCode(),
                'class'       => get_class($e),
                'measurement' => $measurement,
                'tags'        => $tags,
                'fields'      => $fields,
                'trace'       => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * 写入系统监控指标
     * 
     * @param array $metrics 监控指标数据
     * @return bool 是否写入成功
     */
    public function writeSystemMetrics(array $metrics): bool
    {
        if (!$this->shouldStoreInInfluxDB('metrics', 'system_monitoring', 'cpu_usage')) {
            return false;
        }

        $tags = [
            'host'        => gethostname(),
            'environment' => Config::get('app.environment', 'production'),
        ];

        $fields = [];

        // CPU指标
        if (isset($metrics['cpu'])) {
            foreach ($metrics['cpu'] as $key => $value) {
                $fields['cpu_' . $key] = $value;
            }
        }

        // 内存指标
        if (isset($metrics['memory'])) {
            foreach ($metrics['memory'] as $key => $value) {
                if (is_array($value)) {
                    foreach ($value as $subKey => $subValue) {
                        $fields['memory_' . $key . '_' . $subKey] = $subValue;
                    }
                } else {
                    $fields['memory_' . $key] = $value;
                }
            }
        }

        // 磁盘指标
        if (isset($metrics['disk'])) {
            foreach ($metrics['disk'] as $key => $value) {
                $fields['disk_' . $key] = $value;
            }
        }

        return $this->writePoint('system_metrics', $tags, $fields);
    }

    /**
     * 写入队列监控指标
     * 
     * @param string $queue 队列名称
     * @param array $metrics 队列指标数据
     * @return bool 是否写入成功
     */
    public function writeQueueMetrics(string $queue, array $metrics): bool
    {
        if (!$this->shouldStoreInInfluxDB('metrics', 'queue_monitoring', 'queue_length')) {
            return false;
        }

        $tags = [
            'queue'       => $queue,
            'host'        => gethostname(),
            'environment' => Config::get('app.environment', 'production'),
        ];

        $fields = [
            'waiting'      => $metrics['waiting'] ?? 0,
            'processing'   => $metrics['processing'] ?? 0,
            'failed'       => $metrics['failed'] ?? 0,
            'delayed'      => $metrics['delayed'] ?? 0,
            'total'        => $metrics['total'] ?? 0,
            'success_rate' => isset($metrics['success']) && isset($metrics['failed']) && ($metrics['success'] + $metrics['failed'] > 0)
                ? ($metrics['success'] / ($metrics['success'] + $metrics['failed'])) * 100
                : 0,
        ];

        return $this->writePoint('queue_metrics', $tags, $fields);
    }

    /**
     * 写入停车场事件数据
     * 
     * @param string $eventType 事件类型（entry, exit）
     * @param array $eventData 事件数据
     * @return bool 是否写入成功
     */
    public function writeParkingEvent(string $eventType, array $eventData): bool
    {
        if (!$this->shouldStoreInInfluxDB('events', 'parking_system', 'gate_events')) {
            return false;
        }

        $tags = [
            'event_type'     => $eventType,
            'parking_lot_id' => $eventData['parking_lot_id'] ?? '',
            'gate_id'        => $eventData['gate_id'] ?? '',
            'vehicle_type'   => $eventData['vehicle_type'] ?? 'car',
            'device_type'    => $eventData['device_type'] ?? '',
            'payment_status' => $eventData['payment_status'] ?? '',
        ];

        $fields = [
            'plate_number'     => $eventData['plate_number'] ?? '',
            'timestamp'        => $eventData['timestamp'] ?? time(),
            'success'          => $eventData['success'] ?? true,
            'error_message'    => $eventData['error_message'] ?? '',
            'duration'         => $eventData['duration'] ?? 0,
            'fee'              => $eventData['fee'] ?? 0.00,
            'recognition_time' => $eventData['recognition_time'] ?? 0,
        ];

        return $this->writePoint('parking_events', $tags, $fields, $eventData['timestamp'] ?? null);
    }

    /**
     * 写入车位占用率数据
     * 
     * @param string $parkingLotId 停车场ID
     * @param array $occupancyData 占用率数据
     * @return bool 是否写入成功
     */
    public function writeOccupancyRate(string $parkingLotId, array $occupancyData): bool
    {
        if (!$this->shouldStoreInInfluxDB('metrics', 'parking_system', 'occupancy_rate')) {
            return false;
        }

        $tags = [
            'parking_lot_id' => $parkingLotId,
            'environment'    => Config::get('app.environment', 'production'),
            'area'           => $occupancyData['area'] ?? 'main',
        ];

        $fields = [
            'total_spaces'     => $occupancyData['total_spaces'] ?? 0,
            'occupied_spaces'  => $occupancyData['occupied_spaces'] ?? 0,
            'occupancy_rate'   => $occupancyData['occupancy_rate'] ?? 0.0,
            'available_spaces' => $occupancyData['available_spaces'] ?? 0,
        ];

        return $this->writePoint('parking_occupancy', $tags, $fields, $occupancyData['timestamp'] ?? null);
    }

    /**
     * 写入设备状态数据
     * 
     * @param string $deviceId 设备ID
     * @param array $statusData 状态数据
     * @return bool 是否写入成功
     */
    public function writeDeviceStatus(string $deviceId, array $statusData): bool
    {
        if (!$this->shouldStoreInInfluxDB('device_telemetry', 'parking_system', 'gate_events')) {
            return false;
        }

        $tags = [
            'device_id'      => $deviceId,
            'device_type'    => $statusData['device_type'] ?? '',
            'parking_lot_id' => $statusData['parking_lot_id'] ?? '',
            'location'       => $statusData['location'] ?? '',
        ];

        $fields = [
            'status'         => $statusData['status'] ?? 0,
            'is_online'      => $statusData['is_online'] ?? false,
            'last_heartbeat' => $statusData['last_heartbeat'] ?? time(),
            'response_time'  => $statusData['response_time'] ?? 0,
            'error_count'    => $statusData['error_count'] ?? 0,
            'battery_level'  => $statusData['battery_level'] ?? 100,
            'temperature'    => $statusData['temperature'] ?? 0,
        ];

        return $this->writePoint('device_status', $tags, $fields, $statusData['timestamp'] ?? null);
    }

    /**
     * 写入车牌识别性能数据
     * 
     * @param string $deviceId 设备ID
     * @param array $recognitionData 识别数据
     * @return bool 是否写入成功
     */
    public function writePlateRecognitionMetrics(string $deviceId, array $recognitionData): bool
    {
        if (!$this->shouldStoreInInfluxDB('metrics', 'parking_system', 'gate_events')) {
            return false;
        }

        $tags = [
            'device_id'      => $deviceId,
            'device_type'    => $recognitionData['device_type'] ?? '',
            'parking_lot_id' => $recognitionData['parking_lot_id'] ?? '',
            'camera_type'    => $recognitionData['camera_type'] ?? 'standard',
        ];

        $fields = [
            'recognition_time' => $recognitionData['recognition_time'] ?? 0,
            'confidence'       => $recognitionData['confidence'] ?? 0.0,
            'success'          => $recognitionData['success'] ?? true,
            'error_type'       => $recognitionData['error_type'] ?? '',
            'light_condition'  => $recognitionData['light_condition'] ?? 'normal',
        ];

        return $this->writePoint('plate_recognition_metrics', $tags, $fields, $recognitionData['timestamp'] ?? null);
    }

    /**
     * 写入高峰期分析数据
     * 
     * @param string $parkingLotId 停车场ID
     * @param array $peakData 高峰期数据
     * @return bool 是否写入成功
     */
    public function writePeakHoursData(string $parkingLotId, array $peakData): bool
    {
        if (!$this->shouldStoreInInfluxDB('metrics', 'parking_system', 'peak_hours')) {
            return false;
        }

        $tags = [
            'parking_lot_id' => $parkingLotId,
            'day_type'       => $peakData['day_type'] ?? 'weekday', // weekday, weekend, holiday
            'time_slot'      => $peakData['time_slot'] ?? 'hourly', // hourly, daily, weekly
        ];

        $fields = [
            'entry_count'      => $peakData['entry_count'] ?? 0,
            'exit_count'       => $peakData['exit_count'] ?? 0,
            'occupancy_rate'   => $peakData['occupancy_rate'] ?? 0.0,
            'avg_parking_time' => $peakData['avg_parking_time'] ?? 0,
            'turnover_rate'    => $peakData['turnover_rate'] ?? 0.0,
        ];

        return $this->writePoint('peak_hours_analysis', $tags, $fields, $peakData['timestamp'] ?? null);
    }

    /**
     * 写入停车时长统计数据
     * 
     * @param string $parkingLotId 停车场ID
     * @param array $durationData 时长统计数据
     * @return bool 是否写入成功
     */
    public function writeParkingDurationStats(string $parkingLotId, array $durationData): bool
    {
        if (!$this->shouldStoreInInfluxDB('metrics', 'parking_system', 'parking_duration')) {
            return false;
        }

        $tags = [
            'parking_lot_id' => $parkingLotId,
            'vehicle_type'   => $durationData['vehicle_type'] ?? 'car',
            'user_type'      => $durationData['user_type'] ?? 'visitor', // visitor, monthly, employee
            'time_period'    => $durationData['time_period'] ?? 'daily', // hourly, daily, weekly, monthly
        ];

        $fields = [
            'short_term_count'  => $durationData['short_term_count'] ?? 0, // < 1小时
            'medium_term_count' => $durationData['medium_term_count'] ?? 0, // 1-4小时
            'long_term_count'   => $durationData['long_term_count'] ?? 0, // > 4小时
            'avg_duration'      => $durationData['avg_duration'] ?? 0,
            'max_duration'      => $durationData['max_duration'] ?? 0,
            'min_duration'      => $durationData['min_duration'] ?? 0,
        ];

        return $this->writePoint('parking_duration_stats', $tags, $fields, $durationData['timestamp'] ?? null);
    }

    /**
     * 写入闸机操作日志
     * 
     * @param string $deviceId 设备ID
     * @param array $operationData 操作数据
     * @return bool 是否写入成功
     */
    public function writeGateOperation(string $deviceId, array $operationData): bool
    {
        if (!$this->shouldStoreInInfluxDB('events', 'parking_system', 'gate_events')) {
            return false;
        }

        $tags = [
            'device_id'      => $deviceId,
            'operation_type' => $operationData['operation_type'] ?? '', // open, close, reset
            'trigger_source' => $operationData['trigger_source'] ?? '', // auto, manual, remote
            'parking_lot_id' => $operationData['parking_lot_id'] ?? '',
        ];

        $fields = [
            'plate_number'  => $operationData['plate_number'] ?? '',
            'operator'      => $operationData['operator'] ?? 'system',
            'success'       => $operationData['success'] ?? true,
            'error_message' => $operationData['error_message'] ?? '',
            'response_time' => $operationData['response_time'] ?? 0,
        ];

        return $this->writePoint('gate_operations', $tags, $fields, $operationData['timestamp'] ?? null);
    }

    /**
     * 查询InfluxDB数据
     * 
     * @param string $query Flux查询语句
     * @return array 查询结果
     */
    public function query(string $query): array
    {
        if (!$this->isEnabled()) {
            Log::info('InfluxDB未启用，跳过查询操作');
            return [];
        }

        if (!$this->client) {
            Log::error('InfluxDB客户端未初始化，无法执行查询');
            return [];
        }

        try {
            Log::debug('执行InfluxDB查询 - query:{query}', ['query' => $query]);
            $queryApi = $this->client->createQueryApi();
            $result   = $queryApi->query($query);

            // 处理查询结果
            $data = [];
            foreach ($result as $table) {
                foreach ($table->records as $record) {
                    $data[] = $record->values;
                }
            }

            Log::info('InfluxDB查询成功 - records_count:{records_count}', ['records_count' => count($data)]);
            return $data;
        } catch (\InfluxDB2\ApiException $e) {
            // InfluxDB API异常，通常是认证或权限问题
            Log::error('InfluxDB API查询异常:error-{error}, code-{code}, query-{query}, response-{response}, headers-{headers}', [
                'error'    => $e->getMessage(),
                'code'     => $e->getCode(),
                'query'    => $query,
                'response' => $e->getResponseBody(),
                'headers'  => $e->getResponseHeaders()
            ]);
            return [];
        } catch (\Exception $e) {
            Log::error('InfluxDB查询失败 - error:{error}, code:{code}, class:{class}, query:{query}', [
                'error' => $e->getMessage(),
                'code'  => $e->getCode(),
                'class' => get_class($e),
                'query' => $query,
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    /**
     * 获取停车场高峰期分析数据
     * 
     * @param string $parkingLotId 停车场ID
     * @param string $startTime 开始时间
     * @param string $endTime 结束时间
     * @param string $groupBy 分组方式 (1h, 1d, 1w)
     * @return array 高峰期分析数据
     */
    public function getPeakHoursAnalysis(string $parkingLotId, string $startTime, string $endTime, string $groupBy = '1h'): array
    {
        if (!$this->isEnabled() || !$this->client) {
            return [];
        }

        $query = 'from(bucket: "' . $this->config['connection']['bucket'] . '")'
            . ' |> range(start: ' . $startTime . ', stop: ' . $endTime . ')'
            . ' |> filter(fn: (r) => r._measurement == "parking_events")'
            . ' |> filter(fn: (r) => r.parking_lot_id == "' . $parkingLotId . '")'
            . ' |> aggregateWindow(every: ' . $groupBy . ', fn: count)'
            . ' |> group(columns: ["event_type", "_time"])'
            . ' |> yield(name: "peak_hours")';

        return $this->query($query);
    }

    /**
     * 获取车位占用率历史数据
     * 
     * @param string $parkingLotId 停车场ID
     * @param string $startTime 开始时间
     * @param string $endTime 结束时间
     * @param string $groupBy 分组方式 (1h, 1d, 1w)
     * @return array 占用率历史数据
     */
    public function getOccupancyRateHistory(string $parkingLotId, string $startTime, string $endTime, string $groupBy = '1h'): array
    {
        if (!$this->isEnabled() || !$this->client) {
            return [];
        }

        $query = 'from(bucket: "' . $this->config['connection']['bucket'] . '")'
            . ' |> range(start: ' . $startTime . ', stop: ' . $endTime . ')'
            . ' |> filter(fn: (r) => r._measurement == "parking_occupancy")'
            . ' |> filter(fn: (r) => r.parking_lot_id == "' . $parkingLotId . '")'
            . ' |> filter(fn: (r) => r._field == "occupancy_rate")'
            . ' |> aggregateWindow(every: ' . $groupBy . ', fn: mean)'
            . ' |> yield(name: "occupancy_history")';

        return $this->query($query);
    }

    /**
     * 获取设备状态历史数据
     * 
     * @param string $deviceId 设备ID
     * @param string $startTime 开始时间
     * @param string $endTime 结束时间
     * @return array 设备状态历史数据
     */
    public function getDeviceStatusHistory(string $deviceId, string $startTime, string $endTime): array
    {
        if (!$this->isEnabled() || !$this->client) {
            return [];
        }

        $query = 'from(bucket: "' . $this->config['connection']['bucket'] . '")'
            . ' |> range(start: ' . $startTime . ', stop: ' . $endTime . ')'
            . ' |> filter(fn: (r) => r._measurement == "device_status")'
            . ' |> filter(fn: (r) => r.device_id == "' . $deviceId . '")'
            . ' |> yield(name: "device_history")';

        return $this->query($query);
    }

    /**
     * 关闭InfluxDB客户端连接
     */
    public function close()
    {
        if ($this->client) {
            $this->client->close();
        }
    }

    /**
     * 诊断InfluxDB连接状态
     * 
     * @return array 诊断结果
     */
    public function diagnose(): array
    {
        $result = [
            'enabled'      => $this->isEnabled(),
            'config_valid' => false,
            'connection'   => false,
            'write_test'   => false,
            'errors'       => [],
            'suggestions'  => [],
        ];

        // 检查是否启用
        if (!$result['enabled']) {
            $result['errors'][]      = 'InfluxDB未启用，请检查环境变量INFLUXDB_ENABLED是否设置为true';
            $result['suggestions'][] = '在.env文件中添加：INFLUXDB_ENABLED=true';
            return $result;
        }

        // 检查配置是否有效
        $configErrors = [];
        if (empty($this->config['connection']['url'])) {
            $configErrors[] = 'InfluxDB URL未配置';
        }

        if (empty($this->config['connection']['token'])) {
            $configErrors[] = 'InfluxDB Token未配置';
        }

        if (empty($this->config['connection']['bucket'])) {
            $configErrors[] = 'InfluxDB Bucket未配置';
        }

        if (empty($this->config['connection']['org'])) {
            $configErrors[] = 'InfluxDB Organization未配置';
        }

        if (!empty($configErrors)) {
            $result['errors']        = array_merge($result['errors'], $configErrors);
            $result['suggestions'][] = '请在.env文件中配置以下环境变量：\nINFLUXDB_URL=http://influxdb:8086\nINFLUXDB_TOKEN=your_token\nINFLUXDB_BUCKET=your_bucket\nINFLUXDB_ORG=your_org';
            return $result;
        }

        $result['config_valid'] = true;

        // 检查连接是否成功
        if (!$this->client) {
            $result['errors'][]      = 'InfluxDB客户端初始化失败';
            $result['suggestions'][] = '请检查InfluxDB服务是否运行，并确保URL和认证信息正确';
            return $result;
        }

        // 测试连接
        try {
            // 直接使用Client的health方法获取健康状态
            $health               = $this->client->health();
            $result['connection'] = $health->getStatus() === 'pass';

            if (!$result['connection']) {
                $result['errors'][] = 'InfluxDB健康检查失败：' . $health->getMessage();
            }
        } catch (\Exception $e) {
            $result['errors'][]      = 'InfluxDB连接测试异常：' . $e->getMessage();
            $result['suggestions'][] = '请确保InfluxDB服务正在运行，并且可以从应用服务器访问';
            return $result;
        }

        // 测试写入
        try {
            $testPoint = Point::measurement('connection_test')
                ->addTag('test', 'true')
                ->addField('value', 1);

            $writeApi = $this->client->createWriteApi([
                'writeType' => 2, // 同步写入
            ]);

            $writeApi->write($testPoint);
            $writeApi->close();

            $result['write_test'] = true;
            Log::info('InfluxDB诊断：写入测试成功');
        } catch (\Exception $e) {
            $result['errors'][]      = 'InfluxDB写入测试失败：' . $e->getMessage();
            $result['suggestions'][] = '请检查Token权限是否包含写入权限，以及Bucket是否存在';
        }

        return $result;
    }
}