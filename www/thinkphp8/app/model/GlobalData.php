<?php
declare(strict_types=1);

namespace app\model;

use think\Model;
use think\facade\Log;

class GlobalData extends Model
{
    // 设置MongoDB连接（使用分片集群配置）
    protected $connection = 'mongo_sharded';
    
    // 设置集合名称
    protected $table = 'global_data';
    
    // 设置主键
    protected $pk = '_id';
    
    // 自动时间戳
    protected $autoWriteTimestamp = true;
    
    /**
     * 保存全球数据
     * 
     * @param array $data 数据内容
     * @return GlobalData|null
     */
    public static function saveGlobalData(array $data): ?GlobalData
    {
        try {
            // 添加区域信息用于分片
            if (!isset($data['region'])) {
                throw new \Exception('区域信息不能为空，用于数据分片');
            }
            
            // 记录日志
            Log::info('保存全球数据: {region}', ['region' => $data['region']]);
            
            // 创建数据
            return self::create($data);
        } catch (\Exception $e) {
            Log::error('保存全球数据失败: {message}', ['data' => $data, 'message' => $e->getMessage()]);
            return null;
        }
    }
    
    /**
     * 更新全球数据
     * 
     * @param mixed $id 数据ID
     * @param array $data 更新数据
     * @return bool
     */
    public static function updateGlobalData($id, array $data): bool
    {
        try {
            // 获取数据
            $globalData = self::find($id);
            if (!$globalData) {
                throw new \Exception('数据不存在');
            }
            
            // 记录日志
            Log::info('更新全球数据', ['id' => $id, 'region' => $data['region'] ?? $globalData->region]);
            
            // 更新数据
            return $globalData->save($data);
        } catch (\Exception $e) {
            Log::error('更新全球数据失败', ['id' => $id, 'data' => $data, 'message' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * 根据区域查询数据
     * 
     * @param string $region 区域编码
     * @param array $conditions 额外查询条件
     * @param int $page 页码
     * @param int $limit 每页数量
     * @return array
     */
    public static function getDataByRegion(
        string $region, 
        array $conditions = [], 
        int $page = 1, 
        int $limit = 20
    ): array
    {
        try {
            // 构建查询条件
            $query = self::where('region', $region);
            
            // 添加额外查询条件
            foreach ($conditions as $field => $value) {
                $query->where($field, $value);
            }
            
            // 分页查询
            $data = $query->page($page, $limit)->select()->toArray();
            
            return $data;
        } catch (\Exception $e) {
            Log::error('根据区域查询数据失败', [
                'region' => $region,
                'conditions' => $conditions,
                'message' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * 执行全局聚合统计
     * 
     * @param string $groupField 分组字段
     * @param array $match 匹配条件
     * @return array
     */
    public static function globalAggregate(string $groupField, array $match = []): array
    {
        try {
            // 构建聚合管道
            $pipeline = [];
            
            // 匹配条件
            if (!empty($match)) {
                $pipeline[] = ['$match' => $match];
            }
            
            // 分组统计
            $pipeline[] = [
                '$group' => [
                    '_id' => '$' . $groupField,
                    'count' => ['$sum' => 1],
                    'last_update' => ['$max' => '$update_time']
                ]
            ];
            
            // 排序
            $pipeline[] = ['$sort' => ['count' => -1]];
            
            // 执行聚合查询
            $result = self::mongoAggregate($pipeline);
            
            // 格式化结果
            $formattedResult = [];
            foreach ($result as $item) {
                $formattedResult[] = [
                    $groupField => $item['_id'],
                    'count' => $item['count'],
                    'last_update' => date('Y-m-d H:i:s', $item['last_update'])
                ];
            }
            
            return $formattedResult;
        } catch (\Exception $e) {
            Log::error('执行全局聚合统计失败', [
                'group_field' => $groupField,
                'match' => $match,
                'message' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * 多区域数据对比
     * 
     * @param array $regions 要对比的区域列表
     * @param string $metric 对比指标
     * @return array
     */
    public static function compareRegions(array $regions, string $metric): array
    {
        try {
            // 结果集
            $result = [];
            
            // 遍历区域
            foreach ($regions as $region) {
                // 构建聚合管道
                $pipeline = [
                    [
                        '$match' => ['region' => $region]
                    ],
                    [
                        '$group' => [
                            '_id' => '$region',
                            'total' => ['$sum' => '$' . $metric],
                            'avg' => ['$avg' => '$' . $metric],
                            'max' => ['$max' => '$' . $metric],
                            'min' => ['$min' => '$' . $metric],
                            'count' => ['$sum' => 1]
                        ]
                    ]
                ];
                
                // 执行聚合查询
                $regionResult = self::mongoAggregate($pipeline);
                
                // 提取结果
                if (!empty($regionResult)) {
                    $result[] = [
                        'region' => $region,
                        'total' => $regionResult[0]['total'] ?? 0,
                        'avg' => $regionResult[0]['avg'] ?? 0,
                        'max' => $regionResult[0]['max'] ?? 0,
                        'min' => $regionResult[0]['min'] ?? 0,
                        'count' => $regionResult[0]['count'] ?? 0
                    ];
                } else {
                    $result[] = [
                        'region' => $region,
                        'total' => 0,
                        'avg' => 0,
                        'max' => 0,
                        'min' => 0,
                        'count' => 0
                    ];
                }
            }
            
            return $result;
        } catch (\Exception $e) {
            Log::error('多区域数据对比失败: {message}, 区域: {regions}, 指标: {metric}', [
                'regions' => $regions,
                'metric' => $metric,
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