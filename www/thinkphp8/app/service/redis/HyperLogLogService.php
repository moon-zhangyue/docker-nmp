<?php
declare(strict_types=1);

namespace app\service\redis;

use app\service\RedisService;
use Redis;

/**
 * Redis HyperLogLog类型数据服务
 *
 * 提供对Redis HyperLogLog类型的操作封装，用于基数统计
 */
class HyperLogLogService
{
    /**
     * Redis服务实例
     *
     * @var RedisService
     */
    protected RedisService $redisService;

    /**
     * Redis实例
     *
     * @var Redis
     */
    protected Redis $redis;

    /**
     * 构造函数
     *
     * @param RedisService|null $redisService
     */
    public function __construct(?RedisService $redisService = null)
    {
        $this->redisService = $redisService ?? new RedisService();
        $this->redis        = $this->redisService->getRedis();
    }

    /**
     * 添加元素到HyperLogLog
     *
     * @param string $key 键名
     * @param array $element 元素数组
     * @return int 如果至少有一个元素被添加，返回1，否则返回0
     */
    public function pfAdd(string $key, array $element): int
    {
        return $this->redis->pfAdd($key, $element);
    }

    /**
     * 获取HyperLogLog的基数估计值
     *
     * @param string $key 键名
     * @return int 基数估计值
     */
    public function pfCount(string $key): int
    {
        return $this->redis->pfCount($key);
    }

    /**
     * 获取多个HyperLogLog的合并基数估计值
     *
     * @param array $keys 键名数组
     * @return int 合并后的基数估计值
     */
    public function pfMergeCount(array $keys): int
    {
        if (count($keys) === 1) {
            return $this->redis->pfCount($keys[0]);
        }
        
        // 先合并到临时键，然后统计后删除
        $tempKey = 'temp_pfmerge_' . uniqid();
        $this->pfMerge($tempKey, $keys);
        $count = $this->redis->pfCount($tempKey);
        $this->redis->del($tempKey);
        
        return $count;
    }

    /**
     * 将多个HyperLogLog合并为一个HyperLogLog
     *
     * @param string $destKey 目标键名
     * @param array $sourceKeys 源键名数组
     * @return bool 操作是否成功
     */
    public function pfMerge(string $destKey, array $sourceKeys): bool
    {
        return $this->redis->pfMerge($destKey, $sourceKeys);
    }

    /**
     * 删除一个或多个键
     *
     * @param string|array $keys 键名或键名数组
     * @return int 删除的键数量
     */
    public function del($keys): int
    {
        return $this->redis->del($keys);
    }
}