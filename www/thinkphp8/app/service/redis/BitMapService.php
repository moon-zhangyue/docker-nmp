<?php
declare(strict_types=1);

namespace app\service\redis;

use app\service\RedisService;
use Redis;
use think\facade\Log;

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
        $this->redis        = $this->redisService->getRedis();
        Log::info('BitMapService实例化完成');
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
        $boolValue = (bool) $value;
        try {
            $result = $this->redis->setBit($key, $offset, $boolValue);
            Log::debug('setBit操作，key: {key}, offset: {offset}, value: {value}, result: {result}', [
                'key'    => $key,
                'offset' => $offset,
                'value'  => $value,
                'result' => $result
            ]);
            return $result;
        } catch (\Throwable $e) {
            Log::error('setBit操作失败，key: {key}, offset: {offset}, value: {value}, error: {error}', [
                'key'    => $key,
                'offset' => $offset,
                'value'  => $value,
                'error'  => $e->getMessage()
            ]);
            throw $e;
        }
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
        try {
            $result = $this->redis->getBit($key, $offset);
            Log::debug('getBit操作，key: {key}, offset: {offset}, result: {result}', [
                'key'    => $key,
                'offset' => $offset,
                'result' => $result
            ]);
            return $result;
        } catch (\Throwable $e) {
            Log::error('getBit操作失败，key: {key}, offset: {offset}, error: {error}', [
                'key'    => $key,
                'offset' => $offset,
                'error'  => $e->getMessage()
            ]);
            throw $e;
        }
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
        try {
            $result = $this->redis->bitCount($key, $start, $end);
            Log::debug('bitCount操作，key: {key}, start: {start}, end: {end}, result: {result}', [
                'key'    => $key,
                'start'  => $start,
                'end'    => $end,
                'result' => $result
            ]);
            return $result;
        } catch (\Throwable $e) {
            Log::error('bitCount操作失败，key: {key}, start: {start}, end: {end}, error: {error}', [
                'key'   => $key,
                'start' => $start,
                'end'   => $end,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
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
        // 在方法开始时就处理keys描述，确保在try和catch中都可用
        $keysDescription = is_array($keys) ? implode(',', $keys) : (string)$keys;
        
        try {
            $result = is_array($keys) 
                ? $this->redis->bitOp($operation, $destKey, ...$keys)
                : $this->redis->bitOp($operation, $destKey, $keys);
                
            Log::debug('bitOp操作，operation: {operation}, destKey: {destKey}, keys: {keys}, result: {result}', [
                'operation' => $operation,
                'destKey'   => $destKey,
                'keys'      => $keysDescription,
                'result'    => (int)$result
            ]);
            return (int)$result;
        } catch (\Throwable $e) {
            Log::error('bitOp操作失败，operation: {operation}, destKey: {destKey}, keys: {keys}, error: {error}', [
                'operation' => $operation,
                'destKey'   => $destKey,
                'keys'      => $keysDescription,
                'error'     => $e->getMessage()
            ]);
            throw $e;
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
        $boolValue = (bool) $bit;
        try {
            $result = $this->redis->bitPos($key, $boolValue, $start, $end);
            Log::debug('bitPos操作，key: {key}, bit: {bit}, start: {start}, end: {end}, result: {result}', [
                'key'    => $key,
                'bit'    => $bit,
                'start'  => $start,
                'end'    => $end,
                'result' => $result
            ]);
            return $result;
        } catch (\Throwable $e) {
            Log::error('bitPos操作失败，key: {key}, bit: {bit}, start: {start}, end: {end}, error: {error}', [
                'key'   => $key,
                'bit'   => $bit,
                'start' => $start,
                'end'   => $end,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
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
        Log::info('设置用户日活状态，userId: {userId}, date: {date}, status: {status}', [
            'userId' => $userId,
            'date'   => $date,
            'status' => $status
        ]);

        try {
            $datetime = new \DateTime($date);
            $offset   = (int) $datetime->format('z'); // 获取一年中的第几天（0-365）
            $yearKey  = "{$key}{$userId}:{$datetime->format('Y')}";

            $result = $this->setBit($yearKey, $offset, $status);

            Log::info('用户日活状态设置完成，userId: {userId}, date: {date}, yearKey: {yearKey}, offset: {offset}, result: {result}', [
                'userId'  => $userId,
                'date'    => $date,
                'yearKey' => $yearKey,
                'offset'  => $offset,
                'result'  => $result
            ]);

            return $result;
        } catch (\Throwable $e) {
            Log::error('设置用户日活状态失败，userId: {userId}, date: {date}, error: {error}', [
                'userId' => $userId,
                'date'   => $date,
                'error'  => $e->getMessage()
            ]);
            throw $e;
        }
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
        try {
            $datetime = new \DateTime($date);
            $offset   = (int) $datetime->format('z'); // 获取一年中的第几天（0-365）
            $yearKey  = "{$key}{$userId}:{$datetime->format('Y')}";

            $result = (bool) $this->getBit($yearKey, $offset);

            Log::debug('检查用户日活状态，userId: {userId}, date: {date}, yearKey: {yearKey}, offset: {offset}, result: {result}', [
                'userId'  => $userId,
                'date'    => $date,
                'yearKey' => $yearKey,
                'offset'  => $offset,
                'result'  => $result
            ]);

            return $result;
        } catch (\Throwable $e) {
            Log::error('检查用户日活状态失败，userId: {userId}, date: {date}, error: {error}', [
                'userId' => $userId,
                'date'   => $date,
                'error'  => $e->getMessage()
            ]);
            throw $e;
        }
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
        try {
            $yearKey = "{$key}{$userId}:{$year}";
            $result  = $this->bitCount($yearKey);

            Log::info('获取用户年度活跃天数，userId: {userId}, year: {year}, yearKey: {yearKey}, activeDays: {activeDays}', [
                'userId'     => $userId,
                'year'       => $year,
                'yearKey'    => $yearKey,
                'activeDays' => $result
            ]);

            return $result;
        } catch (\Throwable $e) {
            Log::error('获取用户年度活跃天数失败，userId: {userId}, year: {year}, error: {error}', [
                'userId' => $userId,
                'year'   => $year,
                'error'  => $e->getMessage()
            ]);
            throw $e;
        }
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
        try {
            $datetime = new \DateTime("{$yearMonth}-01");
            $year     = $datetime->format('Y');
            $month    = (int) $datetime->format('m');

            $yearKey = "{$key}{$userId}:{$year}";

            // 获取这个月第一天是一年中的第几天
            $firstDay       = new \DateTime("{$year}-{$month}-01");
            $firstDayOffset = (int) $firstDay->format('z');

            // 获取这个月最后一天是一年中的第几天
            $lastDay = clone $firstDay;
            $lastDay->modify('last day of this month');
            $lastDayOffset = (int) $lastDay->format('z');

            // 计算这个月的天数
            $result = $this->bitCount($yearKey, $firstDayOffset, $lastDayOffset);

            Log::info('获取用户月度活跃天数，userId: {userId}, yearMonth: {yearMonth}, yearKey: {yearKey}, firstDayOffset: {firstDayOffset}, lastDayOffset: {lastDayOffset}, activeDays: {activeDays}', [
                'userId'         => $userId,
                'yearMonth'      => $yearMonth,
                'yearKey'        => $yearKey,
                'firstDayOffset' => $firstDayOffset,
                'lastDayOffset'  => $lastDayOffset,
                'activeDays'     => $result
            ]);

            return $result;
        } catch (\Throwable $e) {
            Log::error('获取用户月度活跃天数失败，userId: {userId}, yearMonth: {yearMonth}, error: {error}', [
                'userId'    => $userId,
                'yearMonth' => $yearMonth,
                'error'     => $e->getMessage()
            ]);
            throw $e;
        }
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
        Log::info('获取用户最近活跃状态，userId: {userId}, days: {days}', [
            'userId' => $userId,
            'days'   => $days
        ]);
        
        try {
            $result = [];
            $today = new \DateTime();

            for ($i = $days - 1; $i >= 0; $i--) {
                $date = clone $today;
                $date->modify("-{$i} days");

                $dateStr = $date->format('Y-m-d');
                $isActive = $this->checkDailyActive($key, $userId, $dateStr);

                $result[$dateStr] = $isActive ? 1 : 0;
            }
            
            Log::debug('用户最近活跃状态结果，userId: {userId}, days: {days}, 活跃天数: {activeDays}', [
                'userId' => $userId,
                'days'   => $days,
                'activeDays' => count(array_filter($result, function($v) { return $v == 1; }))
            ]);
            
            return $result;
        } catch (\Throwable $e) {
            Log::error('获取用户最近活跃状态失败，userId: {userId}, days: {days}, error: {error}', [
                'userId' => $userId,
                'days'   => $days,
                'error'  => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 删除一个或多个键
     *
     * @param string|array $keys 键名或键名数组
     * @return int 删除的键数量
     */
    public function delete($keys): int
    {
        try {
            $keysDescription = is_array($keys) ? implode(',', $keys) : $keys;
            $result = $this->redis->del($keys);
            Log::info('删除Redis位图键，keys: {keys}, deletedCount: {deletedCount}', [
                'keys'         => $keysDescription,
                'deletedCount' => $result
            ]);
            return $result;
        } catch (\Throwable $e) {
            $keysDescription = is_array($keys) ? implode(',', $keys) : $keys;
            Log::error('删除Redis位图键失败，keys: {keys}, error: {error}', [
                'keys'  => $keysDescription,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}