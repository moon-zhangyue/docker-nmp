<?php
declare(strict_types=1);

namespace app\service\redis;

use app\service\RedisService;
use Redis;

/**
 * Redis List类型数据服务
 *
 * 提供对Redis List类型的操作封装
 */
class ListService
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
     * 将一个或多个值插入到列表头部
     *
     * @param string $key 列表键名
     * @param mixed $value 要插入的值或值数组
     * @return int 列表长度
     */
    public function lPush(string $key, $value): int
    {
        if (is_array($value)) {
            foreach ($value as &$item) {
                if (is_array($item) || is_object($item)) {
                    $item = json_encode($item, JSON_UNESCAPED_UNICODE);
                }
            }
            return $this->redis->lPush($key, ...$value);
        } else {
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
            return $this->redis->lPush($key, $value);
        }
    }

    /**
     * 将一个或多个值插入到列表尾部
     *
     * @param string $key 列表键名
     * @param mixed $value 要插入的值或值数组
     * @return int 列表长度
     */
    public function rPush(string $key, $value): int
    {
        if (is_array($value)) {
            foreach ($value as &$item) {
                if (is_array($item) || is_object($item)) {
                    $item = json_encode($item, JSON_UNESCAPED_UNICODE);
                }
            }
            return $this->redis->rPush($key, ...$value);
        } else {
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
            return $this->redis->rPush($key, $value);
        }
    }

    /**
     * 移除并返回列表的第一个元素
     *
     * @param string $key 列表键名
     * @param bool $isJson 是否为JSON数据
     * @return mixed
     */
    public function lPop(string $key, bool $isJson = false)
    {
        $value = $this->redis->lPop($key);

        if ($value === false) {
            return null;
        }

        if ($isJson && $value) {
            return json_decode($value, true);
        }

        return $value;
    }

    /**
     * 移除并返回列表的最后一个元素
     *
     * @param string $key 列表键名
     * @param bool $isJson 是否为JSON数据
     * @return mixed
     */
    public function rPop(string $key, bool $isJson = false)
    {
        $value = $this->redis->rPop($key);

        if ($value === false) {
            return null;
        }

        if ($isJson && $value) {
            return json_decode($value, true);
        }

        return $value;
    }

    /**
     * 从列表中弹出最后一个元素，并将其插入到另一个列表的头部，并返回这个元素
     *
     * @param string $source 源列表键名
     * @param string $destination 目标列表键名
     * @param bool $isJson 是否为JSON数据
     * @return mixed
     */
    public function rPopLPush(string $source, string $destination, bool $isJson = false)
    {
        $value = $this->redis->rPopLPush($source, $destination);

        if ($value === false) {
            return null;
        }

        if ($isJson && $value) {
            return json_decode($value, true);
        }

        return $value;
    }

    /**
     * 获取列表长度
     *
     * @param string $key 列表键名
     * @return int
     */
    public function lLen(string $key): int
    {
        return $this->redis->lLen($key);
    }

    /**
     * 通过索引获取列表中的元素
     *
     * @param string $key 列表键名
     * @param int $index 索引
     * @param bool $isJson 是否为JSON数据
     * @return mixed
     */
    public function lIndex(string $key, int $index, bool $isJson = false)
    {
        $value = $this->redis->lIndex($key, $index);

        if ($value === false) {
            return null;
        }

        if ($isJson && $value) {
            return json_decode($value, true);
        }

        return $value;
    }

    /**
     * 获取列表指定范围内的元素
     *
     * @param string $key 列表键名
     * @param int $start 开始位置
     * @param int $stop 结束位置
     * @param bool $isJson 是否为JSON数据
     * @return array
     */
    public function lRange(string $key, int $start, int $stop, bool $isJson = false): array
    {
        $values = $this->redis->lRange($key, $start, $stop);

        if ($isJson) {
            foreach ($values as &$value) {
                if ($value) {
                    $value = json_decode($value, true);
                }
            }
        }

        return $values;
    }

    /**
     * 根据参数 COUNT 的值，移除列表中与参数 VALUE 相等的元素
     *
     * @param string $key 列表键名
     * @param string $value 值
     * @param int $count 数量，0表示所有匹配的元素，正数表示从头部开始，负数表示从尾部开始
     * @return int 被移除元素的数量
     */
    public function lRem(string $key, string $value, int $count = 0): int
    {
        return $this->redis->lRem($key, $value, $count);
    }

    /**
     * 对一个列表进行修剪，只保留指定区间内的元素
     *
     * @param string $key 列表键名
     * @param int $start 开始位置
     * @param int $stop 结束位置
     * @return bool
     */
    public function lTrim(string $key, int $start, int $stop): bool
    {
        return $this->redis->lTrim($key, $start, $stop) === true;
    }

    /**
     * 将列表 key 下标为 index 的元素的值设置为 value
     *
     * @param string $key 列表键名
     * @param int $index 下标
     * @param mixed $value 值
     * @return bool
     */
    public function lSet(string $key, int $index, $value): bool
    {
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return $this->redis->lSet($key, $index, $value) === true;
    }

    /**
     * 在列表的元素前或者后插入元素
     *
     * @param string $key 列表键名
     * @param string $pivot 列表中的元素
     * @param mixed $value 待插入的值
     * @param bool $after 是否在pivot元素之后插入，默认为true，false表示在pivot元素之前插入
     * @return int 列表长度，-1表示pivot元素不存在
     */
    public function lInsert(string $key, string $pivot, $value, bool $after = true): int
    {
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return $this->redis->lInsert($key, $after ? Redis::AFTER : Redis::BEFORE, $pivot, $value);
    }

    /**
     * 移出并获取列表的第一个元素， 如果列表没有元素会阻塞列表直到等待超时或发现可弹出元素为止
     *
     * @param array $keys 列表键名数组
     * @param int $timeout 超时时间（秒）
     * @param bool $isJson 是否为JSON数据
     * @return array|null 返回一个含有key和value的数组，如果超时返回null
     */
    public function blPop(array $keys, int $timeout, bool $isJson = false): ?array
    {
        $result = $this->redis->blPop($keys, $timeout);

        if (!$result) {
            return null;
        }

        if ($isJson && isset($result[1])) {
            $result[1] = json_decode($result[1], true);
        }

        return $result;
    }

    /**
     * 移出并获取列表的最后一个元素， 如果列表没有元素会阻塞列表直到等待超时或发现可弹出元素为止
     *
     * @param array $keys 列表键名数组
     * @param int $timeout 超时时间（秒）
     * @param bool $isJson 是否为JSON数据
     * @return array|null 返回一个含有key和value的数组，如果超时返回null
     */
    public function brPop(array $keys, int $timeout, bool $isJson = false): ?array
    {
        $result = $this->redis->brPop($keys, $timeout);

        if (!$result) {
            return null;
        }

        if ($isJson && isset($result[1])) {
            $result[1] = json_decode($result[1], true);
        }

        return $result;
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