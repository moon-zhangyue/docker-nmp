<?php
declare(strict_types=1);

namespace app\service\redis;

use app\service\RedisService;
use Redis;

/**
 * Redis Set类型数据服务
 *
 * 提供对Redis Set类型的操作封装
 */
class SetService
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
     * 向集合添加一个或多个成员
     *
     * @param string $key 集合键名
     * @param mixed $member 要添加的成员或成员数组
     * @return int 添加到集合的新成员数量，不包括已经存在的成员
     */
    public function sAdd(string $key, $member): int
    {
        if (is_array($member)) {
            foreach ($member as &$item) {
                if (is_array($item) || is_object($item)) {
                    $item = json_encode($item, JSON_UNESCAPED_UNICODE);
                }
            }
            return $this->redis->sAdd($key, ...$member);
        } else {
            if (is_array($member) || is_object($member)) {
                $member = json_encode($member, JSON_UNESCAPED_UNICODE);
            }
            return $this->redis->sAdd($key, $member);
        }
    }

    /**
     * 返回集合中的所有成员
     *
     * @param string $key 集合键名
     * @param bool $isJson 是否为JSON数据
     * @return array
     */
    public function sMembers(string $key, bool $isJson = false): array
    {
        $members = $this->redis->sMembers($key);

        if ($isJson) {
            foreach ($members as &$member) {
                if ($member) {
                    $member = json_decode($member, true);
                }
            }
        }

        return $members;
    }

    /**
     * 判断成员元素是否是集合的成员
     *
     * @param string $key 集合键名
     * @param mixed $member 成员
     * @return bool
     */
    public function sIsMember(string $key, $member): bool
    {
        if (is_array($member) || is_object($member)) {
            $member = json_encode($member, JSON_UNESCAPED_UNICODE);
        }

        return (bool)$this->redis->sIsMember($key, $member);
    }

    /**
     * 获取集合的成员数
     *
     * @param string $key 集合键名
     * @return int
     */
    public function sCard(string $key): int
    {
        return $this->redis->sCard($key);
    }

    /**
     * 移除集合中一个或多个成员
     *
     * @param string $key 集合键名
     * @param mixed $member 要移除的成员或成员数组
     * @return int 被成功移除的成员数量，不包括不存在的成员
     */
    public function sRem(string $key, $member): int
    {
        if (is_array($member)) {
            foreach ($member as &$item) {
                if (is_array($item) || is_object($item)) {
                    $item = json_encode($item, JSON_UNESCAPED_UNICODE);
                }
            }
            return $this->redis->sRem($key, ...$member);
        } else {
            if (is_array($member) || is_object($member)) {
                $member = json_encode($member, JSON_UNESCAPED_UNICODE);
            }
            return $this->redis->sRem($key, $member);
        }
    }

    /**
     * 返回给定集合的交集
     *
     * @param array $keys 集合键名数组
     * @param bool $isJson 是否为JSON数据
     * @return array
     */
    public function sInter(array $keys, bool $isJson = false): array
    {
        $result = $this->redis->sInter(...$keys);

        if ($isJson) {
            foreach ($result as &$item) {
                if ($item) {
                    $item = json_decode($item, true);
                }
            }
        }

        return $result;
    }

    /**
     * 返回给定集合的并集
     *
     * @param array $keys 集合键名数组
     * @param bool $isJson 是否为JSON数据
     * @return array
     */
    public function sUnion(array $keys, bool $isJson = false): array
    {
        $result = $this->redis->sUnion(...$keys);

        if ($isJson) {
            foreach ($result as &$item) {
                if ($item) {
                    $item = json_decode($item, true);
                }
            }
        }

        return $result;
    }

    /**
     * 返回给定集合的差集
     *
     * @param array $keys 集合键名数组
     * @param bool $isJson 是否为JSON数据
     * @return array
     */
    public function sDiff(array $keys, bool $isJson = false): array
    {
        $result = $this->redis->sDiff(...$keys);

        if ($isJson) {
            foreach ($result as &$item) {
                if ($item) {
                    $item = json_decode($item, true);
                }
            }
        }

        return $result;
    }

    /**
     * 将给定集合的交集存储在目标集合中
     *
     * @param string $destination 目标集合键名
     * @param array $keys 集合键名数组
     * @return int 目标集合的成员数量
     */
    public function sInterStore(string $destination, array $keys): int
    {
        return $this->redis->sInterStore($destination, ...$keys);
    }

    /**
     * 将给定集合的并集存储在目标集合中
     *
     * @param string $destination 目标集合键名
     * @param array $keys 集合键名数组
     * @return int 目标集合的成员数量
     */
    public function sUnionStore(string $destination, array $keys): int
    {
        return $this->redis->sUnionStore($destination, ...$keys);
    }

    /**
     * 将给定集合的差集存储在目标集合中
     *
     * @param string $destination 目标集合键名
     * @param array $keys 集合键名数组
     * @return int 目标集合的成员数量
     */
    public function sDiffStore(string $destination, array $keys): int
    {
        return $this->redis->sDiffStore($destination, ...$keys);
    }

    /**
     * 随机获取集合中的一个或多个成员
     *
     * @param string $key 集合键名
     * @param int $count 要获取的成员数量，默认为1
     * @param bool $isJson 是否为JSON数据
     * @return mixed 为1时返回单个成员，大于1时返回成员数组
     */
    public function sRandMember(string $key, int $count = 1, bool $isJson = false)
    {
        $result = $this->redis->sRandMember($key, $count);

        if ($isJson) {
            if (is_array($result)) {
                foreach ($result as &$item) {
                    if ($item) {
                        $item = json_decode($item, true);
                    }
                }
            } elseif ($result) {
                $result = json_decode($result, true);
            }
        }

        return $result;
    }

    /**
     * 移除并返回集合中的一个随机元素
     *
     * @param string $key 集合键名
     * @param bool $isJson 是否为JSON数据
     * @return mixed
     */
    public function sPop(string $key, bool $isJson = false)
    {
        $result = $this->redis->sPop($key);

        if ($result === false) {
            return null;
        }

        if ($isJson && $result) {
            return json_decode($result, true);
        }

        return $result;
    }

    /**
     * 将元素从一个集合移到另一个集合
     *
     * @param string $source 源集合键名
     * @param string $destination 目标集合键名
     * @param mixed $member 要移动的成员
     * @return bool
     */
    public function sMove(string $source, string $destination, $member): bool
    {
        if (is_array($member) || is_object($member)) {
            $member = json_encode($member, JSON_UNESCAPED_UNICODE);
        }

        return (bool)$this->redis->sMove($source, $destination, $member);
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