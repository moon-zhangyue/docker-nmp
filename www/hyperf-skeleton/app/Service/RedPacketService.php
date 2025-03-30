<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\RedPacket;
use App\Log;
use App\Repository\RedPacketRepository;
use App\Repository\UserRepository;
use Hyperf\DbConnection\Db;
use Hyperf\Redis\Redis;
use Hyperf\Utils\ApplicationContext;

class RedPacketService
{
    /**
     * @var Redis
     */
    protected $redis;

    /**
     * @var RedPacketRepository
     */
    protected $redPacketRepository;

    /**
     * @var \App\Repository\UserRepository
     */
    protected $userRepository;

    /**
     * 请求防重放标识有效期（秒）
     */
    protected const REQUEST_ID_EXPIRE = 300;

    /**
     * 性能监控数据保留数量
     */
    protected const MONITOR_DATA_LIMIT = 1000;

    public function __construct(Redis $redis, RedPacketRepository $redPacketRepository, UserRepository $userRepository)
    {
        $this->redis               = $redis;
        $this->redPacketRepository = $redPacketRepository;
        $this->userRepository      = $userRepository;
    }

    /**
     * 生成请求唯一标识并检查是否重复请求
     *
     * @param string $action 操作类型
     * @param array $params 请求参数
     * @return string|bool 如果是重复请求返回false，否则返回请求ID
     */
    protected function checkRequestReplay(string $action, array $params)
    {
        // 生成请求唯一标识
        $requestId = md5($action . json_encode($params) . microtime(true));
        $key       = "request:{$action}:{$requestId}";

        // 检查是否重复请求
        if ($this->redis->exists($key)) {
            Log::warning('检测到重复请求', [
                'action'     => $action,
                'params'     => $params,
                'request_id' => $requestId
            ]);
            return false;
        }

        // 标记请求已处理，有效期5分钟
        $this->redis->setex($key, self::REQUEST_ID_EXPIRE, 1);
        return $requestId;
    }

    /**
     * 记录实时统计数据
     *
     * @param string $type 统计类型
     * @param float $amount 金额（可选）
     * @return void
     */
    protected function recordStats(string $type, float $amount = 0): void
    {
        $date = date('Ymd');

        // 增加计数
        $this->redis->incr("stats:{$type}:{$date}");

        // 如果有金额，记录金额统计（转为整数，避免浮点数精度问题）
        if ($amount > 0) {
            $this->redis->incrby("stats:{$type}:amount:{$date}", (int) ($amount * 100));
        }
    }

    /**
     * 记录性能监控数据
     *
     * @param string $action 操作类型
     * @param float $startTime 开始时间
     * @param bool $success 是否成功
     * @return void
     */
    protected function recordPerformance(string $action, float $startTime, bool $success = true): void
    {
        $endTime      = microtime(true);
        $responseTime = ($endTime - $startTime) * 1000; // 毫秒

        // 记录响应时间
        $this->redis->lpush("monitor:api:response_time:{$action}", $responseTime);
        $this->redis->ltrim("monitor:api:response_time:{$action}", 0, self::MONITOR_DATA_LIMIT - 1);

        // 记录成功/失败次数
        $status = $success ? 'success' : 'fail';
        $this->redis->incr("monitor:api:{$status}:{$action}:" . date('Ymd'));
    }

    /**
     * 记录异常监控
     *
     * @param string $action 操作类型
     * @param \Throwable $e 异常对象
     * @return void
     */
    protected function recordException(string $action, \Throwable $e): void
    {
        // 记录异常日志
        Log::error("{$action}异常", [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        // 增加异常计数
        $this->redis->incr("monitor:error:{$action}:" . date('Ymd'));

        // 判断是否严重异常并发送告警
        if ($this->isSerious($e)) {
            $this->sendAlert("{$action}严重异常: " . $e->getMessage());
        }
    }

    /**
     * 判断是否严重异常
     *
     * @param \Throwable $e 异常对象
     * @return bool
     */
    protected function isSerious(\Throwable $e): bool
    {
        // 数据库异常、Redis连接异常等系统级异常视为严重异常
        $seriousExceptions = [
            '\PDOException',
            '\Redis\RedisException',
            '\ErrorException',
        ];

        foreach ($seriousExceptions as $exceptionClass) {
            if ($e instanceof $exceptionClass) {
                return true;
            }
        }

        return false;
    }

    /**
     * 发送告警
     *
     * @param string $message 告警消息
     * @return void
     */
    protected function sendAlert(string $message): void
    {
        // 这里可以实现发送告警的逻辑，如发送邮件、短信、钉钉通知等
        Log::alert("系统告警: {$message}");

        // TODO: 实现实际的告警发送逻辑
    }

    /**
     * 创建红包
     *
     * @param int $userId 用户ID
     * @param float $amount 红包金额
     * @param int $num 红包数量
     * @param int $type 红包类型
     * @param string $blessing 祝福语
     * @return array 创建结果
     */
    public function createRedPacket(int $userId, float $amount, int $num, int $type, string $blessing = '恭喜发财，大吉大利！'): array
    {
        // 记录开始时间（用于性能监控）
        $startTime = microtime(true);

        // 防重放攻击检查
        $requestParams = [
            'user_id' => $userId,
            'amount'  => $amount,
            'num'     => $num,
            'type'    => $type
        ];
        $requestId     = $this->checkRequestReplay('create_red_packet', $requestParams);
        if ($requestId === false) {
            return [
                'code'    => 429,
                'message' => '重复请求，请稍后再试',
                'data'    => null,
            ];
        }

        Log::info('红包创建请求参数：', [
            'user_id'    => $userId,
            'amount'     => $amount,
            'num'        => $num,
            'type'       => $type,
            'blessing'   => $blessing,
            'request_id' => $requestId,
        ]);

        // 检查用户余额
        $user = $this->userRepository->findById($userId);
        if (!$user) {
            // 记录性能监控（失败）
            $this->recordPerformance('create_red_packet', $startTime, false);
            return [
                'code'    => 404,
                'message' => '用户不存在',
                'data'    => null,
            ];
        }

        if ($user->getBalance() < $amount) {
            // 记录性能监控（失败）
            $this->recordPerformance('create_red_packet', $startTime, false);
            return [
                'code'    => 400,
                'message' => '余额不足',
                'data'    => null,
            ];
        }

        // 开启事务
        Db::beginTransaction();
        try {
            // 扣除用户余额
            $this->userRepository->decreaseBalance($user, $amount);

            // 生成红包编号
            $packetNo = $this->generatePacketNo();

            // 创建红包
            $redPacket = $this->redPacketRepository->create([
                'packet_no'        => $packetNo,
                'user_id'          => $userId,
                'total_amount'     => $amount,
                'total_num'        => $num,
                'remaining_num'    => $num,
                'remaining_amount' => $amount,
                'status'           => 1,
                'type'             => $type,
                'blessing'         => $blessing,
                'expired_at'       => date('Y-m-d H:i:s', strtotime('+24 hours')),
            ]);

            // 如果是拼手气红包，预先生成红包金额列表并存入Redis
            if ($type == 2) {
                $amountList = $this->divideRedPacket((float) $amount, (int) $num);
                $this->redis->lPush('red_packet:' . $packetNo, ...$amountList);
                // 设置过期时间
                $this->redis->expire('red_packet:' . $packetNo, 86400); // 24小时
            }

            // 设置红包状态标记
            $this->redis->set("red_packet:status:{$packetNo}", 1, ['EX' => 86400]);

            Db::commit();

            // 记录实时统计
            $this->recordStats('red_packet:created', $amount);

            // 记录性能监控（成功）
            $this->recordPerformance('create_red_packet', $startTime, true);

            return [
                'code'    => 0,
                'message' => '红包创建成功',
                'data'    => [
                    'packet_no' => $packetNo,
                    'amount'    => $amount,
                    'num'       => $num,
                    'type'      => $type,
                    'blessing'  => $blessing,
                ],
            ];
        } catch (\Throwable $e) {
            Db::rollBack();

            // 记录异常监控
            $this->recordException('create_red_packet', $e);

            // 记录性能监控（失败）
            $this->recordPerformance('create_red_packet', $startTime, false);

            return [
                'code'    => 500,
                'message' => '红包创建失败：' . $e->getMessage(),
                'data'    => null,
            ];
        }
    }

    /**
     * 抢红包
     *
     * @param int $userId 用户ID
     * @param string $packetNo 红包编号
     * @return array 抢红包结果
     */
    public function grabRedPacket(int $userId, string $packetNo): array
    {
        // 记录开始时间（用于性能监控）
        $startTime = microtime(true);

        // 防重放攻击检查
        $requestParams = [
            'user_id'   => $userId,
            'packet_no' => $packetNo
        ];
        $requestId     = $this->checkRequestReplay('grab_red_packet', $requestParams);
        if ($requestId === false) {
            return [
                'code'    => 429,
                'message' => '重复请求，请稍后再试',
                'data'    => null,
            ];
        }

        Log::info('1.抢红包开始');
        Log::info('2.红包抢请求参数：', [
            'user_id'    => $userId,
            'packet_no'  => $packetNo,
            'request_id' => $requestId,
        ]);

        // 检查用户是否存在
        $user = $this->userRepository->findById($userId);
        if (!$user) {
            // 记录性能监控（失败）
            $this->recordPerformance('grab_red_packet', $startTime, false);
            return [
                'code'    => 404,
                'message' => '用户不存在',
                'data'    => null,
            ];
        }

        // 获取红包信息
        $redPacket = $this->redPacketRepository->findByPacketNo($packetNo);
        if (!$redPacket) {
            // 记录性能监控（失败）
            $this->recordPerformance('grab_red_packet', $startTime, false);
            return [
                'code'    => 404,
                'message' => '红包不存在',
                'data'    => null,
            ];
        }
        Log::info('3.红包信息：', [$redPacket]);

        // 检查红包是否有效
        if ($redPacket->status != 1 && $redPacket->remaining_num > 0) {
            // 记录性能监控（失败）
            $this->recordPerformance('grab_red_packet', $startTime, false);
            return [
                'code'    => 400,
                'message' => '红包已失效',
                'data'    => null,
            ];
        }

        // 检查红包是否过期
        if ($redPacket->expired_at < date('Y-m-d H:i:s')) {
            // 记录性能监控（失败）
            $this->recordPerformance('grab_red_packet', $startTime, false);
            return [
                'code'    => 400,
                'message' => '红包已过期',
                'data'    => null,
            ];
        }

        // 检查红包是否已抢完
        if ($redPacket->remaining_num <= 0) {
            // 记录性能监控（失败）
            $this->recordPerformance('grab_red_packet', $startTime, false);
            return [
                'code'    => 400,
                'message' => '红包已抢完',
                'data'    => null,
            ];
        }

        // 使用Redis检查用户是否已抢过该红包（优先使用Redis缓存检查）
        $hasGrabbed = $this->redis->sismember("red_packet:grabbed_users:{$packetNo}", $userId);
        if ($hasGrabbed) {
            // 记录性能监控（失败）
            $this->recordPerformance('grab_red_packet', $startTime, false);
            Log::info('4.该用户已经抢过（Redis缓存）');
            return [
                'code'    => 400,
                'message' => '您已抢过该红包',
                'data'    => null,
            ];
        }

        // 如果Redis中没有记录，再查询数据库
        if ($this->redPacketRepository->hasUserGrabbed($redPacket->id, $userId)) {
            // 记录性能监控（失败）
            $this->recordPerformance('grab_red_packet', $startTime, false);
            Log::info('4.该用户已经抢过（数据库查询）');
            return [
                'code'    => 400,
                'message' => '您已抢过该红包',
                'data'    => null,
            ];
        }

        // 使用Redis分布式锁防止并发抢红包
        $lockKey   = 'lock:red_packet:' . $packetNo; // 锁的键名
        $lockValue = uniqid((string) mt_rand(), true); // 使用更随机的值作为锁标识
        Log::info('5.尝试获取红包锁', ['lockKey' => $lockKey, 'lockValue' => $lockValue]);
        $acquired = $this->redis->set($lockKey, $lockValue, ['EX' => 5, 'NX' => true]); // 锁定5秒
        Log::info('6.红包锁获取结果：', ['acquired' => $acquired]);
        if (!$acquired) {
            // 记录性能监控（失败）
            $this->recordPerformance('grab_red_packet', $startTime, false);
            Log::info('7.获取锁失败，可能有其他请求正在处理');
            return [
                'code'    => 429,
                'message' => '操作太频繁，请稍后再试',
                'data'    => null,
            ];
        }
        Log::info('7.成功获取锁，开始处理抢红包逻辑');

        try {
            // 开启事务
            Db::beginTransaction();

            // 重新查询红包信息（加锁）
            $redPacket = $this->redPacketRepository->findByPacketNoForUpdate($packetNo);

            // 再次检查红包是否有效
            if ($redPacket->status != 1 || $redPacket->remaining_num <= 0 || $redPacket->expired_at < date('Y-m-d H:i:s')) {
                Db::rollBack();
                // 记录性能监控（失败）
                $this->recordPerformance('grab_red_packet', $startTime, false);
                return [
                    'code'    => 400,
                    'message' => '红包已失效或已抢完',
                    'data'    => [
                        'amount'        => 0,
                        'blessing'      => $redPacket->blessing,
                        'sender'        => $redPacket->user->name,
                        'sender_avatar' => $redPacket->user->avatar,
                    ],
                ];
            }

            // 确定红包金额
            $amount = $this->determineRedPacketAmount($redPacket);

            // 更新红包信息
            $this->redPacketRepository->updateAfterGrab($redPacket, $amount);

            // 创建红包记录
            $this->redPacketRepository->createRecord([
                'packet_no' => $packetNo,
                'packet_id' => $redPacket->id,
                'user_id'   => $userId,
                'amount'    => $amount,
                'status'    => 1,
            ]);

            // 增加用户余额
            $this->userRepository->increaseBalance($user, $amount);

            // 记录用户已抢红包到Redis集合
            $this->redis->sadd("red_packet:grabbed_users:{$packetNo}", $userId);
            $this->redis->expire("red_packet:grabbed_users:{$packetNo}", 86400); // 24小时过期

            // 记录实时统计
            $this->recordStats('red_packet:grabbed', $amount);

            Db::commit();

            // 记录性能监控（成功）
            $this->recordPerformance('grab_red_packet', $startTime, true);

            return [
                'code'    => 0,
                'message' => '抢红包成功',
                'data'    => [
                    'amount'        => $amount,
                    'blessing'      => $redPacket->blessing,
                    'sender'        => $redPacket->user->name,
                    'sender_avatar' => $redPacket->user->avatar,
                ],
            ];
        } catch (\Throwable $e) {
            Db::rollBack();

            // 记录异常监控
            $this->recordException('grab_red_packet', $e);

            // 记录性能监控（失败）
            $this->recordPerformance('grab_red_packet', $startTime, false);

            return [
                'code'    => 500,
                'message' => '抢红包失败：' . $e->getMessage(),
                'data'    => null,
            ];
        } finally {
            // 释放锁
            Log::info('8.准备释放分布式锁', ['lockKey' => $lockKey, 'lockValue' => $lockValue]);
            try {
                // 使用Lua脚本确保原子性操作，只有当锁的值与我们设置的值相同时才删除锁
                $script = <<<LUA
                if redis.call('get', KEYS[1]) == ARGV[1] then
                    return redis.call('del', KEYS[1])
                else
                    return 0
                end
                LUA;
                $result = $this->redis->eval($script, [$lockKey, $lockValue], 1);
                Log::info('9.锁释放结果', ['result' => $result ? '成功' : '失败或锁已不存在']);
            } catch (\Throwable $e) {
                // 即使释放锁出错，也不影响业务结果返回，但需要记录日志
                Log::error('9.释放锁异常', ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * 获取红包详情
     *
     * @param string $packetNo 红包编号
     * @return array 红包详情
     */
    public function getRedPacketDetail(string $packetNo): array
    {
        // 记录开始时间（用于性能监控）
        $startTime = microtime(true);

        // 防重放攻击检查
        $requestParams = [
            'packet_no' => $packetNo
        ];
        $requestId     = $this->checkRequestReplay('get_red_packet_detail', $requestParams);
        if ($requestId === false) {
            return [
                'code'    => 429,
                'message' => '重复请求，请稍后再试',
                'data'    => null,
            ];
        }

        try {
            // 获取红包信息
            $redPacket = $this->redPacketRepository->findByPacketNo($packetNo);
            if (!$redPacket) {
                // 记录性能监控（失败）
                $this->recordPerformance('get_red_packet_detail', $startTime, false);
                return [
                    'code'    => 404,
                    'message' => '红包不存在',
                    'data'    => null,
                ];
            }

            // 获取红包记录
            $records = $this->redPacketRepository->getRecordsWithUser($redPacket->id);

            // 记录性能监控（成功）
            $this->recordPerformance('get_red_packet_detail', $startTime, true);

            return [
                'code'    => 0,
                'message' => '获取成功',
                'data'    => [
                    'packet_no'        => $redPacket->packet_no,
                    'sender'           => [
                        'user_id'     => $redPacket->user_id,
                        'user_name'   => $redPacket->user->name,
                        'user_avatar' => $redPacket->user->avatar,
                    ],
                    'total_amount'     => $redPacket->total_amount,
                    'total_num'        => $redPacket->total_num,
                    'remaining_num'    => $redPacket->remaining_num,
                    'remaining_amount' => $redPacket->remaining_amount,
                    'status'           => $redPacket->status,
                    'type'             => $redPacket->type,
                    'blessing'         => $redPacket->blessing,
                    'expired_at'       => $redPacket->expired_at->format('Y-m-d H:i:s'),
                    'created_at'       => $redPacket->created_at->format('Y-m-d H:i:s'),
                    'records'          => $records,
                ],
            ];
        } catch (\Throwable $e) {
            // 记录异常监控
            $this->recordException('get_red_packet_detail', $e);

            // 记录性能监控（失败）
            $this->recordPerformance('get_red_packet_detail', $startTime, false);

            return [
                'code'    => 500,
                'message' => '获取红包详情失败：' . $e->getMessage(),
                'data'    => null,
            ];
        }
    }

    /**
     * 生成红包编号
     *
     * @return string 红包编号
     */
    protected function generatePacketNo(): string
    {
        return date('YmdHis') . substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 8);
    }

    /**
     * 确定红包金额
     *
     * @param RedPacket $redPacket 红包对象
     * @return float 红包金额
     */
    protected function determineRedPacketAmount(RedPacket $redPacket): float
    {
        $amount = 0;

        if ($redPacket->type == 1) {
            // 普通红包，平均分配
            $amount = bcdiv($redPacket->remaining_amount, (string) $redPacket->remaining_num, 2);
        } else {
            // 拼手气红包，从Redis中获取预先生成的金额
            $amount = $this->redis->rPop('red_packet:' . $redPacket->packet_no);
            if (!$amount) {
                // 如果Redis中没有数据，则重新生成一个金额
                if ($redPacket->remaining_num == 1) {
                    // 最后一个红包，直接给剩余金额
                    $amount = $redPacket->remaining_amount;
                } else {
                    // 随机生成红包金额
                    $amount = $this->getRandomAmount((float) $redPacket->remaining_amount, (int) $redPacket->remaining_num);
                }
            }
        }

        return (float) $amount;
    }

    /**
     * 拼手气红包金额分配算法
     *
     * @param float $totalAmount 总金额
     * @param int $totalNum 总数量
     * @return array 金额列表
     */
    protected function divideRedPacket(float $totalAmount, int $totalNum): array
    {
        $amountList   = [];
        $remainAmount = $totalAmount;
        $remainNum    = $totalNum;

        for ($i = 0; $i < $totalNum; $i++) {
            if ($i == $totalNum - 1) {
                // 最后一个红包，直接给剩余金额
                $amount = $remainAmount;
            } else {
                // 随机生成红包金额
                $amount = $this->getRandomAmount((float) $remainAmount, (int) $remainNum);
            }

            $amountList[] = $amount;
            $remainAmount = bcsub((string) $remainAmount, (string) $amount, 2);
            $remainNum--;
        }

        // 随机打乱顺序
        shuffle($amountList);

        return $amountList;
    }

    /**
     * 获取随机红包金额
     *
     * @param float $remainAmount 剩余金额
     * @param int $remainNum 剩余数量
     * @return float 随机金额
     */
    protected function getRandomAmount(float $remainAmount, int $remainNum): float
    {
        // 每个红包最少0.01元
        $minAmount = 0.01;
        // 每个红包最多为剩余平均值的2倍
        $maxAmount = bcmul(bcdiv((string) $remainAmount, (string) $remainNum, 2), '2', 2);
        // 确保最大值不超过剩余金额减去(剩余数量-1)*最小金额，保证后面的红包至少有最小金额
        $maxAmount = min($maxAmount, bcsub((string) $remainAmount, (string) bcmul((string) ($remainNum - 1), (string) $minAmount, 2), 2));

        Log::info('获取随机红包金额', [
            'minAmount'    => $minAmount,
            'maxAmount'    => $maxAmount,
            'remainAmount' => $remainAmount,
            'remainNum'    => $remainNum
        ]);

        // 生成随机金额，精确到分
        $amount = mt_rand((int) $minAmount * 100, (int) $maxAmount * 100) / 100;

        Log::info('生成随机金额，精确到分', [
            'amount' => $amount
        ]);

        return round($amount, 2);
    }

    /**
     * 处理过期红包
     * 
     * 查找已过期但未处理的红包，将剩余金额退回给发红包用户，并更新红包状态
     * 
     * @return array 处理结果，包含处理数量和退回金额
     */
    public function handleExpiredRedPackets(): array
    {
        // 记录开始时间（用于性能监控）
        $startTime = microtime(true);

        Log::info('开始处理过期红包');

        $count       = 0;
        $totalAmount = 0;

        try {
            // 查找已过期但未处理的红包（状态为有效且有剩余数量）
            $expiredPackets = RedPacket::query()
                ->where('expired_at', '<', date('Y-m-d H:i:s'))
                ->where('status', 1)
                ->where('remaining_num', '>', 0)
                ->get();

            Log::info('找到过期红包数量：' . $expiredPackets->count());

            foreach ($expiredPackets as $packet) {
                // 使用事务确保数据一致性
                Db::transaction(function () use ($packet, &$totalAmount, &$count) {
                    // 查找发红包用户
                    $user = $this->userRepository->findById($packet->user_id);
                    if (!$user) {
                        Log::error('处理过期红包时未找到用户', ['user_id' => $packet->user_id]);
                        return;
                    }

                    // 将剩余金额退回给发红包用户
                    $this->userRepository->increaseBalance($user, (float) $packet->remaining_amount);

                    // 记录退回金额
                    $totalAmount = bcadd((string) $totalAmount, (string) $packet->remaining_amount, 2);

                    // 更新红包状态为已过期
                    $packet->status = 0;
                    $packet->save();

                    // 清理Redis中的相关数据
                    $this->redis->del('red_packet:' . $packet->packet_no);
                    $this->redis->del('red_packet:status:' . $packet->packet_no);
                    $this->redis->del('red_packet:grabbed_users:' . $packet->packet_no);

                    // 记录实时统计（红包退回）
                    $this->recordStats('red_packet:expired', (float) $packet->remaining_amount);

                    Log::info('红包过期退回', [
                        'packet_no' => $packet->packet_no,
                        'user_id'   => $packet->user_id,
                        'amount'    => $packet->remaining_amount
                    ]);

                    $count++;
                });
            }

            // 记录性能监控（成功）
            $this->recordPerformance('handle_expired_red_packets', $startTime, true);

            Log::info('处理过期红包完成', [
                'count'  => $count,
                'amount' => $totalAmount
            ]);

            return [
                'count'  => $count,
                'amount' => $totalAmount
            ];
        } catch (\Throwable $e) {
            // 记录异常监控
            $this->recordException('handle_expired_red_packets', $e);

            // 记录性能监控（失败）
            $this->recordPerformance('handle_expired_red_packets', $startTime, false);

            Log::error('处理过期红包异常', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'count'  => 0,
                'amount' => 0,
                'error'  => $e->getMessage()
            ];
        }
    }
}