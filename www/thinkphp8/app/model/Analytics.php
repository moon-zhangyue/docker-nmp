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
    protected static $table = 'analytics';

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
            Log::info('保存用户行为数据: {data}', ['data' => json_encode($data)]);

            // 创建记录
            return self::create($data) ? true : false;
        } catch (\Exception $e) {
            Log::error('保存用户行为数据失败: {message}, 数据: {data}', ['message' => $e->getMessage(), 'data' => json_encode($data)]);
            return false;
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
    public static function aggregateByTime(string $actionType, string $startTime, string $endTime, string $timeUnit = 'day'): array
    {
        try {
            // 时间单位对应的格式化
            $dateFormat = match ($timeUnit) {
                'hour'  => '%Y-%m-%d %H:00:00',
                'day'   => '%Y-%m-%d',
                'week'  => '%Y-%U',
                'month' => '%Y-%m',
                default => '%Y-%m-%d'
            };

            // 构建聚合管道
            $pipeline = [
                [
                    '$match' => [
                        'action_type' => $actionType,
                        'create_time' => [
                            '$gte' => $startTime,
                            '$lte' => $endTime
                        ]
                    ]
                ],
                [
                    '$group' => [
                        '_id'   => [
                            'time_unit' => [
                                '$dateToString' => [
                                    'format' => $dateFormat,
                                    'date'   => ['$dateFromString' => ['dateString' => '$create_time']]
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
            return self::mongoAggregate($pipeline, self::$table);
        } catch (\Exception $e) {
            Log::error('按时间段统计用户行为失败: {message}, 行为类型: {action_type}, 时间范围: {time_range}', [
                'action_type' => $actionType,
                'time_range'  => json_encode([$startTime, $endTime]),
                'message'     => $e->getMessage()
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
    public static function aggregateByUser(string $actionType, string $startTime, string $endTime, int $limit = 10): array
    {
        try {
            // 构建聚合管道
            $pipeline = [
                [
                    '$match' => [
                        'action_type' => $actionType,
                        'create_time' => [
                            '$gte' => $startTime,
                            '$lte' => $endTime
                        ]
                    ]
                ],
                [
                    '$group' => [
                        '_id'       => '$user_id',
                        'count'     => ['$sum' => 1],
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
            return self::mongoAggregate($pipeline, self::$table);
        } catch (\Exception $e) {
            Log::error('按用户分组统计行为数据失败: {message}, 行为类型: {action_type}, 时间范围: {time_range}', [
                'message'     => $e->getMessage(),
                'action_type' => $actionType,
                'time_range'  => json_encode([$startTime, $endTime])
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
                            '$gte' => $startTime,
                            '$lte' => $endTime
                        ]
                    ]
                ],
                [
                    '$group' => [
                        '_id'   => '$action_type',
                        'count' => ['$sum' => 1]
                    ]
                ],
                [
                    '$sort' => ['count' => -1]
                ]
            ];

            // 执行聚合查询
            $result = self::mongoAggregate($pipeline, self::$table);

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
                'time_range' => json_encode([$startTime, $endTime]),
                'message'    => $e->getMessage()
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
    public static function analyzeUserPath(string $userId, string $startTime, string $endTime, int $limit = 10): array
    {
        try {
            // 构建聚合管道
            $pipeline = [
                [
                    '$match' => [
                        'user_id'     => $userId,
                        'create_time' => [
                            '$gte' => $startTime,
                            '$lte' => $endTime
                        ]
                    ]
                ],
                [
                    '$sort' => ['create_time' => 1]
                ],
                [
                    '$project' => [
                        'action_type' => 1,
                        'page'        => 1,
                        'create_time' => 1,
                        'time_str'    => [
                            '$dateToString' => [
                                'format' => '%Y-%m-%d %H:%M:%S',
                                'date'   => ['$dateFromString' => ['dateString' => '$create_time']]
                            ]
                        ]
                    ]
                ],
                [
                    '$limit' => $limit
                ]
            ];

            // 执行聚合查询
            return self::mongoAggregate($pipeline, self::$table);
        } catch (\Exception $e) {
            Log::error('用户行为路径分析失败: {message}, 用户ID: {user_id}, 时间范围: {time_range}', [
                'message'    => $e->getMessage(),
                'user_id'    => $userId,
                'time_range' => json_encode([$startTime, $endTime])
            ]);
            return [];
        }
    }

    /**
     * @param array $pipeline 聚合管道
     * @return array
     */
    protected function analyzeProductSale(array $pipeline, $table): array
    {
        // 执行聚合查询
        return self::mongoAggregate($pipeline, $table);
    }

    /**
     * 执行MongoDB聚合查询
     * 使用MongoDB原生客户端进行聚合查询
     *
     * @param array $pipeline
     * @param string $table $name
     * @return array
     */
    protected static function mongoAggregate(array $pipeline, string $table): array
    {
        try {
            // 获取MongoDB配置
            $mongoConfig = config('database.connections.mongo');

            // 构建MongoDB连接URI
            $uri = $mongoConfig['dsn'] ?? sprintf(
                'mongodb://%s:%s@%s/%s?authSource=admin',
                $mongoConfig['username'],
                $mongoConfig['password'],
                $mongoConfig['hostname'],
                $mongoConfig['database']
            );

            // 创建MongoDB原生客户端
            $client     = new \MongoDB\Client($uri);
            $database   = $client->selectDatabase($mongoConfig['database']);
            $collection = $database->selectCollection($table);

            // 执行聚合查询
            $result = $collection->aggregate($pipeline)->toArray();
            return $result;
        } catch (\Exception $e) {
            Log::error('MongoDB聚合查询失败: {message}, 管道: {pipeline}', ['message' => $e->getMessage(), 'pipeline' => json_encode($pipeline)]);
            return [];
        }
    }
}