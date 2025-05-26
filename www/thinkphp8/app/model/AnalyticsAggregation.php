<?php
declare(strict_types=1);

namespace app\model;

use think\Model;
use think\facade\Log;
use think\exception\DbException;

/**
 * 分析聚合模型 - 聚合框架特性
 * 支持复杂数据分析和实时仪表盘
 */
class AnalyticsAggregation extends Model
{
    // 设置MongoDB连接
    protected $connection = 'mongo';

    // 设置集合名称
    protected $table = 'analytics_data';

    // 设置主键
    protected $pk = '_id';

    // 自动时间戳
    protected $autoWriteTimestamp = true;

    // JSON字段
    protected $json = ['metrics', 'dimensions', 'metadata'];

    /**
     * 记录分析数据
     * 
     * @param array $data 分析数据
     * @return bool
     */
    public static function recordAnalyticsData(array $data): bool
    {
        try {
            Log::info('记录分析数据', [
                'event_type' => $data['event_type'] ?? 'unknown',
                'user_id'    => $data['user_id'] ?? 'anonymous'
            ]);

            $analyticsData = [
                'event_type' => $data['event_type'] ?? '',
                'user_id'    => $data['user_id'] ?? '',
                'session_id' => $data['session_id'] ?? '',
                'timestamp'  => $data['timestamp'] ?? time(),
                'metrics'    => $data['metrics'] ?? [],
                'dimensions' => $data['dimensions'] ?? [],
                'metadata'   => $data['metadata'] ?? [],
                'created_at' => time(),
                'updated_at' => time()
            ];

            $result = self::create($analyticsData);

            if ($result) {
                Log::info('分析数据记录成功', ['record_id' => $result->_id]);
                return true;
            }

            return false;

        } catch (\Exception $e) {
            Log::error('记录分析数据失败', [
                'data'  => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * 获取用户行为分析
     * 
     * @param int $startTime 开始时间戳
     * @param int $endTime 结束时间戳
     * @param string $eventType 事件类型
     * @return array
     */
    public static function getUserBehaviorAnalysis(int $startTime, int $endTime, string $eventType = ''): array
    {
        try {
            Log::info('获取用户行为分析', [
                'start_time' => date('Y-m-d H:i:s', $startTime),
                'end_time'   => date('Y-m-d H:i:s', $endTime),
                'event_type' => $eventType
            ]);

            $query = self::where('timestamp', '>=', $startTime)
                ->where('timestamp', '<=', $endTime);

            if (!empty($eventType)) {
                $query->where('event_type', $eventType);
            }

            $data = $query->select()->toArray();

            // 手动聚合分析（模拟MongoDB聚合管道）
            $analysis = [
                'total_events'        => count($data),
                'unique_users'        => 0,
                'event_types'         => [],
                'hourly_distribution' => [],
                'user_engagement'     => [],
                'top_users'           => []
            ];

            $uniqueUsers     = [];
            $eventTypeCounts = [];
            $hourlyData      = [];
            $userEventCounts = [];

            foreach ($data as $record) {
                // 统计唯一用户
                if (!empty($record['user_id']) && !in_array($record['user_id'], $uniqueUsers)) {
                    $uniqueUsers[] = $record['user_id'];
                }

                // 统计事件类型
                $eventType                   = $record['event_type'] ?? 'unknown';
                $eventTypeCounts[$eventType] = ($eventTypeCounts[$eventType] ?? 0) + 1;

                // 统计小时分布
                $hour              = date('H', $record['timestamp']);
                $hourlyData[$hour] = ($hourlyData[$hour] ?? 0) + 1;

                // 统计用户事件数
                $userId                   = $record['user_id'] ?? 'anonymous';
                $userEventCounts[$userId] = ($userEventCounts[$userId] ?? 0) + 1;
            }

            $analysis['unique_users']        = count($uniqueUsers);
            $analysis['event_types']         = $eventTypeCounts;
            $analysis['hourly_distribution'] = $hourlyData;

            // 排序获取最活跃用户
            arsort($userEventCounts);
            $analysis['top_users'] = array_slice($userEventCounts, 0, 10, true);

            // 计算用户参与度
            if ($analysis['unique_users'] > 0) {
                $analysis['user_engagement'] = [
                    'avg_events_per_user' => round($analysis['total_events'] / $analysis['unique_users'], 2),
                    'active_user_rate'    => round(($analysis['unique_users'] / max(1, $analysis['total_events'])) * 100, 2)
                ];
            }

            Log::info('用户行为分析完成', [
                'total_events' => $analysis['total_events'],
                'unique_users' => $analysis['unique_users']
            ]);

            return $analysis;

        } catch (\Exception $e) {
            Log::error('用户行为分析失败', [
                'start_time' => $startTime,
                'end_time'   => $endTime,
                'event_type' => $eventType,
                'error'      => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * 获取实时仪表盘数据
     * 
     * @param int $timeRange 时间范围（秒）
     * @return array
     */
    public static function getRealTimeDashboard(int $timeRange = 3600): array
    {
        try {
            $startTime = time() - $timeRange;
            Log::info('获取实时仪表盘数据', [
                'time_range' => $timeRange,
                'start_time' => date('Y-m-d H:i:s', $startTime)
            ]);

            $data = self::where('timestamp', '>=', $startTime)
                ->order('timestamp', 'desc')
                ->select()
                ->toArray();

            $dashboard = [
                'summary'           => [
                    'total_events'      => count($data),
                    'events_per_minute' => 0,
                    'unique_users'      => 0,
                    'active_sessions'   => 0
                ],
                'real_time_metrics' => [],
                'trending_events'   => [],
                'user_activity'     => [],
                'system_health'     => [
                    'status'        => 'healthy',
                    'response_time' => 0,
                    'error_rate'    => 0
                ],
                'last_updated'      => time()
            ];

            if (!empty($data)) {
                // 计算每分钟事件数
                $dashboard['summary']['events_per_minute'] = round(count($data) / ($timeRange / 60), 2);

                // 统计唯一用户和会话
                $uniqueUsers    = [];
                $activeSessions = [];
                $eventTypes     = [];
                $recentActivity = [];

                foreach ($data as $record) {
                    // 唯一用户
                    if (!empty($record['user_id']) && !in_array($record['user_id'], $uniqueUsers)) {
                        $uniqueUsers[] = $record['user_id'];
                    }

                    // 活跃会话
                    if (!empty($record['session_id']) && !in_array($record['session_id'], $activeSessions)) {
                        $activeSessions[] = $record['session_id'];
                    }

                    // 事件类型统计
                    $eventType              = $record['event_type'] ?? 'unknown';
                    $eventTypes[$eventType] = ($eventTypes[$eventType] ?? 0) + 1;

                    // 最近活动（最新10条）
                    if (count($recentActivity) < 10) {
                        $recentActivity[] = [
                            'event_type' => $eventType,
                            'user_id'    => $record['user_id'] ?? 'anonymous',
                            'timestamp'  => $record['timestamp'],
                            'time_ago'   => time() - $record['timestamp']
                        ];
                    }
                }

                $dashboard['summary']['unique_users']    = count($uniqueUsers);
                $dashboard['summary']['active_sessions'] = count($activeSessions);

                // 趋势事件（按频率排序）
                arsort($eventTypes);
                $dashboard['trending_events'] = array_slice($eventTypes, 0, 5, true);

                // 用户活动
                $dashboard['user_activity'] = $recentActivity;

                // 实时指标（按时间分组）
                $timeSlots    = [];
                $slotDuration = 300; // 5分钟一个时间段

                foreach ($data as $record) {
                    $slot             = floor($record['timestamp'] / $slotDuration) * $slotDuration;
                    $timeSlots[$slot] = ($timeSlots[$slot] ?? 0) + 1;
                }

                ksort($timeSlots);
                $dashboard['real_time_metrics'] = array_map(function ($timestamp, $count) {
                    return [
                        'timestamp'   => $timestamp,
                        'time_label'  => date('H:i', $timestamp),
                        'event_count' => $count
                    ];
                }, array_keys($timeSlots), array_values($timeSlots));
            }

            Log::info('实时仪表盘数据获取完成', [
                'total_events' => $dashboard['summary']['total_events'],
                'unique_users' => $dashboard['summary']['unique_users']
            ]);

            return $dashboard;

        } catch (\Exception $e) {
            Log::error('获取实时仪表盘数据失败', [
                'time_range' => $timeRange,
                'error'      => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * 获取转化漏斗分析
     * 
     * @param array $funnelSteps 漏斗步骤
     * @param int $startTime 开始时间
     * @param int $endTime 结束时间
     * @return array
     */
    public static function getFunnelAnalysis(array $funnelSteps, int $startTime, int $endTime): array
    {
        try {
            Log::info('获取转化漏斗分析', [
                'funnel_steps' => $funnelSteps,
                'start_time'   => date('Y-m-d H:i:s', $startTime),
                'end_time'     => date('Y-m-d H:i:s', $endTime)
            ]);

            if (empty($funnelSteps)) {
                throw new \Exception('漏斗步骤不能为空');
            }

            $data = self::where('timestamp', '>=', $startTime)
                ->where('timestamp', '<=', $endTime)
                ->whereIn('event_type', $funnelSteps)
                ->select()
                ->toArray();

            $funnelAnalysis = [
                'steps'            => [],
                'conversion_rates' => [],
                'total_users'      => 0,
                'drop_off_points'  => []
            ];

            // 按用户分组事件
            $userEvents = [];
            foreach ($data as $record) {
                $userId = $record['user_id'] ?? 'anonymous';
                if (!isset($userEvents[$userId])) {
                    $userEvents[$userId] = [];
                }
                $userEvents[$userId][] = $record['event_type'];
            }

            $funnelAnalysis['total_users'] = count($userEvents);

            // 分析每个步骤的用户数
            $stepUsers = [];
            foreach ($funnelSteps as $stepIndex => $step) {
                $stepUsers[$step] = 0;

                foreach ($userEvents as $userId => $events) {
                    // 检查用户是否完成了当前步骤及之前的所有步骤
                    $completedSteps = true;
                    for ($i = 0; $i <= $stepIndex; $i++) {
                        if (!in_array($funnelSteps[$i], $events)) {
                            $completedSteps = false;
                            break;
                        }
                    }

                    if ($completedSteps) {
                        $stepUsers[$step]++;
                    }
                }

                $funnelAnalysis['steps'][] = [
                    'step'       => $step,
                    'step_index' => $stepIndex + 1,
                    'users'      => $stepUsers[$step],
                    'percentage' => $funnelAnalysis['total_users'] > 0 ?
                        round(($stepUsers[$step] / $funnelAnalysis['total_users']) * 100, 2) : 0
                ];
            }

            // 计算转化率
            for ($i = 1; $i < count($funnelSteps); $i++) {
                $currentStep  = $funnelSteps[$i];
                $previousStep = $funnelSteps[$i - 1];

                $conversionRate = $stepUsers[$previousStep] > 0 ?
                    round(($stepUsers[$currentStep] / $stepUsers[$previousStep]) * 100, 2) : 0;

                $funnelAnalysis['conversion_rates'][] = [
                    'from_step' => $previousStep,
                    'to_step'   => $currentStep,
                    'rate'      => $conversionRate,
                    'drop_off'  => 100 - $conversionRate
                ];
            }

            Log::info('转化漏斗分析完成', [
                'total_users' => $funnelAnalysis['total_users'],
                'steps_count' => count($funnelAnalysis['steps'])
            ]);

            return $funnelAnalysis;

        } catch (\Exception $e) {
            Log::error('转化漏斗分析失败', [
                'funnel_steps' => $funnelSteps,
                'start_time'   => $startTime,
                'end_time'     => $endTime,
                'error'        => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * 获取用户留存分析
     * 
     * @param int $startTime 开始时间
     * @param int $days 分析天数
     * @return array
     */
    public static function getRetentionAnalysis(int $startTime, int $days = 7): array
    {
        try {
            Log::info('获取用户留存分析', [
                'start_time' => date('Y-m-d H:i:s', $startTime),
                'days'       => $days
            ]);

            $endTime = $startTime + ($days * 24 * 3600);

            $data = self::where('timestamp', '>=', $startTime)
                ->where('timestamp', '<=', $endTime)
                ->select()
                ->toArray();

            $retentionAnalysis = [
                'cohort_date'      => date('Y-m-d', $startTime),
                'total_users'      => 0,
                'retention_by_day' => [],
                'retention_rates'  => []
            ];

            // 按用户和日期分组
            $usersByDay    = [];
            $firstDayUsers = [];

            foreach ($data as $record) {
                $userId = $record['user_id'] ?? 'anonymous';
                $day    = date('Y-m-d', $record['timestamp']);

                if (!isset($usersByDay[$day])) {
                    $usersByDay[$day] = [];
                }

                if (!in_array($userId, $usersByDay[$day])) {
                    $usersByDay[$day][] = $userId;
                }

                // 记录第一天的用户
                if ($day === date('Y-m-d', $startTime) && !in_array($userId, $firstDayUsers)) {
                    $firstDayUsers[] = $userId;
                }
            }

            $retentionAnalysis['total_users'] = count($firstDayUsers);

            // 计算每日留存
            for ($i = 0; $i < $days; $i++) {
                $currentDay = date('Y-m-d', $startTime + ($i * 24 * 3600));
                $dayUsers   = $usersByDay[$currentDay] ?? [];

                // 计算留存用户（第一天用户中在当天活跃的用户）
                $retainedUsers  = array_intersect($firstDayUsers, $dayUsers);
                $retentionCount = count($retainedUsers);
                $retentionRate  = count($firstDayUsers) > 0 ?
                    round(($retentionCount / count($firstDayUsers)) * 100, 2) : 0;

                $retentionAnalysis['retention_by_day'][] = [
                    'day'            => $i,
                    'date'           => $currentDay,
                    'retained_users' => $retentionCount,
                    'retention_rate' => $retentionRate
                ];
            }

            Log::info('用户留存分析完成', [
                'total_users'   => $retentionAnalysis['total_users'],
                'analysis_days' => $days
            ]);

            return $retentionAnalysis;

        } catch (\Exception $e) {
            Log::error('用户留存分析失败', [
                'start_time' => $startTime,
                'days'       => $days,
                'error'      => $e->getMessage()
            ]);
            return [];
        }
    }
}