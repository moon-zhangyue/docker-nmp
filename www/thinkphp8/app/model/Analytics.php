<?php
declare(strict_types=1);

namespace app\model;

use think\Model;
use think\facade\Log;

class Analytics extends Model
{
    // 设置MongoDB连接
    protected $connection = 'mongo';
    
    // 设置集合名称
    protected $table = 'analytics';
    
    // 设置主键
    protected $pk = '_id';
    
    // 自动时间戳
    protected $autoWriteTimestamp = true;
    
    /**
     * 保存用户行为数据
     * 
     * @param array $data 行为数据
     * @return bool
     */
    public static function saveUserAction(array $data): bool
    {
        try {
            // 记录日志
            Log::info('保存用户行为数据', ['data' => $data]);
            
            // 创建记录
            return self::create($data) ? true : false;
        } catch (\Exception $e) {
            Log::error('保存用户行为数据失败', ['data' => $data, 'message' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * 记录用户行为
     */
    public static function recordAction(array $data): ?Analytics
    {
        try {
            if (!isset($data['action_type'])) {
                throw new \Exception('行为类型不能为空');
            }
            
            // 确保有时间戳
            if (!isset($data['create_time'])) {
                $data['create_time'] = time();
            }
            
            Log::info('保存用户行为数据: {action_type}', ['data' => $data, 'action_type' => $data['action_type']]);
            
            return self::create($data);
        } catch (\Exception $e) {
            Log::error('保存用户行为数据失败: {message}', ['data' => $data, 'message' => $e->getMessage()]);
            return null;
        }
    }
    
    /**
     * 按时间段统计用户行为
     * 
     * @param string $actionType 行为类型
     * @param string $startTime 开始时间
     * @param string $endTime 结束时间
     * @param string $timeUnit 时间单位 (hour/day/week/month)
     * @return array
     */
    public static function aggregateByTime(
        string $actionType, 
        string $startTime, 
        string $endTime, 
        string $timeUnit = 'day'
    ): array
    {
        try {
            // 时间单位对应的格式化
            $dateFormat = match ($timeUnit) {
                'hour' => '%Y-%m-%d %H:00:00',
                'day' => '%Y-%m-%d',
                'week' => '%Y-%U',
                'month' => '%Y-%m',
                default => '%Y-%m-%d'
            };
            
            // 构建聚合管道
            $pipeline = [
                [
                    '$match' => [
                        'action_type' => $actionType,
                        'create_time' => [
                            '$gte' => strtotime($startTime),
                            '$lte' => strtotime($endTime)
                        ]
                    ]
                ],
                [
                    '$group' => [
                        '_id' => [
                            'time_unit' => [
                                '$dateToString' => [
                                    'format' => $dateFormat,
                                    'date' => ['$toDate' => ['$multiply' => ['$create_time', 1000]]]
                                ]
                            ]
                        ],
                        'count' => ['$sum' => 1]
                    ]
                ],
                [
                    '$sort' => ['_id.time_unit' => 1]
                ]
            ];
            
            // 执行聚合查询
            return self::mongoAggregate($pipeline);
        } catch (\Exception $e) {
            Log::error('按时间段统计用户行为失败: {message}, 行为类型: {action_type}, 时间范围: {time_range}', [
                'action_type' => $actionType,
                'time_range' => [$startTime, $endTime],
                'message' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * 按用户分组统计行为数据
     * 
     * @param string $actionType 行为类型
     * @param string $startTime 开始时间
     * @param string $endTime 结束时间
     * @param int $limit 返回数量限制
     * @return array
     */
    public static function aggregateByUser(
        string $actionType, 
        string $startTime, 
        string $endTime, 
        int $limit = 10
    ): array
    {
        try {
            // 构建聚合管道
            $pipeline = [
                [
                    '$match' => [
                        'action_type' => $actionType,
                        'create_time' => [
                            '$gte' => strtotime($startTime),
                            '$lte' => strtotime($endTime)
                        ]
                    ]
                ],
                [
                    '$group' => [
                        '_id' => '$user_id',
                        'count' => ['$sum' => 1],
                        'last_time' => ['$max' => '$create_time']
                    ]
                ],
                [
                    '$sort' => ['count' => -1]
                ],
                [
                    '$limit' => $limit
                ]
            ];
            
            // 执行聚合查询
            return self::mongoAggregate($pipeline);
        } catch (\Exception $e) {
            Log::error('按用户分组统计行为数据失败', [
                'action_type' => $actionType,
                'time_range' => [$startTime, $endTime],
                'message' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * 统计不同行为类型的占比
     * 
     * @param string $startTime 开始时间
     * @param string $endTime 结束时间
     * @return array
     */
    public static function aggregateActionTypes(string $startTime, string $endTime): array
    {
        try {
            // 构建聚合管道
            $pipeline = [
                [
                    '$match' => [
                        'create_time' => [
                            '$gte' => strtotime($startTime),
                            '$lte' => strtotime($endTime)
                        ]
                    ]
                ],
                [
                    '$group' => [
                        '_id' => '$action_type',
                        'count' => ['$sum' => 1]
                    ]
                ],
                [
                    '$sort' => ['count' => -1]
                ]
            ];
            
            // 执行聚合查询
            $result = self::mongoAggregate($pipeline);
            
            // 计算总数
            $total = array_sum(array_column($result, 'count'));
            
            // 计算占比
            if ($total > 0) {
                foreach ($result as &$item) {
                    $item['percentage'] = round($item['count'] / $total * 100, 2);
                }
            }
            
            return $result;
        } catch (\Exception $e) {
            Log::error('统计不同行为类型的占比失败: {message}, 时间范围: {time_range}', [
                'time_range' => [$startTime, $endTime],
                'message' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * 用户行为路径分析
     * 
     * @param string $userId 用户ID
     * @param string $startTime 开始时间
     * @param string $endTime 结束时间
     * @param int $limit 路径长度限制
     * @return array
     */
    public static function analyzeUserPath(
        string $userId, 
        string $startTime, 
        string $endTime, 
        int $limit = 10
    ): array
    {
        try {
            // 构建聚合管道
            $pipeline = [
                [
                    '$match' => [
                        'user_id' => $userId,
                        'create_time' => [
                            '$gte' => strtotime($startTime),
                            '$lte' => strtotime($endTime)
                        ]
                    ]
                ],
                [
                    '$sort' => ['create_time' => 1]
                ],
                [
                    '$project' => [
                        'action_type' => 1,
                        'page' => 1,
                        'create_time' => 1,
                        'time_str' => [
                            '$dateToString' => [
                                'format' => '%Y-%m-%d %H:%M:%S',
                                'date' => ['$toDate' => ['$multiply' => ['$create_time', 1000]]]
                            ]
                        ]
                    ]
                ],
                [
                    '$limit' => $limit
                ]
            ];
            
            // 执行聚合查询
            return self::mongoAggregate($pipeline);
        } catch (\Exception $e) {
            Log::error('用户行为路径分析失败', [
                'user_id' => $userId,
                'time_range' => [$startTime, $endTime],
                'message' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * 执行MongoDB聚合查询
     * 
     * @param array $pipeline 聚合管道
     * @return array
     */
    protected static function mongoAggregate(array $pipeline): array
    {
        try {
            $model = new self();
            $connection = $model->getConnection();
            $collection = $connection->getCollection($model->getTable());
            
            $result = $collection->aggregate($pipeline)->toArray();
            
            return $result;
        } catch (\Exception $e) {
            Log::error('MongoDB聚合查询失败', ['pipeline' => $pipeline, 'message' => $e->getMessage()]);
            return [];
        }
    }
} 