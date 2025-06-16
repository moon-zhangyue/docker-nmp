<?php
declare(strict_types=1);

namespace app\service\mongo;

use app\model\GlobalData;
use think\facade\Log;
use think\facade\Cache;

class GlobalDataService
{
    /**
     * 保存全球数据
     * 
     * @param array $data 数据内容
     * @return array|null
     */
    public function saveData(array $data): ?array
    {
        try {
            // 验证必要字段
            if (empty($data['region']) || empty($data['content'])) {
                throw new \Exception('区域和内容不能为空');
            }

            // 记录日志
            Log::info('保存全球数据: 区域 {region}', ['region' => $data['region']]);

            // 保存数据
            $globalData = GlobalData::saveGlobalData($data);

            return $globalData ? $globalData->toArray() : null;
        } catch (\Exception $e) {
            Log::error('保存全球数据失败: {message}', ['message' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * 更新全球数据
     * 
     * @param mixed $id 数据ID
     * @param array $data 更新数据
     * @return bool
     */
    public function updateData($id, array $data): bool
    {
        try {
            // 记录日志
            Log::info('更新全球数据: ID {id}', ['id' => $id]);

            // 更新数据
            return GlobalData::updateGlobalData($id, $data);
        } catch (\Exception $e) {
            Log::error('更新全球数据失败: ID {id}, 错误 {message}', ['id' => $id, 'message' => $e->getMessage()]);
            throw $e;
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
    public function getDataByRegion(string $region, array $conditions = [], int $page = 1, int $limit = 20): array
    {
        try {
            // 缓存键
            $cacheKey = "global:region:{$region}:" . md5(json_encode($conditions)) . ":{$page}:{$limit}";

            // 优先从缓存获取
            if (Cache::has($cacheKey)) {
                return Cache::get($cacheKey);
            }

            // 记录日志
            Log::info('根据区域查询数据: 区域 {region}, 页码 {page}, 每页 {limit}', [
                'region' => $region,
                'page'   => $page,
                'limit'  => $limit
            ]);

            // 查询数据
            $data = GlobalData::getDataByRegion($region, $conditions, $page, $limit);

            // 缓存结果，5分钟过期
            Cache::set($cacheKey, $data, 300);

            return $data;
        } catch (\Exception $e) {
            Log::error('根据区域查询数据失败: 区域 {region}, 错误 {message}', [
                'region'  => $region,
                'message' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * 获取全球区域统计
     * 
     * @param array $conditions 过滤条件
     * @return array
     */
    public function getRegionStats(array $conditions = []): array
    {
        try {
            // 缓存键
            $cacheKey = "global:regions:" . md5(json_encode($conditions));

            // 优先从缓存获取
            if (Cache::has($cacheKey)) {
                return Cache::get($cacheKey);
            }

            // 记录日志
            Log::info('获取全球区域统计: 条件 {conditions}', ['conditions' => json_encode($conditions)]);

            // 查询统计
            $stats = GlobalData::globalAggregate('region', $conditions);

            // 缓存结果，10分钟过期
            Cache::set($cacheKey, $stats, 600);

            return $stats;
        } catch (\Exception $e) {
            Log::error('获取全球区域统计失败: {message}', ['message' => $e->getMessage()]);
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
    public function compareRegions(array $regions, string $metric): array
    {
        try {
            // 验证必要参数
            if (empty($regions) || empty($metric)) {
                throw new \Exception('区域列表和对比指标不能为空');
            }

            // 缓存键
            $cacheKey = "global:compare:" . md5(json_encode($regions)) . ":{$metric}";

            // 优先从缓存获取
            if (Cache::has($cacheKey)) {
                return Cache::get($cacheKey);
            }

            // 记录日志
            Log::info('多区域数据对比: 指标 {metric}, 区域数 {region_count}', [
                'metric'       => $metric,
                'region_count' => count($regions)
            ]);

            // 执行对比
            $result = GlobalData::compareRegions($regions, $metric);

            // 缓存结果，15分钟过期
            Cache::set($cacheKey, $result, 900);

            return $result;
        } catch (\Exception $e) {
            Log::error('多区域数据对比失败: 指标 {metric}, 错误 {message}', [
                'metric'  => $metric,
                'message' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * 获取热门区域
     * 
     * @param int $limit 限制数量
     * @return array
     */
    public function getHotRegions(int $limit = 10): array
    {
        try {
            // 缓存键
            $cacheKey = "global:hot_regions:{$limit}";

            // 优先从缓存获取
            if (Cache::has($cacheKey)) {
                return Cache::get($cacheKey);
            }

            // 记录日志
            Log::info('获取热门区域: 限制 {limit}条', ['limit' => $limit]);

            // 查询统计
            $stats = GlobalData::globalAggregate('region');

            // 截取指定数量
            $hotRegions = array_slice($stats, 0, $limit);

            // 缓存结果，1小时过期
            Cache::set($cacheKey, $hotRegions, 3600);

            return $hotRegions;
        } catch (\Exception $e) {
            Log::error('获取热门区域失败: {message}', ['message' => $e->getMessage()]);
            return [];
        }
    }
}