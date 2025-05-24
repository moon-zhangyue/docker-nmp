<?php
declare(strict_types=1);

namespace app\model;

use think\Model;
use think\facade\Log;
use think\exception\DbException;

/**
 * 全球数据复制模型 - 副本集与分片特性
 * 支持高可用性和分布式存储，适合全球化应用
 */
class GlobalDataReplication extends Model
{
    // 设置MongoDB连接
    protected $connection = 'mongo';

    // 设置集合名称
    protected $table = 'global_data';

    // 设置主键
    protected $pk = '_id';

    // 自动时间戳
    protected $autoWriteTimestamp = true;

    // JSON字段
    protected $json = ['data', 'metadata', 'replication_info'];

    // 读偏好设置
    protected $readPreference = 'secondaryPreferred';

    /**
     * 创建全球数据记录（支持多区域复制）
     * 
     * @param array $data 数据内容
     * @param string $region 区域标识
     * @param int $priority 优先级
     * @return array|false
     */
    public static function createGlobalData(array $data, string $region = 'default', int $priority = 1)
    {
        try {
            Log::info('创建全球数据记录', [
                'region'    => $region,
                'priority'  => $priority,
                'data_type' => $data['type'] ?? 'unknown'
            ]);

            $globalData = [
                'data'             => $data,
                'region'           => $region,
                'priority'         => $priority,
                'status'           => 'active',
                'replication_info' => [
                    'created_region'     => $region,
                    'replicated_regions' => [$region],
                    'last_sync_time'     => time(),
                    'sync_status'        => 'synced'
                ],
                'metadata'         => [
                    'version'    => 1,
                    'checksum'   => md5(json_encode($data)),
                    'size'       => strlen(json_encode($data)),
                    'created_by' => $data['created_by'] ?? 'system'
                ],
                'created_at'       => time(),
                'updated_at'       => time()
            ];

            $result = self::create($globalData);

            if ($result) {
                Log::info('全球数据记录创建成功', [
                    'record_id' => $result->_id,
                    'region'    => $region
                ]);

                // 异步触发复制到其他区域
                self::triggerReplication($result->_id, $region);

                return $result->toArray();
            }

            return false;

        } catch (\Exception $e) {
            Log::error('创建全球数据记录失败', [
                'data'   => $data,
                'region' => $region,
                'error'  => $e->getMessage(),
                'trace'  => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * 根据区域获取数据（读偏好优化）
     * 
     * @param string $region 区域标识
     * @param int $limit 限制数量
     * @param string $status 状态过滤
     * @return array
     */
    public static function getDataByRegion(string $region, int $limit = 100, string $status = 'active'): array
    {
        try {
            Log::info('根据区域获取数据', [
                'region' => $region,
                'limit'  => $limit,
                'status' => $status
            ]);

            // 优先从本区域读取，如果没有则从副本读取
            $query = self::where('region', $region)
                ->where('status', $status)
                ->limit($limit)
                ->order('priority', 'desc')
                ->order('updated_at', 'desc');

            $result = $query->select()->toArray();

            // 如果本区域没有数据，尝试从复制数据中获取
            if (empty($result)) {
                Log::info('本区域无数据，尝试从复制数据获取', ['region' => $region]);

                $result = self::where('replication_info->replicated_regions', 'like', '%' . $region . '%')
                    ->where('status', $status)
                    ->limit($limit)
                    ->order('priority', 'desc')
                    ->order('updated_at', 'desc')
                    ->select()
                    ->toArray();
            }

            Log::info('区域数据获取完成', [
                'region'       => $region,
                'result_count' => count($result)
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('根据区域获取数据失败', [
                'region' => $region,
                'limit'  => $limit,
                'error'  => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * 同步数据到指定区域
     * 
     * @param string $recordId 记录ID
     * @param string $targetRegion 目标区域
     * @return bool
     */
    public static function syncToRegion(string $recordId, string $targetRegion): bool
    {
        try {
            Log::info('同步数据到区域', [
                'record_id'     => $recordId,
                'target_region' => $targetRegion
            ]);

            $record = self::find($recordId);
            if (!$record) {
                throw new \Exception('记录不存在');
            }

            // 检查是否已经复制到目标区域
            $replicatedRegions = $record->replication_info['replicated_regions'] ?? [];
            if (in_array($targetRegion, $replicatedRegions)) {
                Log::info('数据已存在于目标区域', [
                    'record_id'     => $recordId,
                    'target_region' => $targetRegion
                ]);
                return true;
            }

            // 更新复制信息
            $replicatedRegions[]                   = $targetRegion;
            $replicationInfo                       = $record->replication_info;
            $replicationInfo['replicated_regions'] = $replicatedRegions;
            $replicationInfo['last_sync_time']     = time();
            $replicationInfo['sync_status']        = 'syncing';

            $result = $record->save([
                'replication_info' => $replicationInfo,
                'updated_at'       => time()
            ]);

            if ($result) {
                // 模拟异步复制过程
                self::performAsyncReplication($recordId, $targetRegion);

                Log::info('数据同步启动成功', [
                    'record_id'     => $recordId,
                    'target_region' => $targetRegion
                ]);
                return true;
            }

            return false;

        } catch (\Exception $e) {
            Log::error('同步数据到区域失败', [
                'record_id'     => $recordId,
                'target_region' => $targetRegion,
                'error'         => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 获取复制状态
     * 
     * @param string $recordId 记录ID
     * @return array|null
     */
    public static function getReplicationStatus(string $recordId): ?array
    {
        try {
            Log::info('获取复制状态', ['record_id' => $recordId]);

            $record = self::find($recordId);
            if (!$record) {
                return null;
            }

            $replicationInfo = $record->replication_info ?? [];
            $status          = [
                'record_id'          => $recordId,
                'created_region'     => $replicationInfo['created_region'] ?? 'unknown',
                'replicated_regions' => $replicationInfo['replicated_regions'] ?? [],
                'last_sync_time'     => $replicationInfo['last_sync_time'] ?? 0,
                'sync_status'        => $replicationInfo['sync_status'] ?? 'unknown',
                'replication_lag'    => time() - ($replicationInfo['last_sync_time'] ?? 0),
                'is_healthy'         => (time() - ($replicationInfo['last_sync_time'] ?? 0)) < 300 // 5分钟内同步认为健康
            ];

            Log::info('复制状态获取完成', $status);
            return $status;

        } catch (\Exception $e) {
            Log::error('获取复制状态失败', [
                'record_id' => $recordId,
                'error'     => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * 故障转移处理
     * 
     * @param string $failedRegion 故障区域
     * @param string $backupRegion 备份区域
     * @return bool
     */
    public static function handleFailover(string $failedRegion, string $backupRegion): bool
    {
        try {
            Log::warning('开始故障转移处理', [
                'failed_region' => $failedRegion,
                'backup_region' => $backupRegion
            ]);

            // 查找受影响的记录
            $affectedRecords = self::where('region', $failedRegion)
                ->where('status', 'active')
                ->select()
                ->toArray();

            $successCount = 0;
            $totalCount   = count($affectedRecords);

            foreach ($affectedRecords as $record) {
                try {
                    // 检查备份区域是否有副本
                    $replicatedRegions = $record['replication_info']['replicated_regions'] ?? [];

                    if (in_array($backupRegion, $replicatedRegions)) {
                        // 更新主区域为备份区域
                        $updateResult = self::where('_id', $record['_id'])->update([
                            'region'                  => $backupRegion,
                            'metadata->failover_info' => [
                                'original_region' => $failedRegion,
                                'failover_time'   => time(),
                                'failover_reason' => 'region_failure'
                            ],
                            'updated_at'              => time()
                        ]);

                        if ($updateResult) {
                            $successCount++;
                        }
                    } else {
                        Log::warning('记录在备份区域无副本', [
                            'record_id'     => $record['_id'],
                            'backup_region' => $backupRegion
                        ]);
                    }

                } catch (\Exception $e) {
                    Log::error('单个记录故障转移失败', [
                        'record_id' => $record['_id'],
                        'error'     => $e->getMessage()
                    ]);
                }
            }

            Log::warning('故障转移处理完成', [
                'failed_region' => $failedRegion,
                'backup_region' => $backupRegion,
                'total_records' => $totalCount,
                'success_count' => $successCount,
                'success_rate'  => $totalCount > 0 ? round(($successCount / $totalCount) * 100, 2) : 0
            ]);

            return $successCount > 0;

        } catch (\Exception $e) {
            Log::error('故障转移处理失败', [
                'failed_region' => $failedRegion,
                'backup_region' => $backupRegion,
                'error'         => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 获取全球数据分布统计
     * 
     * @return array
     */
    public static function getGlobalDistributionStats(): array
    {
        try {
            Log::info('获取全球数据分布统计');

            $allData = self::select()->toArray();

            $stats = [
                'total_records'      => count($allData),
                'regions'            => [],
                'replication_health' => [],
                'data_distribution'  => [],
                'sync_status'        => [
                    'synced'  => 0,
                    'syncing' => 0,
                    'failed'  => 0
                ],
                'last_updated'       => time()
            ];

            $regionCounts     = [];
            $replicationStats = [];

            foreach ($allData as $record) {
                // 统计区域分布
                $region                = $record['region'] ?? 'unknown';
                $regionCounts[$region] = ($regionCounts[$region] ?? 0) + 1;

                // 统计同步状态
                $syncStatus = $record['replication_info']['sync_status'] ?? 'unknown';
                if (isset($stats['sync_status'][$syncStatus])) {
                    $stats['sync_status'][$syncStatus]++;
                }

                // 统计复制健康度
                $replicatedRegions = $record['replication_info']['replicated_regions'] ?? [];
                $replicationCount  = count($replicatedRegions);
                $lastSyncTime      = $record['replication_info']['last_sync_time'] ?? 0;
                $syncLag           = time() - $lastSyncTime;

                if (!isset($replicationStats[$region])) {
                    $replicationStats[$region] = [
                        'total_records'         => 0,
                        'avg_replication_count' => 0,
                        'avg_sync_lag'          => 0,
                        'healthy_records'       => 0
                    ];
                }

                $replicationStats[$region]['total_records']++;
                $replicationStats[$region]['avg_replication_count'] += $replicationCount;
                $replicationStats[$region]['avg_sync_lag'] += $syncLag;

                if ($syncLag < 300) { // 5分钟内认为健康
                    $replicationStats[$region]['healthy_records']++;
                }
            }

            // 计算平均值
            foreach ($replicationStats as $region => &$stat) {
                if ($stat['total_records'] > 0) {
                    $stat['avg_replication_count'] = round($stat['avg_replication_count'] / $stat['total_records'], 2);
                    $stat['avg_sync_lag']          = round($stat['avg_sync_lag'] / $stat['total_records'], 2);
                    $stat['health_rate']           = round(($stat['healthy_records'] / $stat['total_records']) * 100, 2);
                }
            }

            $stats['regions']            = $regionCounts;
            $stats['replication_health'] = $replicationStats;

            // 数据分布百分比
            if ($stats['total_records'] > 0) {
                foreach ($regionCounts as $region => $count) {
                    $stats['data_distribution'][$region] = round(($count / $stats['total_records']) * 100, 2);
                }
            }

            Log::info('全球数据分布统计完成', [
                'total_records' => $stats['total_records'],
                'regions_count' => count($stats['regions'])
            ]);

            return $stats;

        } catch (\Exception $e) {
            Log::error('获取全球数据分布统计失败', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * 触发复制到其他区域（异步）
     * 
     * @param string $recordId 记录ID
     * @param string $sourceRegion 源区域
     * @return void
     */
    private static function triggerReplication(string $recordId, string $sourceRegion): void
    {
        try {
            // 这里应该触发异步任务来复制数据
            // 模拟复制到预定义的区域列表
            $targetRegions = ['us-east', 'eu-west', 'asia-pacific'];

            foreach ($targetRegions as $region) {
                if ($region !== $sourceRegion) {
                    // 这里应该加入队列或异步任务
                    Log::info('触发异步复制', [
                        'record_id'     => $recordId,
                        'source_region' => $sourceRegion,
                        'target_region' => $region
                    ]);
                }
            }

        } catch (\Exception $e) {
            Log::error('触发复制失败', [
                'record_id'     => $recordId,
                'source_region' => $sourceRegion,
                'error'         => $e->getMessage()
            ]);
        }
    }

    /**
     * 执行异步复制
     * 
     * @param string $recordId 记录ID
     * @param string $targetRegion 目标区域
     * @return void
     */
    private static function performAsyncReplication(string $recordId, string $targetRegion): void
    {
        try {
            // 模拟异步复制过程
            Log::info('执行异步复制', [
                'record_id'     => $recordId,
                'target_region' => $targetRegion
            ]);

            // 这里应该实际执行数据复制逻辑
            // 复制完成后更新状态
            sleep(1); // 模拟复制延迟

            $record = self::find($recordId);
            if ($record) {
                $replicationInfo                   = $record->replication_info;
                $replicationInfo['sync_status']    = 'synced';
                $replicationInfo['last_sync_time'] = time();

                $record->save([
                    'replication_info' => $replicationInfo,
                    'updated_at'       => time()
                ]);
            }

            Log::info('异步复制完成', [
                'record_id'     => $recordId,
                'target_region' => $targetRegion
            ]);

        } catch (\Exception $e) {
            Log::error('异步复制失败', [
                'record_id'     => $recordId,
                'target_region' => $targetRegion,
                'error'         => $e->getMessage()
            ]);
        }
    }
}