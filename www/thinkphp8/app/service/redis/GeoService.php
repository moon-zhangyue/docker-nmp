<?php
declare(strict_types=1);

namespace app\service\redis;

use app\service\RedisService;
use Redis;

/**
 * Redis Geo类型数据服务
 *
 * 提供对Redis地理位置功能的操作封装
 */
class GeoService
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
        $this->redis = $this->redisService->getRedis();
    }

    /**
     * 添加地理位置信息
     *
     * @param string $key 键名
     * @param float $longitude 经度
     * @param float $latitude 纬度
     * @param string $member 成员名
     * @return int 添加的元素数量
     */
    public function geoAdd(string $key, float $longitude, float $latitude, string $member): int
    {
        return $this->redis->geoAdd($key, $longitude, $latitude, $member);
    }

    /**
     * 批量添加地理位置信息
     *
     * @param string $key 键名
     * @param array $locationMembers 位置信息数组，格式为：[[经度1, 纬度1, 成员名1], [经度2, 纬度2, 成员名2], ...]
     * @return int 添加的元素数量
     */
    public function geoBatchAdd(string $key, array $locationMembers): int
    {
        $params = [];
        foreach ($locationMembers as $item) {
            if (count($item) === 3) {
                $params[] = $item[0]; // 经度
                $params[] = $item[1]; // 纬度
                $params[] = $item[2]; // 成员名
            }
        }

        if (empty($params)) {
            return 0;
        }

        return $this->redis->geoAdd($key, ...$params);
    }

    /**
     * 获取地理位置的坐标
     *
     * @param string $key 键名
     * @param string $member 成员名
     * @return array|null 坐标数组 [经度, 纬度]，不存在则返回null
     */
    public function geoPos(string $key, string $member): ?array
    {
        $result = $this->redis->geoPos($key, $member);

        if (empty($result) || empty($result[0])) {
            return null;
        }

        return $result[0];
    }

    /**
     * 计算两个位置之间的距离
     *
     * @param string $key 键名
     * @param string $member1 成员名1
     * @param string $member2 成员名2
     * @param string $unit 单位，可选值：m（米）、km（千米）、mi（英里）、ft（英尺）
     * @return float|null 距离，如果成员不存在则返回null
     */
    public function geoDist(string $key, string $member1, string $member2, string $unit = 'm'): ?float
    {
        $result = $this->redis->geoDist($key, $member1, $member2, $unit);
        return $result === false ? null : $result;
    }

    /**
     * 根据经纬度获取指定范围内的地理位置信息
     *
     * @param string $key 键名
     * @param float $longitude 经度
     * @param float $latitude 纬度
     * @param float $radius 半径
     * @param string $unit 单位，可选值：m（米）、km（千米）、mi（英里）、ft（英尺）
     * @param array $options 选项，可包含：
     *                      'withCoord' => true/false 是否返回坐标
     *                      'withDist' => true/false 是否返回距离
     *                      'withHash' => true/false 是否返回geo hash值
     *                      'count' => int 返回的数量限制
     *                      'sort' => string 排序方式，ASC/DESC
     * @return array 地理位置信息数组
     */
    public function geoRadius(string $key, float $longitude, float $latitude, float $radius, string $unit = 'm', array $options = []): array
    {
        return $this->redis->geoRadius($key, $longitude, $latitude, $radius, $unit, $options);
    }

    /**
     * 根据成员获取指定范围内的地理位置信息
     *
     * @param string $key 键名
     * @param string $member 成员名
     * @param float $radius 半径
     * @param string $unit 单位，可选值：m（米）、km（千米）、mi（英里）、ft（英尺）
     * @param array $options 选项，可包含：
     *                      'withCoord' => true/false 是否返回坐标
     *                      'withDist' => true/false 是否返回距离
     *                      'withHash' => true/false 是否返回geo hash值
     *                      'count' => int 返回的数量限制
     *                      'sort' => string 排序方式，ASC/DESC
     * @return array 地理位置信息数组
     */
    public function geoRadiusByMember(string $key, string $member, float $radius, string $unit = 'm', array $options = []): array
    {
        return $this->redis->geoRadiusByMember($key, $member, $radius, $unit, $options);
    }

    /**
     * 获取地理位置的GeoHash值
     *
     * @param string $key 键名
     * @param string $member 成员名
     * @return string|null GeoHash值，不存在则返回null
     */
    public function geoHash(string $key, string $member): ?string
    {
        $result = $this->redis->geoHash($key, $member);

        if (empty($result) || !isset($result[0])) {
            return null;
        }

        return $result[0];
    }

    /**
     * 批量获取地理位置的GeoHash值
     *
     * @param string $key 键名
     * @param array $members 成员名数组
     * @return array GeoHash值数组
     */
    public function geoBatchHash(string $key, array $members): array
    {
        return $this->redis->geoHash($key, ...$members);
    }

    /**
     * 删除一个或多个键
     *
     * @param string|array $keys 键名或键名数组
     * @return int 删除的键数量
     */
    public function delete($keys): int
    {
        return $this->redis->del($keys);
    }
}