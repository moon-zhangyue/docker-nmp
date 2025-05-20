<?php
declare(strict_types=1);

namespace app\controller\redis;

use app\controller\RedisDemo;
use app\facade\Redis;
use think\facade\Log;
use think\facade\View;

/**
 * Redis BitMap类型演示控制器
 *
 * 演示Redis BitMap类型的常见应用场景
 * 
 * @OA\Tag(
 *     name="BitMap",
 *     description="Redis BitMap相关操作"
 * )
 */
class BitMapDemo extends RedisDemo
{
    /**
     * 演示页面
     * 
     * @OA\Get(
     *     path="/redis/bitmap",
     *     tags={"BitMap"},
     *     summary="BitMap演示页面",
     *     @OA\Response(
     *         response=200,
     *         description="成功返回BitMap演示页面"
     *     )
     * )
     */
    public function index()
    {
        Log::info('访问BitMap演示页面');
        return View::fetch('redis/bitmap/index');
    }

    /**
     * 基本用法示例
     * 
     * @OA\Get(
     *     path="/redis/bitmap/basic",
     *     tags={"BitMap"},
     *     summary="BitMap基本用法示例",
     *     @OA\Response(
     *         response=200,
     *         description="成功返回BitMap基本用法结果"
     *     )
     * )
     */
    public function basic()
    {
        try {
            Log::info('执行BitMap基本用法示例');
            $redis = Redis::bitmap();
            $key   = 'bitmap_demo_basic';

            // 清空之前的测试数据
            $redis->delete($key);
            Log::debug('清空测试数据，key: {key}', ['key' => $key]);

            // 设置bit位
            $redis->setBit($key, 0, 1); // 设置第0位为1
            $redis->setBit($key, 3, 1); // 设置第3位为1
            $redis->setBit($key, 5, 1); // 设置第5位为1
            Log::debug('设置bit位，positions: {positions}', ['positions' => [0, 3, 5]]);

            // 获取bit位
            $bit0 = $redis->getBit($key, 0);
            $bit1 = $redis->getBit($key, 1);
            $bit3 = $redis->getBit($key, 3);
            Log::debug('获取bit位，bit0: {bit0}, bit1: {bit1}, bit3: {bit3}', ['bit0' => $bit0, 'bit1' => $bit1, 'bit3' => $bit3]);

            // 计数为1的位数
            $count = $redis->bitCount($key);
            Log::debug('计数bit=1的位数，count: {count}', ['count' => $count]);

            // 查找第一个为1或0的位置
            $firstBit1 = $redis->bitPos($key, 1);
            $firstBit0 = $redis->bitPos($key, 0);
            Log::debug('查找首个bit位置，firstBit1: {firstBit1}, firstBit0: {firstBit0}', ['firstBit1' => $firstBit1, 'firstBit0' => $firstBit0]);

            // 测试位运算
            $key1    = 'bitmap_demo_op1';
            $key2    = 'bitmap_demo_op2';
            $destKey = 'bitmap_demo_op_result';
            Log::debug('准备位运算测试，key1: {key1}, key2: {key2}, destKey: {destKey}', ['key1' => $key1, 'key2' => $key2, 'destKey' => $destKey]);

            // 设置两个位图数据
            $redis->setBit($key1, 0, 1);
            $redis->setBit($key1, 1, 1);
            $redis->setBit($key1, 4, 1);

            $redis->setBit($key2, 1, 1);
            $redis->setBit($key2, 2, 1);
            $redis->setBit($key2, 4, 1);

            // 位运算：AND, OR, XOR, NOT
            $andResult = $redis->bitOp('AND', "{$destKey}_and", [$key1, $key2]);
            $orResult  = $redis->bitOp('OR', "{$destKey}_or", [$key1, $key2]);
            $xorResult = $redis->bitOp('XOR', "{$destKey}_xor", [$key1, $key2]);
            $notResult = $redis->bitOp('NOT', "{$destKey}_not", $key1);
            Log::debug('执行位运算，andResult: {andResult}, orResult: {orResult}, xorResult: {xorResult}, notResult: {notResult}', [
                'andResult' => $andResult,
                'orResult' => $orResult,
                'xorResult' => $xorResult,
                'notResult' => $notResult
            ]);

            // 查看位运算的结果
            $andBitCount = $redis->bitCount("{$destKey}_and");
            $orBitCount  = $redis->bitCount("{$destKey}_or");
            $xorBitCount = $redis->bitCount("{$destKey}_xor");
            $notBitCount = $redis->bitCount("{$destKey}_not");
            Log::debug('位运算结果计数，andBitCount: {andBitCount}, orBitCount: {orBitCount}, xorBitCount: {xorBitCount}, notBitCount: {notBitCount}', [
                'andBitCount' => $andBitCount,
                'orBitCount' => $orBitCount,
                'xorBitCount' => $xorBitCount,
                'notBitCount' => $notBitCount
            ]);

            $result = [
                'bit_values'     => [
                    'bit0' => $bit0,
                    'bit1' => $bit1,
                    'bit3' => $bit3,
                ],
                'bit_count'      => $count,
                'first_bit_1'    => $firstBit1,
                'first_bit_0'    => $firstBit0,
                'bit_operations' => [
                    'and_result' => $andBitCount,
                    'or_result'  => $orBitCount,
                    'xor_result' => $xorBitCount,
                    'not_result' => $notBitCount,
                ],
            ];
            
            Log::info('BitMap基本用法演示成功，result: {result}', ['result' => $result]);
            return $this->success('BitMap基本用法演示成功', $result);
        } catch (\Throwable $e) {
            Log::error('BitMap基本用法演示失败，error: {error}, trace: {trace}', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->error('BitMap基本用法演示失败：' . $e->getMessage());
        }
    }

    /**
     * 用户签到示例
     */
    public function userSign()
    {
        try {
            $redis  = Redis::bitmap();
            $action = $this->request->param('action', 'status');
            $userId = $this->request->param('user_id', 1, 'intval');
            $date   = $this->request->param('date', date('Y-m-d'));

            $keyPrefix = 'user:sign:';
            
            Log::info('执行用户签到操作，action: {action}, user_id: {user_id}, date: {date}', [
                'action' => $action,
                'user_id' => $userId,
                'date' => $date
            ]);

            switch ($action) {
                case 'sign':
                    // 用户签到
                    Log::debug('用户执行签到，userId: {userId}, date: {date}', ['userId' => $userId, 'date' => $date]);
                    $signed = $redis->setDailyActive($keyPrefix, $userId, $date, 1);

                    $result = [
                        'status'             => 'success',
                        'user_id'            => $userId,
                        'date'               => $date,
                        'signed'             => true,
                        'was_already_signed' => $signed == 1,
                        'message'            => $signed == 0 ? '签到成功' : '今日已签到',
                    ];
                    Log::info($signed == 0 ? '用户签到成功，userId: {userId}, date: {date}' : '用户今日已签到，userId: {userId}, date: {date}', ['userId' => $userId, 'date' => $date]);
                    break;

                case 'check':
                    // 检查用户是否签到
                    Log::debug('检查用户签到状态，userId: {userId}, date: {date}', ['userId' => $userId, 'date' => $date]);
                    $isSigned = $redis->checkDailyActive($keyPrefix, $userId, $date);

                    $result = [
                        'status'    => 'success',
                        'user_id'   => $userId,
                        'date'      => $date,
                        'is_signed' => $isSigned,
                        'message'   => $isSigned ? '已签到' : '未签到',
                    ];
                    Log::info('用户签到状态检查结果，userId: {userId}, date: {date}, is_signed: {is_signed}', ['userId' => $userId, 'date' => $date, 'is_signed' => $isSigned]);
                    break;

                case 'month_stats':
                    // 获取用户月度签到统计
                    $yearMonth = $this->request->param('year_month', date('Y-m'));
                    Log::debug('获取用户月度签到统计，userId: {userId}, yearMonth: {yearMonth}', ['userId' => $userId, 'yearMonth' => $yearMonth]);
                    
                    $activeDays = $redis->getMonthlyActiveDays($keyPrefix, $userId, $yearMonth);

                    // 获取月份的天数
                    $year = substr($yearMonth, 0, 4);
                    $month = substr($yearMonth, 5, 2);
                    $daysInMonth = date('t', strtotime("{$year}-{$month}-01"));

                    // 获取详细的签到数据
                    $days = [];
                    $firstDay = "{$yearMonth}-01";
                    $datetime = new \DateTime($firstDay);

                    for ($i = 0; $i < $daysInMonth; $i++) {
                        $currentDate        = $datetime->format('Y-m-d');
                        $days[$currentDate] = $redis->checkDailyActive($keyPrefix, $userId, $currentDate);
                        $datetime->modify('+1 day');
                    }

                    $result = [
                        'status'      => 'success',
                        'user_id'     => $userId,
                        'year_month'  => $yearMonth,
                        'active_days' => $activeDays,
                        'total_days'  => $daysInMonth,
                        'sign_rate'   => round($activeDays / $daysInMonth * 100, 2) . '%',
                        'days'        => $days,
                    ];
                    Log::info('用户月度签到统计完成，userId: {userId}, yearMonth: {yearMonth}, activeDays: {activeDays}, signRate: {signRate}', [
                        'userId' => $userId, 
                        'yearMonth' => $yearMonth, 
                        'activeDays' => $activeDays,
                        'signRate' => $result['sign_rate']
                    ]);
                    break;

                case 'year_stats':
                    // 获取用户年度签到统计
                    $year = $this->request->param('year', date('Y'));
                    Log::debug('获取用户年度签到统计，userId: {userId}, year: {year}', ['userId' => $userId, 'year' => $year]);
                    
                    $activeDays = $redis->getYearlyActiveDays($keyPrefix, $userId, $year);

                    // 获取每月的签到数据
                    $months = [];
                    for ($i = 1; $i <= 12; $i++) {
                        $month       = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
                        $yearMonth   = "{$year}-{$month}";
                        $monthlyDays = $redis->getMonthlyActiveDays($keyPrefix, $userId, $yearMonth);
                        $daysInMonth = date('t', strtotime("{$year}-{$month}-01"));

                        $months[$month] = [
                            'active_days' => $monthlyDays,
                            'total_days'  => $daysInMonth,
                            'sign_rate'   => $daysInMonth > 0 ? round($monthlyDays / $daysInMonth * 100, 2) . '%' : '0%',
                        ];
                    }

                    $result = [
                        'status'      => 'success',
                        'user_id'     => $userId,
                        'year'        => $year,
                        'active_days' => $activeDays,
                        'months'      => $months,
                    ];
                    Log::info('用户年度签到统计完成，userId: {userId}, year: {year}, activeDays: {activeDays}', [
                        'userId' => $userId, 
                        'year' => $year, 
                        'activeDays' => $activeDays
                    ]);
                    break;

                case 'status':
                default:
                    // 获取用户最近签到状态
                    $days = $this->request->param('days', 7, 'intval');
                    Log::debug('获取用户最近签到状态，userId: {userId}, days: {days}', ['userId' => $userId, 'days' => $days]);
                    
                    $recentDays = $redis->getRecentActiveDays($keyPrefix, $userId, $days);

                    // 计算连续签到天数
                    $consecutiveDays = 0;
                    $today = date('Y-m-d');
                    $yesterday = date('Y-m-d', strtotime('-1 day'));

                    // 检查今日和昨日是否签到
                    $isTodaySigned = $redis->checkDailyActive($keyPrefix, $userId, $today);
                    $isYesterdaySigned = $redis->checkDailyActive($keyPrefix, $userId, $yesterday);

                    // 如果今日已签到，开始计算连续天数
                    if ($isTodaySigned) {
                        $consecutiveDays = 1;
                        $checkDate       = $yesterday;

                        while ($redis->checkDailyActive($keyPrefix, $userId, $checkDate)) {
                            $consecutiveDays++;
                            $checkDate = date('Y-m-d', strtotime($checkDate . ' -1 day'));
                        }
                    }

                    $result = [
                        'status'           => 'success',
                        'user_id'          => $userId,
                        'recent_days'      => $recentDays,
                        'today_signed'     => $isTodaySigned,
                        'yesterday_signed' => $isYesterdaySigned,
                        'consecutive_days' => $consecutiveDays,
                    ];
                    Log::info('获取用户最近签到状态完成，userId: {userId}, days: {days}, today_signed: {today_signed}, consecutive_days: {consecutive_days}', [
                        'userId' => $userId, 
                        'days' => $days, 
                        'today_signed' => $isTodaySigned,
                        'consecutive_days' => $consecutiveDays
                    ]);
                    break;
            }

            Log::info('用户签到操作成功，action: {action}, userId: {userId}', ['action' => $action, 'userId' => $userId]);
            return $this->success('用户签到操作成功', $result);
        } catch (\Throwable $e) {
            Log::error('用户签到操作失败，action: {action}, userId: {userId}, error: {error}, trace: {trace}', [
                'action' => $this->request->param('action', 'status'),
                'userId' => $this->request->param('user_id', 1, 'intval'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('用户签到操作失败：' . $e->getMessage());
        }
    }

    /**
     * 在线状态示例
     */
    public function onlineStatus()
    {
        try {
            $redis  = Redis::bitmap();
            $action = $this->request->param('action', 'status');
            
            Log::info('执行在线状态操作，action: {action}', ['action' => $action]);

            $key = 'online:users';

            switch ($action) {
                case 'login':
                    // 用户登录
                    $userId = $this->request->param('user_id', 0, 'intval');
                    Log::debug('执行用户登录操作，userId: {userId}', ['userId' => $userId]);

                    if ($userId > 0) {
                        $redis->setBit($key, $userId, 1);
                        $result = [
                            'status'    => 'success',
                            'user_id'   => $userId,
                            'message'   => '用户已登录',
                            'is_online' => true,
                        ];
                        Log::info('用户登录成功，userId: {userId}', ['userId' => $userId]);
                    } else {
                        $result = [
                            'status'  => 'error',
                            'message' => '用户ID不能为空',
                        ];
                        Log::warning('用户登录失败，userId: {userId}, reason: {reason}', ['userId' => $userId, 'reason' => '用户ID不能为空']);
                    }
                    break;

                case 'logout':
                    // 用户登出
                    $userId = $this->request->param('user_id', 0, 'intval');
                    Log::debug('执行用户登出操作，userId: {userId}', ['userId' => $userId]);

                    if ($userId > 0) {
                        $redis->setBit($key, $userId, value: 0);
                        $result = [
                            'status'    => 'success',
                            'user_id'   => $userId,
                            'message'   => '用户已登出',
                            'is_online' => false,
                        ];
                        Log::info('用户登出成功，userId: {userId}', ['userId' => $userId]);
                    } else {
                        $result = [
                            'status'  => 'error',
                            'message' => '用户ID不能为空',
                        ];
                        Log::warning('用户登出失败，userId: {userId}, reason: {reason}', ['userId' => $userId, 'reason' => '用户ID不能为空']);
                    }
                    break;

                case 'check':
                    // 检查用户是否在线
                    $userId = $this->request->param('user_id', 0, 'intval');
                    Log::debug('检查用户在线状态，userId: {userId}', ['userId' => $userId]);

                    if ($userId > 0) {
                        $isOnline = $redis->getBit($key, $userId);
                        $result   = [
                            'status'    => 'success',
                            'user_id'   => $userId,
                            'is_online' => $isOnline == 1,
                            'message'   => $isOnline == 1 ? '用户在线' : '用户离线',
                        ];
                        Log::info('用户在线状态检查，userId: {userId}, isOnline: {isOnline}', ['userId' => $userId, 'isOnline' => ($isOnline == 1)]);
                    } else {
                        $result = [
                            'status'  => 'error',
                            'message' => '用户ID不能为空',
                        ];
                        Log::warning('用户在线状态检查失败，userId: {userId}, reason: {reason}', ['userId' => $userId, 'reason' => '用户ID不能为空']);
                    }
                    break;

                case 'batch_check':
                    // 批量检查用户是否在线
                    $userIds = $this->request->param('user_ids', '');
                    Log::debug('批量检查用户在线状态，userIds: {userIds}', ['userIds' => $userIds]);

                    if (!empty($userIds)) {
                        $userIds      = explode(',', $userIds);
                        $onlineStatus = [];

                        foreach ($userIds as $userId) {
                            $userId = intval($userId);
                            if ($userId > 0) {
                                $isOnline              = $redis->getBit($key, $userId);
                                $onlineStatus[$userId] = $isOnline == 1;
                            }
                        }

                        $result = [
                            'status'        => 'success',
                            'user_ids'      => $userIds,
                            'online_status' => $onlineStatus,
                        ];
                        Log::info('批量检查用户在线状态完成，userCount: {userCount}, onlineCount: {onlineCount}', [
                            'userCount' => count($userIds),
                            'onlineCount' => count(array_filter($onlineStatus))
                        ]);
                    } else {
                        $result = [
                            'status'  => 'error',
                            'message' => '用户ID列表不能为空',
                        ];
                        Log::warning('批量检查用户在线状态失败，reason: {reason}', ['reason' => '用户ID列表不能为空']);
                    }
                    break;

                case 'count':
                    // 统计在线用户数
                    Log::debug('统计在线用户数量');
                    $onlineCount = $redis->bitCount($key);
                    $result = [
                        'status'       => 'success',
                        'online_count' => $onlineCount,
                    ];
                    Log::info('在线用户数量统计完成，onlineCount: {onlineCount}', ['onlineCount' => $onlineCount]);
                    break;

                case 'status':
                default:
                    // 生成一些模拟在线用户
                    Log::debug('生成模拟在线用户数据');
                    for ($i = 1; $i <= 10; $i++) {
                        // 随机设置在线状态
                        $isOnline = mt_rand(0, 1);
                        $redis->setBit($key, $i, $isOnline);
                    }

                    // 查询所有模拟用户的在线状态
                    $onlineStatus = [];
                    for ($i = 1; $i <= 10; $i++) {
                        $isOnline         = $redis->getBit($key, $i);
                        $onlineStatus[$i] = $isOnline == 1;
                    }

                    // 统计在线用户数
                    $onlineCount = $redis->bitCount($key);

                    $result = [
                        'status'        => 'success',
                        'online_status' => $onlineStatus,
                        'online_count'  => $onlineCount,
                        'total_users'   => 10,
                        'online_rate'   => round($onlineCount / 10 * 100, 2) . '%',
                    ];
                    Log::info('在线状态模拟数据生成完成，onlineCount: {onlineCount}, totalUsers: {totalUsers}, onlineRate: {onlineRate}', [
                        'onlineCount' => $onlineCount,
                        'totalUsers' => 10,
                        'onlineRate' => $result['online_rate']
                    ]);
                    break;
            }

            Log::info('在线状态操作成功，action: {action}', ['action' => $action]);
            return $this->success('在线状态操作成功', $result);
        } catch (\Throwable $e) {
            Log::error('在线状态操作失败，action: {action}, error: {error}, trace: {trace}', [
                'action' => $this->request->param('action', 'status'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('在线状态操作失败：' . $e->getMessage());
        }
    }

    /**
     * 布隆过滤器示例
     * 注：这是布隆过滤器的简化实现，生产环境推荐使用Redis模块RedisBloom
     */
    public function bloomFilter()
    {
        try {
            $redis  = Redis::bitmap();
            $action = $this->request->param('action', 'test');
            
            Log::info('执行布隆过滤器操作，action: {action}', ['action' => $action]);

            $key       = 'bloom:filter:demo';
            $hashCount = 3; // 哈希函数数量
            $size      = 100000; // 位图大小
            
            Log::debug('初始化布隆过滤器，key: {key}, hashCount: {hashCount}, size: {size}', [
                'key' => $key,
                'hashCount' => $hashCount,
                'size' => $size
            ]);

            // 定义哈希函数
            $hashFunctions = [
                function ($value) use ($size) {
                    return crc32($value . '1') % $size;
                },
                function ($value) use ($size) {
                    return crc32($value . '2') % $size;
                },
                function ($value) use ($size) {
                    return crc32($value . '3') % $size;
                },
            ];

            // 将元素添加到布隆过滤器
            $add = function ($value) use ($redis, $key, $hashFunctions) {
                foreach ($hashFunctions as $i => $hashFunc) {
                    $position = $hashFunc($value);
                    $redis->setBit($key, $position, 1);
                }
                Log::debug('添加元素到布隆过滤器，value: {value}', ['value' => $value]);
            };

            // 检查元素是否存在于布隆过滤器
            $exists = function ($value) use ($redis, $key, $hashFunctions) {
                foreach ($hashFunctions as $i => $hashFunc) {
                    $position = $hashFunc($value);
                    if ($redis->getBit($key, $position) == 0) {
                        Log::debug('元素不存在于布隆过滤器，value: {value}, position: {position}, hashIndex: {hashIndex}', [
                            'value' => $value, 
                            'position' => $position, 
                            'hashIndex' => $i
                        ]);
                        return false; // 如果有任何一位是0，则元素一定不存在
                    }
                }
                Log::debug('元素可能存在于布隆过滤器，value: {value}', ['value' => $value]);
                return true; // 如果所有位都是1，则元素可能存在
            };

            switch ($action) {
                case 'add':
                    // 添加元素到布隆过滤器
                    $value = $this->request->param('value', '');
                    Log::debug('尝试添加元素到布隆过滤器，value: {value}', ['value' => $value]);

                    if (!empty($value)) {
                        $add($value);
                        $result = [
                            'status'  => 'success',
                            'value'   => $value,
                            'message' => '已添加到布隆过滤器',
                        ];
                        Log::info('添加元素到布隆过滤器成功，value: {value}', ['value' => $value]);
                    } else {
                        $result = [
                            'status'  => 'error',
                            'message' => '值不能为空',
                        ];
                        Log::warning('添加元素到布隆过滤器失败，reason: {reason}', ['reason' => '值不能为空']);
                    }
                    break;

                case 'check':
                    // 检查元素是否存在
                    $value = $this->request->param('value', '');
                    Log::debug('检查元素是否存在于布隆过滤器，value: {value}', ['value' => $value]);

                    if (!empty($value)) {
                        $mayExist = $exists($value);
                        $result   = [
                            'status'    => 'success',
                            'value'     => $value,
                            'may_exist' => $mayExist,
                            'message'   => $mayExist ? '元素可能存在于集合中' : '元素一定不存在于集合中',
                        ];
                        Log::info('检查布隆过滤器元素完成，value: {value}, mayExist: {mayExist}', ['value' => $value, 'mayExist' => $mayExist]);
                    } else {
                        $result = [
                            'status'  => 'error',
                            'message' => '值不能为空',
                        ];
                        Log::warning('检查布隆过滤器元素失败，reason: {reason}', ['reason' => '值不能为空']);
                    }
                    break;

                case 'test':
                default:
                    // 测试布隆过滤器
                    Log::debug('开始布隆过滤器测试');
                    $redis->delete($key);
                    Log::debug('重置布隆过滤器，key: {key}', ['key' => $key]);

                    // 添加一组元素
                    $testValues = [
                        'apple',
                        'banana',
                        'cherry',
                        'date',
                        'elderberry',
                        'user:1001',
                        'user:1002',
                        'user:1003'
                    ];
                    
                    Log::debug('准备添加测试元素，元素数量: {count}', ['count' => count($testValues)]);

                    foreach ($testValues as $value) {
                        $add($value);
                    }
                    Log::info('测试元素添加完成，count: {count}', ['count' => count($testValues)]);

                    // 测试已知元素
                    $knownTests = [];
                    foreach ($testValues as $value) {
                        $knownTests[$value] = $exists($value);
                    }
                    Log::debug('已知元素测试完成，测试元素数量: {count}', ['count' => count($knownTests)]);

                    // 测试未知元素
                    $unknownTests = [
                        'fig'       => $exists('fig'),
                        'grape'     => $exists('grape'),
                        'user:1004' => $exists('user:1004'),
                        'user:1005' => $exists('user:1005'),
                    ];
                    Log::debug('未知元素测试完成，测试元素数量: {count}', ['count' => count($unknownTests)]);

                    // 测试误判率
                    $totalUnknown = 1000;
                    $falsePositives = 0;
                    
                    Log::debug('开始误判率测试，totalUnknown: {totalUnknown}', ['totalUnknown' => $totalUnknown]);

                    for ($i = 0; $i < $totalUnknown; $i++) {
                        $randomValue = 'random:' . mt_rand(10000, 99999);
                        if ($exists($randomValue)) {
                            $falsePositives++;
                        }
                    }

                    $falsePositiveRate = $totalUnknown > 0 ? $falsePositives / $totalUnknown : 0;
                    $rateStr = round($falsePositiveRate * 100, 2) . '%';
                    
                    Log::info('误判率测试完成，totalUnknown: {totalUnknown}, falsePositives: {falsePositives}, falsePositiveRate: {falsePositiveRate}', [
                        'totalUnknown' => $totalUnknown,
                        'falsePositives' => $falsePositives,
                        'falsePositiveRate' => $rateStr
                    ]);

                    $result = [
                        'status'               => 'success',
                        'added_values'         => $testValues,
                        'known_tests'          => $knownTests,
                        'unknown_tests'        => $unknownTests,
                        'false_positive_tests' => [
                            'total'           => $totalUnknown,
                            'false_positives' => $falsePositives,
                            'rate'            => $rateStr,
                        ],
                        'explanation'          => [
                            '布隆过滤器特性' => '布隆过滤器可能会误判元素存在，但不会误判元素不存在',
                            '应用场景'    => '用于快速检查元素是否不存在，减少对数据库或缓存的无效查询',
                        ],
                    ];
                    break;
            }

            Log::info('布隆过滤器操作成功，action: {action}', ['action' => $action]);
            return $this->success('布隆过滤器操作成功', $result);
        } catch (\Throwable $e) {
            Log::error('布隆过滤器操作失败，action: {action}, error: {error}', [
                'action' => $this->request->param('action', 'test'),
                'error' => $e->getMessage()
            ]);
            // 添加更详细的调试信息
            Log::debug('布隆过滤器操作失败详细信息: {trace}', ['trace' => $e->getTraceAsString()]);
            return $this->error('布隆过滤器操作失败：' . $e->getMessage());
        }
    }
}