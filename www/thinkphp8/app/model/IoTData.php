<?php
declare(strict_types=1);

namespace app\model;

use think\Model;
use think\facade\Log;

class IoTData extends Model
{
    // 设置MongoDB连接
    protected $connection = 'mongo';

    // 设置集合名称
    protected $table = 'iot_data';

    // 设置主键
    protected $pk = '_id';

    // 自动时间戳
    protected $autoWriteTimestamp = true;

    /**
     * 批量保存IoT设备数据
     * 
     * @param array $dataList 设备数据列表
     * @return bool
     */
    public static function batchSave(array $dataList): bool
    {
        try {
            // MongoDB 4.0+ 事务需要在副本集环境中使用
            // 不使用事务直接批量插入数据
            // foreach ($dataList as $data) {
            //     self::create($data);
            // }
            // return true;

            // 注意：如果需要使用MongoDB事务，请确保MongoDB版本在4.0以上且为副本集环境
            // 事务示例代码（需要副本集环境）：

            $connection = self::getConnection();
            $connection->startTrans();
            try {
                foreach ($dataList as $data) {
                    self::create($data);
                }
                $connection->commit();
                return true;
            } catch (\Exception $e) {
                $connection->rollback();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('批量保存IoT数据失败: {message}', ['message' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * 根据时间范围分页查询数据
     * 
     * @param string $deviceId 设备ID
     * @param string $startTime 开始时间
     * @param string $endTime 结束时间
     * @param int $page 页码
     * @param int $limit 每页条数
     * @return array
     */
    public static function getDataByTimeRange(string $deviceId, string $startTime, string $endTime, int $page = 1, int $limit = 20): array
    {
        try {
            return self::where('device_id', $deviceId)
                ->where('create_time', 'between', [strtotime($startTime), strtotime($endTime)])
                ->page($page, $limit)
                ->order('create_time', 'desc')
                ->select()
                ->toArray();
        } catch (\Exception $e) {
            Log::error('查询IoT数据失败: {message}, 设备ID: {device_id}, 时间范围: {time_range}', [
                'device_id'  => $deviceId,
                'time_range' => [$startTime, $endTime],
                'message'    => $e->getMessage()
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
    public static function getLatestData(string $deviceId): ?array
    {
        try {
            $data = self::where('device_id', $deviceId)
                ->order('create_time', 'desc')
                ->find();

            return $data ? $data->toArray() : null;
        } catch (\Exception $e) {
            Log::error('获取设备最新数据失败: {message}, 设备ID: {device_id}', [
                'device_id' => $deviceId,
                'message'   => $e->getMessage()
            ]);
            return null;
        }
    }
}