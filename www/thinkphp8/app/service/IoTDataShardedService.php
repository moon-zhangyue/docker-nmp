<?php
declare(strict_types=1);

namespace app\service;

use app\model\IoTDataSharded;
use think\facade\Log;
use think\facade\Cache;
use think\facade\Queue;

/**
 * IoT数据分片服务 - 高扩展性特性
 * 处理大规模物联网数据的业务逻辑
 */
class IoTDataShardedService
{
    /**
     * 批量处理IoT数据
     * 
     * @param array $dataList 数据列表
     * @param array $options 处理选项
     * @return array
     */
    public function batchProcessData(array $dataList, array $options = []): array
    {
        try {
            $batchSize           = $options['batch_size'] ?? 1000;
            $enableValidation    = $options['enable_validation'] ?? true;
            $enableDeduplication = $options['enable_deduplication'] ?? true;

            Log::info('IoT数据分片服务：批量处理数据，总数据量: {total_count}，批次大小: {batch_size}，启用验证: {enable_validation}', [
                'total_count'       => count($dataList),
                'batch_size'        => $batchSize,
                'enable_validation' => $enableValidation
            ]);

            // 数据预处理
            $processedData = $this->preprocessData($dataList, $enableValidation, $enableDeduplication);

            if (empty($processedData)) {
                return [
                    'success' => false,
                    'data'    => null,
                    'message' => '没有有效数据需要处理'
                ];
            }

            // 批量插入数据
            $success = IoTDataSharded::batchInsertOptimized($processedData, $batchSize);

            if ($success) {
                // 更新统计缓存
                $this->updateStatisticsCache($processedData);

                // 触发数据分析任务
                $this->triggerAnalysisTasks($processedData);

                Log::info('IoT数据批量处理成功，处理数量: {processed_count}，原始数量: {original_count}', [
                    'processed_count' => count($processedData),
                    'original_count'  => count($dataList)
                ]);

                return [
                    'success' => true,
                    'data'    => [
                        'processed_count' => count($processedData),
                        'original_count'  => count($dataList),
                        'success_rate'    => round((count($processedData) / count($dataList)) * 100, 2)
                    ],
                    'message' => '数据处理成功'
                ];
            }

            return [
                'success' => false,
                'data'    => null,
                'message' => '数据处理失败'
            ];

        } catch (\Exception $e) {
            Log::error('IoT数据批量处理失败，数据数量: {data_count}，错误: {error}', [
                'data_count' => count($dataList),
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'data'    => null,
                'message' => '数据处理失败：' . $e->getMessage()
            ];
        }
    }

    /**
     * 获取设备数据分析
     * 
     * @param array $deviceIds 设备ID列表
     * @param array $timeRange 时间范围
     * @return array
     */
    public function getDeviceAnalysis(array $deviceIds, array $timeRange = []): array
    {
        try {
            $startTime = $timeRange['start_time'] ?? (time() - 3600); // 默认1小时
            $endTime   = $timeRange['end_time'] ?? time();

            Log::info('IoT数据分片服务：获取设备分析，设备数量: {device_count}，时间范围: {time_range}', [
                'device_count' => count($deviceIds),
                'time_range'   => json_encode([$startTime, $endTime])
            ]);

            $cacheKey = 'iot_device_analysis_' . md5(json_encode($deviceIds) . $startTime . $endTime);

            // 尝试从缓存获取
            $cachedResult = Cache::get($cacheKey);
            if ($cachedResult) {
                Log::info('从缓存获取设备分析结果');
                return $cachedResult;
            }

            // 获取设备统计数据
            $statistics = IoTDataSharded::getDeviceStatistics($deviceIds, $endTime - $startTime);

            // 获取详细数据进行分析
            $analysisData = [];
            foreach ($deviceIds as $deviceId) {
                $deviceData              = IoTDataSharded::getDataByTimeRangeSharded($deviceId, $startTime, $endTime, 1000);
                $analysisData[$deviceId] = $this->analyzeDeviceData($deviceData);
            }

            $result = [
                'success' => true,
                'data'    => [
                    'statistics' => $statistics,
                    'analysis'   => $analysisData,
                    'summary'    => $this->generateAnalysisSummary($statistics, $analysisData),
                    'time_range' => [
                        'start_time' => $startTime,
                        'end_time'   => $endTime,
                        'duration'   => $endTime - $startTime
                    ]
                ],
                'message' => '设备分析完成'
            ];

            // 缓存结果（5分钟）
            Cache::set($cacheKey, $result, 300);

            Log::info('设备分析完成，设备数量: {device_count}', ['device_count' => count($deviceIds)]);
            return $result;

        } catch (\Exception $e) {
            Log::error('设备分析失败，设备ID: {device_ids}，时间范围: {time_range}，错误: {error}', [
                'device_ids' => json_encode($deviceIds),
                'time_range' => json_encode($timeRange),
                'error'      => $e->getMessage()
            ]);

            return [
                'success' => false,
                'data'    => null,
                'message' => '设备分析失败：' . $e->getMessage()
            ];
        }
    }

    /**
     * 执行数据归档
     * 
     * @param array $archiveOptions 归档选项
     * @return array
     */
    public function performDataArchive(array $archiveOptions = []): array
    {
        try {
            $archiveDays = $archiveOptions['archive_days'] ?? 30;
            $batchSize   = $archiveOptions['batch_size'] ?? 10000;
            $dryRun      = $archiveOptions['dry_run'] ?? false;

            Log::info('IoT数据分片服务：执行数据归档，归档天数: {archive_days}，批次大小: {batch_size}，模拟运行: {dry_run}', [
                'archive_days' => $archiveDays,
                'batch_size'   => $batchSize,
                'dry_run'      => $dryRun
            ]);

            if ($dryRun) {
                // 模拟归档，只统计数据量
                $archiveTime = time() - ($archiveDays * 24 * 3600);
                $count       = IoTDataSharded::where('created_at', '<', $archiveTime)->count();

                return [
                    'success' => true,
                    'data'    => [
                        'archive_count' => $count,
                        'archive_days'  => $archiveDays,
                        'dry_run'       => true
                    ],
                    'message' => "模拟归档：将归档 {$count} 条数据"
                ];
            }

            // 执行实际归档
            $success = IoTDataSharded::archiveOldData($archiveDays);

            if ($success) {
                // 清除相关缓存
                $this->clearStatisticsCache();

                Log::info('数据归档完成，归档天数: {archive_days}', ['archive_days' => $archiveDays]);
                return [
                    'success' => true,
                    'data'    => [
                        'archive_days' => $archiveDays,
                        'completed_at' => time()
                    ],
                    'message' => '数据归档完成'
                ];
            }

            return [
                'success' => false,
                'data'    => null,
                'message' => '数据归档失败'
            ];

        } catch (\Exception $e) {
            Log::error('数据归档失败，归档选项: {archive_options}，错误: {error}', [
                'archive_options' => json_encode($archiveOptions),
                'error'           => $e->getMessage()
            ]);

            return [
                'success' => false,
                'data'    => null,
                'message' => '数据归档失败：' . $e->getMessage()
            ];
        }
    }

    /**
     * 获取分片信息和健康状态
     * 
     * @return array
     */
    public function getShardHealthStatus(): array
    {
        try {
            Log::info('IoT数据分片服务：获取分片健康状态');

            $cacheKey = 'iot_shard_health_status';

            // 尝试从缓存获取（缓存时间较短，1分钟）
            $cachedResult = Cache::get($cacheKey);
            if ($cachedResult) {
                return $cachedResult;
            }

            // 获取分片信息
            $shardInfo = IoTDataSharded::getShardInfo();

            // 获取系统健康指标
            $healthMetrics = $this->collectHealthMetrics();

            // 获取性能指标
            $performanceMetrics = $this->collectPerformanceMetrics();

            $result = [
                'success' => true,
                'data'    => [
                    'shard_info'          => $shardInfo,
                    'health_metrics'      => $healthMetrics,
                    'performance_metrics' => $performanceMetrics,
                    'overall_status'      => $this->calculateOverallStatus($healthMetrics, $performanceMetrics),
                    'last_updated'        => time()
                ],
                'message' => '分片健康状态获取完成'
            ];

            // 缓存结果（1分钟）
            Cache::set($cacheKey, $result, 60);

            Log::info('分片健康状态获取完成');
            return $result;

        } catch (\Exception $e) {
            Log::error('获取分片健康状态失败: {error}', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'data'    => null,
                'message' => '获取分片健康状态失败：' . $e->getMessage()
            ];
        }
    }

    /**
     * 数据预处理
     * 
     * @param array $dataList 原始数据列表
     * @param bool $enableValidation 是否启用验证
     * @param bool $enableDeduplication 是否启用去重
     * @return array 处理后的数据列表
     */
    private function preprocessData(array $dataList, bool $enableValidation, bool $enableDeduplication): array
    {
        $processedData   = [];
        $duplicateHashes = [];

        foreach ($dataList as $data) {
            try {
                // 数据验证
                if ($enableValidation && !$this->validateIoTData($data)) {
                    continue;
                }

                // 数据标准化
                $normalizedData = $this->normalizeIoTData($data);

                // 去重检查
                if ($enableDeduplication) {
                    $dataHash = $this->generateDataHash($normalizedData);
                    if (in_array($dataHash, $duplicateHashes)) {
                        continue;
                    }
                    $duplicateHashes[] = $dataHash;
                }

                $processedData[] = $normalizedData;

            } catch (\Exception $e) {
                Log::warning('数据预处理跳过无效数据: {data}, 错误: {error}', [
                    'data'  => json_encode($data),
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $processedData;
    }

    /**
     * 验证IoT数据
     * 
     * @param array $data 数据
     * @return bool 是否有效
     */
    private function validateIoTData(array $data): bool
    {
        // 必需字段检查
        if (empty($data['device_id'])) {
            return false;
        }

        // 时间戳检查
        if (isset($data['timestamp'])) {
            $timestamp = is_numeric($data['timestamp']) ? (int) $data['timestamp'] : strtotime($data['timestamp']);
            if ($timestamp === false || $timestamp < 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * 标准化IoT数据
     * 
     * @param array $data 原始数据
     * @return array 标准化后的数据
     */
    private function normalizeIoTData(array $data): array
    {
        $normalized = $data;

        // 标准化时间戳
        if (isset($normalized['timestamp'])) {
            $timestamp               = is_numeric($normalized['timestamp']) ? (int) $normalized['timestamp'] : strtotime($normalized['timestamp']);
            $normalized['timestamp'] = $timestamp;
        } else {
            $normalized['timestamp'] = time();
        }

        // 确保设备ID为字符串
        if (isset($normalized['device_id'])) {
            $normalized['device_id'] = (string) $normalized['device_id'];
        }

        // 添加处理时间戳
        $normalized['processed_at'] = time();

        return $normalized;
    }

    /**
     * 生成数据哈希（用于去重）
     * 
     * @param array $data 数据
     * @return string 哈希值
     */
    private function generateDataHash(array $data): string
    {
        // 排除处理时间戳，只基于核心数据生成哈希
        $coreData = $data;
        unset($coreData['processed_at']);

        return md5(json_encode($coreData));
    }

    /**
     * 分析设备数据
     * 
     * @param array $deviceData 设备数据
     * @return array 分析结果
     */
    private function analyzeDeviceData(array $deviceData): array
    {
        if (empty($deviceData)) {
            return [
                'data_count'   => 0,
                'avg_interval' => 0,
                'data_quality' => 'no_data',
                'anomalies'    => []
            ];
        }

        $analysis = [
            'data_count'   => count($deviceData),
            'time_range'   => [
                'start' => min(array_column($deviceData, 'timestamp')),
                'end'   => max(array_column($deviceData, 'timestamp'))
            ],
            'avg_interval' => 0,
            'data_quality' => 'good',
            'anomalies'    => []
        ];

        // 计算平均间隔
        if (count($deviceData) > 1) {
            $timestamps = array_column($deviceData, 'timestamp');
            sort($timestamps);
            $intervals = [];
            for ($i = 1; $i < count($timestamps); $i++) {
                $intervals[] = $timestamps[$i] - $timestamps[$i - 1];
            }
            $analysis['avg_interval'] = array_sum($intervals) / count($intervals);
        }

        // 检测异常
        $analysis['anomalies'] = $this->detectAnomalies($deviceData);

        // 评估数据质量
        if (count($analysis['anomalies']) > count($deviceData) * 0.1) {
            $analysis['data_quality'] = 'poor';
        } elseif (count($analysis['anomalies']) > 0) {
            $analysis['data_quality'] = 'fair';
        }

        return $analysis;
    }

    /**
     * 检测数据异常
     * 
     * @param array $deviceData 设备数据
     * @return array 异常列表
     */
    private function detectAnomalies(array $deviceData): array
    {
        $anomalies = [];

        // 检测时间间隔异常
        $timestamps = array_column($deviceData, 'timestamp');
        sort($timestamps);

        for ($i = 1; $i < count($timestamps); $i++) {
            $interval = $timestamps[$i] - $timestamps[$i - 1];

            // 如果间隔超过1小时，认为是异常
            if ($interval > 3600) {
                $anomalies[] = [
                    'type'        => 'large_time_gap',
                    'timestamp'   => $timestamps[$i],
                    'interval'    => $interval,
                    'description' => '数据间隔过大'
                ];
            }
        }

        return $anomalies;
    }

    /**
     * 生成分析摘要
     * 
     * @param array $statistics 统计数据
     * @param array $analysisData 分析数据
     * @return array 摘要
     */
    private function generateAnalysisSummary(array $statistics, array $analysisData): array
    {
        $totalDevices   = count($statistics);
        $onlineDevices  = 0;
        $totalDataCount = 0;
        $qualityIssues  = 0;

        foreach ($statistics as $deviceStat) {
            if ($deviceStat['is_online']) {
                $onlineDevices++;
            }
            $totalDataCount += $deviceStat['data_count'];
        }

        foreach ($analysisData as $analysis) {
            if ($analysis['data_quality'] !== 'good') {
                $qualityIssues++;
            }
        }

        return [
            'total_devices'       => $totalDevices,
            'online_devices'      => $onlineDevices,
            'online_rate'         => $totalDevices > 0 ? round(($onlineDevices / $totalDevices) * 100, 2) : 0,
            'total_data_count'    => $totalDataCount,
            'avg_data_per_device' => $totalDevices > 0 ? round($totalDataCount / $totalDevices, 2) : 0,
            'quality_issues'      => $qualityIssues,
            'quality_rate'        => $totalDevices > 0 ? round((($totalDevices - $qualityIssues) / $totalDevices) * 100, 2) : 0
        ];
    }

    /**
     * 收集健康指标
     * 
     * @return array 健康指标
     */
    private function collectHealthMetrics(): array
    {
        // 模拟健康指标收集
        return [
            'connection_status' => 'healthy',
            'replication_lag'   => rand(0, 100), // 毫秒
            'disk_usage'        => rand(30, 80), // 百分比
            'memory_usage'      => rand(40, 70), // 百分比
            'cpu_usage'         => rand(20, 60) // 百分比
        ];
    }

    /**
     * 收集性能指标
     * 
     * @return array 性能指标
     */
    private function collectPerformanceMetrics(): array
    {
        // 模拟性能指标收集
        return [
            'avg_query_time'     => rand(10, 100), // 毫秒
            'queries_per_second' => rand(100, 1000),
            'insert_rate'        => rand(500, 2000), // 每秒插入数
            'index_hit_rate'     => rand(85, 99) // 百分比
        ];
    }

    /**
     * 计算整体状态
     * 
     * @param array $healthMetrics 健康指标
     * @param array $performanceMetrics 性能指标
     * @return string 整体状态
     */
    private function calculateOverallStatus(array $healthMetrics, array $performanceMetrics): string
    {
        // 简单的状态评估逻辑
        if ($healthMetrics['disk_usage'] > 90 || $healthMetrics['memory_usage'] > 90) {
            return 'critical';
        }

        if ($healthMetrics['disk_usage'] > 80 || $performanceMetrics['avg_query_time'] > 200) {
            return 'warning';
        }

        return 'healthy';
    }

    /**
     * 更新统计缓存
     * 
     * @param array $processedData 处理后的数据
     */
    private function updateStatisticsCache(array $processedData): void
    {
        try {
            // 更新设备统计缓存
            $deviceCounts = [];
            foreach ($processedData as $data) {
                $deviceId                = $data['device_id'] ?? 'unknown';
                $deviceCounts[$deviceId] = ($deviceCounts[$deviceId] ?? 0) + 1;
            }

            foreach ($deviceCounts as $deviceId => $count) {
                $cacheKey     = "device_data_count_{$deviceId}";
                $currentCount = Cache::get($cacheKey, 0);
                Cache::set($cacheKey, $currentCount + $count, 3600);
            }

            Log::info('统计缓存更新完成: {device_count} 个设备', ['device_count' => count($deviceCounts)]);
        } catch (\Exception $e) {
            Log::warning('更新统计缓存失败: {error}', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 触发分析任务
     * 
     * @param array $processedData 处理后的数据
     */
    private function triggerAnalysisTasks(array $processedData): void
    {
        try {
            // 如果数据量较大，触发异步分析任务
            if (count($processedData) > 1000) {
                // 这里应该加入队列任务
                Log::info('触发异步分析任务: {data_count} 条数据', ['data_count' => count($processedData)]);
            }
        } catch (\Exception $e) {
            Log::warning('触发分析任务失败: {error}', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 清除统计缓存
     */
    private function clearStatisticsCache(): void
    {
        try {
            Cache::tag('iot_statistics')->clear();
            Log::info('统计缓存清除完成');
        } catch (\Exception $e) {
            Log::warning('清除统计缓存失败: {error}', ['error' => $e->getMessage()]);
        }
    }
}