<?php
declare(strict_types=1);

namespace app\service;

use app\model\Analytics;
use think\facade\Log;
use think\facade\Cache;

class AnalyticsService
{
    /**
     * 记录用户行为
     * 
     * @param array $data 用户行为数据
     * @return bool
     */
    public function recordUserAction(array $data): bool
    {
        try {
            // 验证必要字段
            if (empty($data['user_id']) || empty($data['action_type'])) {
                throw new \Exception('用户ID和行为类型不能为空');
            }
            
            // 记录日志
            Log::info('记录用户行为', ['data' => $data]);
            
            // 保存用户行为
            return Analytics::saveUserAction($data);
        } catch (\Exception $e) {
            Log::error('记录用户行为失败', ['data' => $data, 'message' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * 按时间段统计用户行为
     * 
     * @param string $actionType 行为类型
     * @param string $startTime 开始时间，默认7天前
     * @param string $endTime 结束时间，默认当前时间
     * @param string $timeUnit 时间单位(hour/day/week/month)
     * @return array
     */
    public function getActionStatsByTime(
        string $actionType, 
        string $startTime = '', 
        string $endTime = '', 
        string $timeUnit = 'day'
    ): array
    {
        try {
            // 设置默认时间范围
            if (empty($startTime)) {
                $startTime = date('Y-m-d H:i:s', strtotime('-7 days'));
            }
            
            if (empty($endTime)) {
                $endTime = date('Y-m-d H:i:s');
            }
            
            // 缓存键
            $cacheKey = "analytics:time:{$actionType}:{$startTime}:{$endTime}:{$timeUnit}";
            
            // 优先从缓存获取
            if (Cache::has($cacheKey)) {
                return Cache::get($cacheKey);
            }
            
            // 记录日志
            Log::info('按时间段统计用户行为', [
                'action_type' => $actionType,
                'time_range' => [$startTime, $endTime],
                'time_unit' => $timeUnit
            ]);
            
            // 查询统计数据
            $result = Analytics::aggregateByTime($actionType, $startTime, $endTime, $timeUnit);
            
            // 格式化结果
            $formattedResult = [];
            foreach ($result as $item) {
                $formattedResult[] = [
                    'time' => $item['_id']['time_unit'],
                    'count' => $item['count']
                ];
            }
            
            // 缓存结果，30分钟过期
            Cache::set($cacheKey, $formattedResult, 1800);
            
            return $formattedResult;
        } catch (\Exception $e) {
            Log::error('按时间段统计用户行为失败', [
                'action_type' => $actionType,
                'time_range' => [$startTime, $endTime],
                'message' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * 获取活跃用户排行
     * 
     * @param string $actionType 行为类型
     * @param string $startTime 开始时间，默认7天前
     * @param string $endTime 结束时间，默认当前时间
     * @param int $limit 返回数量限制
     * @return array
     */
    public function getActiveUsers(
        string $actionType, 
        string $startTime = '', 
        string $endTime = '', 
        int $limit = 10
    ): array
    {
        try {
            // 设置默认时间范围
            if (empty($startTime)) {
                $startTime = date('Y-m-d H:i:s', strtotime('-7 days'));
            }
            
            if (empty($endTime)) {
                $endTime = date('Y-m-d H:i:s');
            }
            
            // 缓存键
            $cacheKey = "analytics:users:{$actionType}:{$startTime}:{$endTime}:{$limit}";
            
            // 优先从缓存获取
            if (Cache::has($cacheKey)) {
                return Cache::get($cacheKey);
            }
            
            // 记录日志
            Log::info('获取活跃用户排行', [
                'action_type' => $actionType,
                'time_range' => [$startTime, $endTime],
                'limit' => $limit
            ]);
            
            // 查询统计数据
            $result = Analytics::aggregateByUser($actionType, $startTime, $endTime, $limit);
            
            // 格式化结果
            $formattedResult = [];
            foreach ($result as $item) {
                $formattedResult[] = [
                    'user_id' => $item['_id'],
                    'count' => $item['count'],
                    'last_time' => date('Y-m-d H:i:s', $item['last_time'])
                ];
            }
            
            // 缓存结果，10分钟过期
            Cache::set($cacheKey, $formattedResult, 600);
            
            return $formattedResult;
        } catch (\Exception $e) {
            Log::error('获取活跃用户排行失败', [
                'action_type' => $actionType,
                'time_range' => [$startTime, $endTime],
                'message' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * 获取行为类型占比
     * 
     * @param string $startTime 开始时间，默认7天前
     * @param string $endTime 结束时间，默认当前时间
     * @return array
     */
    public function getActionTypeDistribution(string $startTime = '', string $endTime = ''): array
    {
        try {
            // 设置默认时间范围
            if (empty($startTime)) {
                $startTime = date('Y-m-d H:i:s', strtotime('-7 days'));
            }
            
            if (empty($endTime)) {
                $endTime = date('Y-m-d H:i:s');
            }
            
            // 缓存键
            $cacheKey = "analytics:distribution:{$startTime}:{$endTime}";
            
            // 优先从缓存获取
            if (Cache::has($cacheKey)) {
                return Cache::get($cacheKey);
            }
            
            // 记录日志
            Log::info('获取行为类型占比', [
                'time_range' => [$startTime, $endTime]
            ]);
            
            // 查询统计数据
            $result = Analytics::aggregateActionTypes($startTime, $endTime);
            
            // 格式化结果
            $formattedResult = [];
            foreach ($result as $item) {
                $formattedResult[] = [
                    'action_type' => $item['_id'],
                    'count' => $item['count'],
                    'percentage' => $item['percentage']
                ];
            }
            
            // 缓存结果，1小时过期
            Cache::set($cacheKey, $formattedResult, 3600);
            
            return $formattedResult;
        } catch (\Exception $e) {
            Log::error('获取行为类型占比失败', [
                'time_range' => [$startTime, $endTime],
                'message' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * 获取用户行为路径
     * 
     * @param string $userId 用户ID
     * @param string $startTime 开始时间，默认1天前
     * @param string $endTime 结束时间，默认当前时间
     * @param int $limit 路径长度限制
     * @return array
     */
    public function getUserActionPath(
        string $userId, 
        string $startTime = '', 
        string $endTime = '', 
        int $limit = 10
    ): array
    {
        try {
            // 设置默认时间范围
            if (empty($startTime)) {
                $startTime = date('Y-m-d H:i:s', strtotime('-1 day'));
            }
            
            if (empty($endTime)) {
                $endTime = date('Y-m-d H:i:s');
            }
            
            // 缓存键
            $cacheKey = "analytics:path:{$userId}:{$startTime}:{$endTime}:{$limit}";
            
            // 优先从缓存获取
            if (Cache::has($cacheKey)) {
                return Cache::get($cacheKey);
            }
            
            // 记录日志
            Log::info('获取用户行为路径', [
                'user_id' => $userId,
                'time_range' => [$startTime, $endTime],
                'limit' => $limit
            ]);
            
            // 查询数据
            $result = Analytics::analyzeUserPath($userId, $startTime, $endTime, $limit);
            
            // 缓存结果，5分钟过期
            Cache::set($cacheKey, $result, 300);
            
            return $result;
        } catch (\Exception $e) {
            Log::error('获取用户行为路径失败', [
                'user_id' => $userId,
                'time_range' => [$startTime, $endTime],
                'message' => $e->getMessage()
            ]);
            return [];
        }
    }
} 