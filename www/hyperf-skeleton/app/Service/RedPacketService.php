<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\RedPacket;
use App\Model\RedPacketRecord;
use App\Model\User;
use App\Log;
use App\Repository\RedPacketRepository;
use App\Repository\UserRepository;
use Hyperf\DbConnection\Db;
use Hyperf\Redis\Redis;

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

    public function __construct(Redis $redis, RedPacketRepository $redPacketRepository, UserRepository $userRepository)
    {
        $this->redis               = $redis;
        $this->redPacketRepository = $redPacketRepository;
        $this->userRepository      = $userRepository;
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
        Log::info('红包创建请求参数：', [
            'user_id'  => $userId,
            'amount'   => $amount,
            'num'      => $num,
            'type'     => $type,
            'blessing' => $blessing,
        ]);

        // 检查用户余额
        $user = $this->userRepository->findById($userId);
        if (!$user) {
            return [
                'code'    => 404,
                'message' => '用户不存在',
                'data'    => null,
            ];
        }

        if ($user->getBalance() < $amount) {
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

            Db::commit();

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
        Log::info('1. 抢红包开始');
        Log::info('2.红包抢请求参数：', [
            'user_id'   => $userId,
            'packet_no' => $packetNo,
        ]);

        // 检查用户是否存在
        $user = $this->userRepository->findById($userId);
        if (!$user) {
            return [
                'code'    => 404,
                'message' => '用户不存在',
                'data'    => null,
            ];
        }

        // 获取红包信息
        $redPacket = $this->redPacketRepository->findByPacketNo($packetNo);
        if (!$redPacket) {
            return [
                'code'    => 404,
                'message' => '红包不存在',
                'data'    => null,
            ];
        }
        Log::info('3.红包信息：', [$redPacket]);

        // 检查红包是否有效
        if ($redPacket->status != 1 && $redPacket->remaining_num > 0) {
            return [
                'code'    => 400,
                'message' => '红包已失效',
                'data'    => null,
            ];
        }

        // 检查红包是否过期
        if ($redPacket->expired_at < date('Y-m-d H:i:s')) {
            return [
                'code'    => 400,
                'message' => '红包已过期',
                'data'    => null,
            ];
        }

        // 检查红包是否已抢完
        if ($redPacket->remaining_num <= 0) {
            return [
                'code'    => 400,
                'message' => '红包已抢完',
                'data'    => null,
            ];
        }

        // 检查用户是否已抢过该红包
        if ($this->redPacketRepository->hasUserGrabbed($redPacket->id, $userId)) {
            Log::info('4.该用户已经抢过：');
            return [
                'code'    => 400,
                'message' => '您已抢过该红包',
                'data'    => null,
            ];
        }

        // 使用Redis分布式锁防止并发抢红包
        $lockKey   = 'lock:red_packet:' . $packetNo; // 锁的键名
        $lockValue = uniqid();
        $acquired  = $this->redis->set($lockKey, $lockValue, ['EX' => 5, 'NX' => true]); // 锁定5秒
        Log::info('5.红包锁：', [$acquired]);
        if (!$acquired) {
            return [
                'code'    => 429,
                'message' => '操作太频繁，请稍后再试',
                'data'    => null,
            ];
        }

        try {
            // 开启事务
            Db::beginTransaction();

            // 重新查询红包信息（加锁）
            $redPacket = $this->redPacketRepository->findByPacketNoForUpdate($packetNo);

            // 再次检查红包是否有效
            if ($redPacket->status != 1 || $redPacket->remaining_num <= 0 || $redPacket->expired_at < date('Y-m-d H:i:s')) {
                Db::rollBack();
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

            Db::commit();

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
            return [
                'code'    => 500,
                'message' => '抢红包失败：' . $e->getMessage(),
                'data'    => null,
            ];
        } finally {
            // 释放锁
            if ($this->redis->get($lockKey) == $lockValue) {
                $this->redis->del($lockKey);
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
        // 获取红包信息
        $redPacket = $this->redPacketRepository->findByPacketNo($packetNo);
        if (!$redPacket) {
            return [
                'code'    => 404,
                'message' => '红包不存在',
                'data'    => null,
            ];
        }

        // 获取红包记录
        $records = $this->redPacketRepository->getRecordsWithUser($redPacket->id);

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
        Log::info('开始处理过期红包');

        $count       = 0;
        $totalAmount = 0;

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

                Log::info('红包过期退回', [
                    'packet_no' => $packet->packet_no,
                    'user_id'   => $packet->user_id,
                    'amount'    => $packet->remaining_amount
                ]);

                $count++;
            });
        }

        Log::info('处理过期红包完成', [
            'count'  => $count,
            'amount' => $totalAmount
        ]);

        return [
            'count'  => $count,
            'amount' => $totalAmount
        ];
    }
}