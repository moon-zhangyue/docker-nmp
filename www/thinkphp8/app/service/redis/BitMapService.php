<?php
declare(strict_types=1);

namespace app\service\redis;

use app\service\RedisService;
use Redis;

/**
 * Redis BitMap类型数据服务
 *
 * 提供对Redis BitMap类型的操作封装，用于位图操作
 */
class BitMapService
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
     * 设置位图中指定偏移量的位（bit）
     *
     * @param string $key 键名
     * @param int $offset 偏移量
     * @param bool|int $value 值，true/false或1/0
     * @return int 原来位的值
     */
    public function setBit(string $key, int $offset, $value): int
    {
        // 确保值为布尔类型
        $boolValue = (bool)$value;
        return $this->redis->setBit($key, $offset, $boolValue);
    }

    /**
     * 获取位图中指定偏移量的位（bit）
     *
     * @param string $key 键名
     * @param int $offset 偏移量
     * @return int 位的值，0或1
     */
    public function getBit(string $key, int $offset): int
    {
        return $this->redis->getBit($key, $offset);
    }

    /**
     * 统计位图中位值为1的数量
     *
     * @param string $key 键名
     * @param int $start 开始字节
     * @param int|null $end 结束字节，默认为-1，表示到最后一个字节
     * @return int 位值为1的数量
     */
    public function bitCount(string $key, int $start = 0, ?int $end = -1): int
    {
        return $this->redis->bitCount($key, $start, $end);
    }

    /**
     * 对多个位图进行位运算，并将结果存储在目标键
     *
     * @param string $operation 操作类型，可选值：AND、OR、XOR、NOT
     * @param string $destKey 目标键名
     * @param string|array $keys 源键名或键名数组
     * @return int 结果位图中位值为1的数量
     */
    public function bitOp(string $operation, string $destKey, $keys): int
    {
        $operation = strtoupper($operation);

        if (is_array($keys)) {
            return $this->redis->bitOp($operation, $destKey, ...$keys);
        } else {
            return $this->redis->bitOp($operation, $destKey, $keys);
        }
    }

    /**
     * 查找位图中第一个指定值的位的位置
     *
     * @param string $key 键名
     * @param bool|int $bit 要查找的位值，true/false或1/0
     * @param int $start 开始查找的位置
     * @param int|null $end 结束查找的位置，默认为-1，表示到最后一个位
     * @return int 位置，如果未找到则返回-1
     */
    public function bitPos(string $key, $bit, int $start = 0, ?int $end = -1): int
    {
        // 确保值为布尔类型
        $boolValue = (bool)$bit;
        return $this->redis->bitPos($key, $boolValue, $start, $end);
    }

    /**
     * 设置用户指定日期的活跃状态
     *
     * @param string $key 键名前缀，通常为user:sign:
     * @param int $userId 用户ID
     * @param string $date 日期，格式为YYYY-MM-DD
     * @param bool|int $status 状态，true/false或1/0
     * @return int 原来的状态
     */
    public function setDailyActive(string $key, int $userId, string $date, $status = 1): int
    {
        $datetime = new \DateTime($date);
        $offset = (int)$datetime->format('z'); // 获取一年中的第几天（0-365）
        $yearKey = "{$key}{$userId}:{$datetime->format('Y')}";

        return $this->setBit($yearKey, $offset, $status);
    }

    /**
     * 检查用户指定日期是否活跃
     *
     * @param string $key 键名前缀，通常为user:sign:
     * @param int $userId 用户ID
     * @param string $date 日期，格式为YYYY-MM-DD
     * @return bool 是否活跃
     */
    public function checkDailyActive(string $key, int $userId, string $date): bool
    {
        $datetime = new \DateTime($date);
        $offset = (int)$datetime->format('z'); // 获取一年中的第几天（0-365）
        $yearKey = "{$key}{$userId}:{$datetime->format('Y')}";

        return (bool)$this->getBit($yearKey, $offset);
    }

    /**
     * 获取用户指定年份的活跃天数
     *
     * @param string $key 键名前缀，通常为user:sign:
     * @param int $userId 用户ID
     * @param string $year 年份，格式为YYYY
     * @return int 活跃天数
     */
    public function getYearlyActiveDays(string $key, int $userId, string $year): int
    {
        $yearKey = "{$key}{$userId}:{$year}";

        return $this->bitCount($yearKey);
    }

    /**
     * 获取用户指定年月的活跃天数
     *
     * @param string $key 键名前缀，通常为user:sign:
     * @param int $userId 用户ID
     * @param string $yearMonth 年月，格式为YYYY-MM
     * @return int 活跃天数
     */
    public function getMonthlyActiveDays(string $key, int $userId, string $yearMonth): int
    {
        $datetime = new \DateTime("{$yearMonth}-01");
        $year = $datetime->format('Y');
        $month = (int)$datetime->format('m');

        $yearKey = "{$key}{$userId}:{$year}";

        // 获取这个月第一天是一年中的第几天
        $firstDay = new \DateTime("{$year}-{$month}-01");
        $firstDayOffset = (int)$firstDay->format('z');

        // 获取这个月最后一天是一年中的第几天
        $lastDay = clone $firstDay;
        $lastDay->modify('last day of this month');
        $lastDayOffset = (int)$lastDay->format('z');

        // 计算这个月的天数
        return $this->bitCount($yearKey, $firstDayOffset, $lastDayOffset);
    }

    /**
     * 获取用户最近N天的活跃状态
     *
     * @param string $key 键名前缀，通常为user:sign:
     * @param int $userId 用户ID
     * @param int $days 天数
     * @return array 活跃状态数组，格式为 ['2023-01-01' => 1, '2023-01-02' => 0, ...]
     */
    public function getRecentActiveDays(string $key, int $userId, int $days = 7): array
    {
        $result = [];
        $today = new \DateTime();

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = clone $today;
            $date->modify("-{$i} days");

            $dateStr = $date->format('Y-m-d');
            $isActive = $this->checkDailyActive($key, $userId, $dateStr);

            $result[$dateStr] = $isActive ? 1 : 0;
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