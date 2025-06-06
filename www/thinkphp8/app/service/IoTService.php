<?php
declare(strict_types=1);

namespace app\service;

use app\model\IoTData;
use think\facade\Log;
use think\facade\Cache;

class IoTService
{
    /**
     * 保存设备数据
     * 
     * @param array $data 设备数据
     * @return bool
     */
    public function saveData(array $data): bool
    {
        try {
            // 验证数据
            if (empty($data['device_id'])) {
                throw new \Exception('设备ID不能为空');
            }

            // 记录日志
            Log::info('保存IoT设备数据: {device_id}', ['device_id' => $data['device_id']]);

            // 保存数据
            return IoTData::create($data) ? true : false;
        } catch (\Exception $e) {
            Log::error('保存IoT设备数据失败: {message}, 数据: {data}', ['data' => $data, 'message' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * 批量保存设备数据
     * 
     * @param array $dataList 设备数据列表
     * @return bool
     */
    public function batchSaveData(array $dataList): bool
    {
        try {
            // 记录日志
            Log::info('批量保存IoT设备数据: {count}条数据', ['count' => count($dataList)]);

            // 批量保存数据
            return IoTData::batchSave($dataList);
        } catch (\Exception $e) {
            Log::error('批量保存IoT设备数据失败: {message}', ['message' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * 获取设备历史数据
     * 
     * @param string $deviceId 设备ID
     * @param string $startTime 开始时间
     * @param string $endTime 结束时间
     * @param int $page 页码
     * @param int $limit 每页条数
     * @return array
     */
    public function getHistoryData(string $deviceId, string $startTime, string $endTime, int $page = 1, int $limit = 20): array
    {
        try {
            // 缓存键
            $cacheKey = "iot:history:{$deviceId}:{$startTime}:{$endTime}:{$page}:{$limit}";

            // 优先从缓存获取
            if (Cache::has($cacheKey)) {
                return Cache::get($cacheKey);
            }

            // 记录日志
            Log::info('查询设备历史数据: {device_id}, 时间范围: {time_range}, 页码: {page}, 每页条数: {limit}', [
                'device_id'  => $deviceId,
                'time_range' => json_encode([$startTime, $endTime]),
                'page'       => $page,
                'limit'      => $limit
            ]);

            // 查询数据
            $data = IoTData::getDataByTimeRange(
                $deviceId,
                $startTime,
                $endTime,
                $page,
                $limit
            );

            // 缓存结果，5分钟过期
            Cache::set($cacheKey, $data, 300);

            return $data;
        } catch (\Exception $e) {
            Log::error('查询设备历史数据失败: {message}, 设备ID: {device_id}', [
                'device_id' => $deviceId,
                'message'   => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * 获取设备最新数据
     * 
     * @param string $deviceId 设备ID
     * @return array|null
     */
    public function getLatestData(string $deviceId): ?array
    {
        try {
            // 缓存键
            $cacheKey = "iot:latest:{$deviceId}";

            // 优先从缓存获取
            if (Cache::has($cacheKey)) {
                return Cache::get($cacheKey);
            }

            // 记录日志
            Log::info('获取设备最新数据: {device_id}', ['device_id' => $deviceId]);

            // 查询数据
            $data = IoTData::getLatestData($deviceId);

            // 缓存结果，30秒过期
            if ($data) {
                Cache::set($cacheKey, $data, 30);
            }

            return $data;
        } catch (\Exception $e) {
            Log::error('获取设备最新数据失败: {message}, 设备ID: {device_id}', [
                'device_id' => $deviceId,
                'message'   => $e->getMessage()
            ]);
            return null;
        }
    }
}