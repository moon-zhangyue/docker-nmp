<?php
declare(strict_types=1);

namespace app\service\redis;

use app\service\RedisService;
use Redis;

/**
 * Redis Sorted Set类型数据服务
 *
 * 提供对Redis Sorted Set类型的操作封装
 */
class ZSetService
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
     * 向有序集合添加一个或多个成员，或者更新已存在成员的分数
     *
     * @param string $key 有序集合键名
     * @param float $score 分数
     * @param mixed $member 成员
     * @return int 被成功添加的新成员的数量，不包括那些被更新的、已经存在的成员
     */
    public function zAdd(string $key, float $score, $member): int
    {
        if (is_array($member) || is_object($member)) {
            $member = json_encode($member, JSON_UNESCAPED_UNICODE);
        }

        return $this->redis->zAdd($key, $score, $member);
    }

    /**
     * 批量向有序集合添加多个成员
     *
     * @param string $key 有序集合键名
     * @param array $scoreMembers 成员数组，格式为 [成员1 => 分数1, 成员2 => 分数2, ...]
     * @return int 被成功添加的新成员的数量
     */
    public function zMAdd(string $key, array $scoreMembers): int
    {
        $params = [];
        foreach ($scoreMembers as $member => $score) {
            if (is_array($member) || is_object($member)) {
                $member = json_encode($member, JSON_UNESCAPED_UNICODE);
            }
            $params[] = $score;
            $params[] = $member;
        }

        return $this->redis->zAdd($key, ...$params);
    }

    /**
     * 获取有序集合的成员数
     *
     * @param string $key 有序集合键名
     * @return int
     */
    public function zCard(string $key): int
    {
        return $this->redis->zCard($key);
    }

    /**
     * 计算有序集合中指定分数区间的成员数量
     *
     * @param string $key 有序集合键名
     * @param string $min 最小分数
     * @param string $max 最大分数
     * @return int
     */
    public function zCount(string $key, string $min, string $max): int
    {
        return $this->redis->zCount($key, $min, $max);
    }

    /**
     * 有序集合中对指定成员的分数加上增量
     *
     * @param string $key 有序集合键名
     * @param float $increment 增量值（可以是负数）
     * @param mixed $member 成员
     * @return float 增加后的分数
     */
    public function zIncrBy(string $key, float $increment, $member): float
    {
        if (is_array($member) || is_object($member)) {
            $member = json_encode($member, JSON_UNESCAPED_UNICODE);
        }

        return $this->redis->zIncrBy($key, $increment, $member);
    }

    /**
     * 获取有序集合指定区间内的成员，按分数从小到大排序
     *
     * @param string $key 有序集合键名
     * @param int $start 开始位置
     * @param int $stop 结束位置
     * @param bool $withScores 是否返回分数
     * @param bool $isJson 是否为JSON数据
     * @return array
     */
    public function zRange(string $key, int $start, int $stop, bool $withScores = false, bool $isJson = false): array
    {
        $result = $this->redis->zRange($key, $start, $stop, $withScores);

        if ($isJson && !$withScores) {
            foreach ($result as &$member) {
                if ($member) {
                    $member = json_decode($member, true);
                }
            }
        } elseif ($isJson && $withScores) {
            $processedResult = [];
            foreach ($result as $member => $score) {
                if ($member) {
                    $decodedMember = json_decode($member, true);
                    $processedResult[$decodedMember] = $score;
                } else {
                    $processedResult[$member] = $score;
                }
            }
            $result = $processedResult;
        }

        return $result;
    }

    /**
     * 获取有序集合指定区间内的成员，按分数从大到小排序
     *
     * @param string $key 有序集合键名
     * @param int $start 开始位置
     * @param int $stop 结束位置
     * @param bool $withScores 是否返回分数
     * @param bool $isJson 是否为JSON数据
     * @return array
     */
    public function zRevRange(string $key, int $start, int $stop, bool $withScores = false, bool $isJson = false): array
    {
        $result = $this->redis->zRevRange($key, $start, $stop, $withScores);

        if ($isJson && !$withScores) {
            foreach ($result as &$member) {
                if ($member) {
                    $member = json_decode($member, true);
                }
            }
        } elseif ($isJson && $withScores) {
            $processedResult = [];
            foreach ($result as $member => $score) {
                if ($member) {
                    $decodedMember = json_decode($member, true);
                    $processedResult[$decodedMember] = $score;
                } else {
                    $processedResult[$member] = $score;
                }
            }
            $result = $processedResult;
        }

        return $result;
    }

    /**
     * 通过分数返回有序集合指定区间内的成员，分数从小到大排序
     *
     * @param string $key 有序集合键名
     * @param string $min 最小分数
     * @param string $max 最大分数
     * @param bool $withScores 是否返回分数
     * @param bool $isJson 是否为JSON数据
     * @param array $options 额外选项，如：['limit' => [offset, count]]
     * @return array
     */
    public function zRangeByScore(string $key, string $min, string $max, bool $withScores = false, bool $isJson = false, array $options = []): array
    {
        // 准备Redis参数
        $redisOptions = [];

        // 添加withscores选项
        if ($withScores) {
            $redisOptions['withscores'] = true;
        }

        // 添加limit选项
        if (!empty($options['limit']) && is_array($options['limit']) && count($options['limit']) == 2) {
            $redisOptions['limit'] = $options['limit'];
        }

        // 调用Redis原生方法
        $result = $this->redis->zRangeByScore($key, $min, $max, $redisOptions);

        if ($isJson && !$withScores) {
            foreach ($result as &$member) {
                if ($member) {
                    $member = json_decode($member, true);
                }
            }
        } elseif ($isJson && $withScores) {
            $processedResult = [];
            foreach ($result as $member => $score) {
                if ($member) {
                    $decodedMember = json_decode($member, true);
                    $processedResult[$decodedMember] = $score;
                } else {
                    $processedResult[$member] = $score;
                }
            }
            $result = $processedResult;
        }

        return $result;
    }

    /**
     * 通过分数返回有序集合指定区间内的成员，分数从大到小排序
     *
     * @param string $key 有序集合键名
     * @param string $max 最大分数
     * @param string $min 最小分数
     * @param bool $withScores 是否返回分数
     * @param bool $isJson 是否为JSON数据
     * @param array $options 额外选项，如：['limit' => [offset, count]]
     * @return array
     */
    public function zRevRangeByScore(string $key, string $max, string $min, bool $withScores = false, bool $isJson = false, array $options = []): array
    {
        // 准备Redis参数
        $redisOptions = [];

        // 添加withscores选项
        if ($withScores) {
            $redisOptions['withscores'] = true;
        }

        // 添加limit选项
        if (!empty($options['limit']) && is_array($options['limit']) && count($options['limit']) == 2) {
            $redisOptions['limit'] = $options['limit'];
        }

        // 调用Redis原生方法
        $result = $this->redis->zRevRangeByScore($key, $max, $min, $redisOptions);

        if ($isJson && !$withScores) {
            foreach ($result as &$member) {
                if ($member) {
                    $member = json_decode($member, true);
                }
            }
        } elseif ($isJson && $withScores) {
            $processedResult = [];
            foreach ($result as $member => $score) {
                if ($member) {
                    $decodedMember = json_decode($member, true);
                    $processedResult[$decodedMember] = $score;
                } else {
                    $processedResult[$member] = $score;
                }
            }
            $result = $processedResult;
        }

        return $result;
    }

    /**
     * 返回有序集合中指定成员的排名，有序集成员按分数值从小到大排序
     *
     * @param string $key 有序集合键名
     * @param mixed $member 成员
     * @return int|null 排名，从0开始，如果成员不存在返回null
     */
    public function zRank(string $key, $member): ?int
    {
        if (is_array($member) || is_object($member)) {
            $member = json_encode($member, JSON_UNESCAPED_UNICODE);
        }

        $rank = $this->redis->zRank($key, $member);
        return $rank === false ? null : $rank;
    }

    /**
     * 返回有序集合中指定成员的排名，有序集成员按分数值从大到小排序
     *
     * @param string $key 有序集合键名
     * @param mixed $member 成员
     * @return int|null 排名，从0开始，如果成员不存在返回null
     */
    public function zRevRank(string $key, $member): ?int
    {
        if (is_array($member) || is_object($member)) {
            $member = json_encode($member, JSON_UNESCAPED_UNICODE);
        }

        $rank = $this->redis->zRevRank($key, $member);
        return $rank === false ? null : $rank;
    }

    /**
     * 移除有序集合中的一个或多个成员
     *
     * @param string $key 有序集合键名
     * @param mixed $member 要移除的成员或成员数组
     * @return int 被成功移除的成员数量，不包括不存在的成员
     */
    public function zRem(string $key, $member): int
    {
        if (is_array($member)) {
            foreach ($member as &$item) {
                if (is_array($item) || is_object($item)) {
                    $item = json_encode($item, JSON_UNESCAPED_UNICODE);
                }
            }
            return $this->redis->zRem($key, ...$member);
        } else {
            if (is_array($member) || is_object($member)) {
                $member = json_encode($member, JSON_UNESCAPED_UNICODE);
            }
            return $this->redis->zRem($key, $member);
        }
    }

    /**
     * 移除有序集合中给定的排名区间的所有成员
     *
     * @param string $key 有序集合键名
     * @param int $start 开始位置
     * @param int $stop 结束位置
     * @return int 被移除成员的数量
     */
    public function zRemRangeByRank(string $key, int $start, int $stop): int
    {
        return $this->redis->zRemRangeByRank($key, $start, $stop);
    }

    /**
     * 移除有序集合中给定的分数区间的所有成员
     *
     * @param string $key 有序集合键名
     * @param string $min 最小分数
     * @param string $max 最大分数
     * @return int 被移除成员的数量
     */
    public function zRemRangeByScore(string $key, string $min, string $max): int
    {
        return $this->redis->zRemRangeByScore($key, $min, $max);
    }

    /**
     * 返回有序集中成员的分数值
     *
     * @param string $key 有序集合键名
     * @param mixed $member 成员
     * @return float|null 分数值，如果成员不存在返回null
     */
    public function zScore(string $key, $member): ?float
    {
        if (is_array($member) || is_object($member)) {
            $member = json_encode($member, JSON_UNESCAPED_UNICODE);
        }

        $score = $this->redis->zScore($key, $member);
        return $score === false ? null : $score;
    }

    /**
     * 计算给定的多个有序集的交集并将结果存储在新的有序集合中
     *
     * @param string $destination 目标有序集合键名
     * @param array $keys 有序集合键名数组
     * @param array $weights 可选，权重数组，与$keys一一对应
     * @param string $aggregate 可选，结果集的聚合方式：sum、min、max
     * @return int 目标有序集合的成员数量
     */
    public function zInterStore(string $destination, array $keys, array $weights = [], string $aggregate = 'SUM'): int
    {
        $aggregate = strtoupper($aggregate);
        if (!in_array($aggregate, ['SUM', 'MIN', 'MAX'])) {
            $aggregate = 'SUM';
        }

        return $this->redis->zInterStore($destination, $keys, $weights, $aggregate);
    }

    /**
     * 计算给定的多个有序集的并集并将结果存储在新的有序集合中
     *
     * @param string $destination 目标有序集合键名
     * @param array $keys 有序集合键名数组
     * @param array $weights 可选，权重数组，与$keys一一对应
     * @param string $aggregate 可选，结果集的聚合方式：sum、min、max
     * @return int 目标有序集合的成员数量
     */
    public function zUnionStore(string $destination, array $keys, array $weights = [], string $aggregate = 'SUM'): int
    {
        $aggregate = strtoupper($aggregate);
        if (!in_array($aggregate, ['SUM', 'MIN', 'MAX'])) {
            $aggregate = 'SUM';
        }

        return $this->redis->zUnionStore($destination, $keys, $weights, $aggregate);
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