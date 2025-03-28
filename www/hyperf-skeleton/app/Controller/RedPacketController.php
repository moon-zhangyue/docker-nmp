<?php

declare(strict_types=1);

namespace App\Controller;

use App\Model\RedPacket;
use App\Model\RedPacketRecord;
use App\Model\User;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Redis\Redis;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;

class RedPacketController extends AbstractController
{
    /**
     * @var ValidatorFactoryInterface
     */
    #[Inject]
    protected $validationFactory;

    /**
     * @var Redis
     */
    #[Inject]
    protected $redis;

    /**
     * 创建红包
     */
    public function create()
    {
        try {
            // 验证请求参数
            $validated = $this->validate([
                'user_id'  => 'required|integer|exists:users,id',
                'amount'   => 'required|numeric|min:0.01',
                'num'      => 'required|integer|min:1',
                'type'     => 'required|integer|in:1,2',
                'blessing' => 'nullable|string|max:255',
            ]);
        } catch (\Throwable $e) {
            return $this->response->json([
                'code'    => 422,
                'message' => $e->getMessage(),
                'data'    => null,
            ])->withStatus(422);
        }

        $userId   = $validated['user_id'];
        $amount   = $validated['amount'];
        $num      = $validated['num'];
        $type     = $validated['type'];
        $blessing = $validated['blessing'] ?? '恭喜发财，大吉大利！';

        // 检查用户余额
        $user = User::query()->find($userId);
        if (!$user) {
            return $this->response->json([
                'code'    => 404,
                'message' => '用户不存在',
                'data'    => null,
            ]);
        }

        if ($user->getBalance() < $amount) {
            return $this->response->json([
                'code'    => 400,
                'message' => '余额不足',
                'data'    => null,
            ]);
        }

        // 开启事务
        Db::beginTransaction();
        try {
            // 扣除用户余额
            $user->setBalance($user->getBalance() - $amount);
            $user->save();

            // 生成红包编号
            // 使用随机字符串生成红包编号
            $packetNo = date('YmdHis') . substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 8);

            // 创建红包
            $redPacket = new RedPacket([
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
            $redPacket->save();

            // 如果是拼手气红包，预先生成红包金额列表并存入Redis
            if ($type == 2) {
                $amountList = $this->divideRedPacket($amount, $num);
                $this->redis->lPush('red_packet:' . $packetNo, ...$amountList);
                // 设置过期时间
                $this->redis->expire('red_packet:' . $packetNo, 86400); // 24小时
            }

            Db::commit();

            return $this->response->json([
                'code'    => 0,
                'message' => '红包创建成功',
                'data'    => [
                    'packet_no' => $packetNo,
                    'amount'    => $amount,
                    'num'       => $num,
                    'type'      => $type,
                    'blessing'  => $blessing,
                ],
            ]);
        } catch (\Throwable $e) {
            Db::rollBack();
            return $this->response->json([
                'code'    => 500,
                'message' => '红包创建失败：' . $e->getMessage(),
                'data'    => null,
            ]);
        }
    }

    /**
     * 抢红包
     */
    public function grab()
    {
        // 验证请求参数
        $validated = $this->validate([
            'user_id'   => 'required|integer|exists:users,id',
            'packet_no' => 'required|string|exists:red_packets,packet_no',
        ]);

        $userId   = $validated['user_id'];
        $packetNo = $validated['packet_no'];

        // 检查用户是否存在
        $user = User::query()->find($userId);
        if (!$user) {
            return $this->response->json([
                'code'    => 404,
                'message' => '用户不存在',
                'data'    => null,
            ]);
        }

        // 获取红包信息
        $redPacket = RedPacket::query()->where('packet_no', $packetNo)->first();
        if (!$redPacket) {
            return $this->response->json([
                'code'    => 404,
                'message' => '红包不存在',
                'data'    => null,
            ]);
        }

        // 检查红包是否有效
        if ($redPacket->status != 1) {
            return $this->response->json([
                'code'    => 400,
                'message' => '红包已失效',
                'data'    => null,
            ]);
        }

        // 检查红包是否过期
        if ($redPacket->expired_at < date('Y-m-d H:i:s')) {
            return $this->response->json([
                'code'    => 400,
                'message' => '红包已过期',
                'data'    => null,
            ]);
        }

        // 检查红包是否已抢完
        if ($redPacket->remaining_num <= 0) {
            return $this->response->json([
                'code'    => 400,
                'message' => '红包已抢完',
                'data'    => null,
            ]);
        }

        // 检查用户是否已抢过该红包
        $record = RedPacketRecord::query()
            ->where('packet_id', $redPacket->id)
            ->where('user_id', $userId)
            ->first();

        if ($record) {
            return $this->response->json([
                'code'    => 400,
                'message' => '您已抢过该红包',
                'data'    => null,
            ]);
        }

        // 使用Redis分布式锁防止并发抢红包
        $lockKey   = 'lock:red_packet:' . $packetNo;
        $lockValue = uniqid();
        $acquired  = $this->redis->set($lockKey, $lockValue, ['NX', 'EX' => 5]); // 锁定5秒

        if (!$acquired) {
            return $this->response->json([
                'code'    => 429,
                'message' => '操作太频繁，请稍后再试',
                'data'    => null,
            ]);
        }

        try {
            // 开启事务
            Db::beginTransaction();

            // 重新查询红包信息（加锁）
            $redPacket = RedPacket::query()->where('packet_no', $packetNo)->lockForUpdate()->first();

            // 再次检查红包是否有效
            if ($redPacket->status != 1 || $redPacket->remaining_num <= 0 || $redPacket->expired_at < date('Y-m-d H:i:s')) {
                Db::rollBack();
                return $this->response->json([
                    'code'    => 400,
                    'message' => '红包已失效或已抢完',
                    'data'    => [
                        'amount'        => 0,
                        'blessing'      => $redPacket->blessing,
                        'sender'        => $redPacket->user->name,
                        'sender_avatar' => $redPacket->user->avatar,
                    ],
                ]);
            }

            // 确定红包金额
            $amount = 0;

            if ($redPacket->type == 1) {
                // 普通红包，平均分配
                $amount = bcdiv($redPacket->remaining_amount, $redPacket->remaining_num, 2);
            } else {
                // 拼手气红包，从Redis中获取预先生成的金额
                $amount = $this->redis->rPop('red_packet:' . $packetNo);
                if (!$amount) {
                    // 如果Redis中没有数据，则重新生成一个金额
                    if ($redPacket->remaining_num == 1) {
                        // 最后一个红包，直接给剩余金额
                        $amount = $redPacket->remaining_amount;
                    } else {
                        // 随机生成红包金额
                        $amount = $this->getRandomAmount($redPacket->remaining_amount, $redPacket->remaining_num);
                    }
                }
            }

            // 更新红包信息
            $redPacket->remaining_num -= 1;
            $redPacket->remaining_amount = bcsub($redPacket->remaining_amount, $amount, 2);
            if ($redPacket->remaining_num == 0) {
                $redPacket->status = 0; // 红包已抢完，标记为无效
            }
            $redPacket->save();

            // 创建红包记录
            $record = new RedPacketRecord([
                'packet_no' => $packetNo,
                'packet_id' => $redPacket->id,
                'user_id'   => $userId,
                'amount'    => $amount,
                'status'    => 1,
            ]);
            $record->save();

            // 增加用户余额
            $user->setBalance(bcadd($user->getBalance(), $amount, 2));
            $user->save();

            Db::commit();

            return $this->response->json([
                'code'    => 0,
                'message' => '抢红包成功',
                'data'    => [
                    'amount'        => $amount,
                    'blessing'      => $redPacket->blessing,
                    'sender'        => $redPacket->user->name,
                    'sender_avatar' => $redPacket->user->avatar,
                ],
            ]);
        } catch (\Throwable $e) {
            Db::rollBack();
            return $this->response->json([
                'code'    => 500,
                'message' => '抢红包失败：' . $e->getMessage(),
                'data'    => null,
            ]);
        } finally {
            // 释放锁
            if ($this->redis->get($lockKey) == $lockValue) {
                $this->redis->del($lockKey);
            }
        }
    }

    /**
     * 红包详情
     */
    public function detail()
    {
        // 验证请求参数
        $validated = $this->validate([
            'packet_no' => 'required|string|exists:red_packets,packet_no',
        ]);

        $packetNo = $validated['packet_no'];

        // 获取红包信息
        $redPacket = RedPacket::query()->where('packet_no', $packetNo)->first();
        if (!$redPacket) {
            return $this->response->json([
                'code'    => 404,
                'message' => '红包不存在',
                'data'    => null,
            ]);
        }

        // 获取红包记录
        $records = RedPacketRecord::query()
            ->where('packet_id', $redPacket->id)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($record) {
                return [
                    'user_id'     => $record->user_id,
                    'user_name'   => $record->user->name,
                    'user_avatar' => $record->user->avatar,
                    'amount'      => $record->amount,
                    'created_at'  => $record->created_at->format('Y-m-d H:i:s'),
                ];
            });

        return $this->response->json([
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
        ]);
    }

    /**
     * 验证请求数据
     *
     * @param array $rules 验证规则
     * @param array $messages 错误信息
     * @return array 验证通过的数据
     */
    protected function validate(array $rules, array $messages = []): array
    {
        $validator = $this->validationFactory->make(
            $this->request->all(),
            $rules,
            $messages
        );

        if ($validator->fails()) {
            throw new \InvalidArgumentException($validator->errors()->first());
        }

        return $validator->validated();
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
                $amount = $this->getRandomAmount($remainAmount, $remainNum);
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
        $maxAmount = min($maxAmount, bcsub((string) $remainAmount, bcmul((string) ($remainNum - 1), (string) $minAmount, 2), 2));

        // 生成随机金额，精确到分
        $amount = mt_rand((int) $minAmount * 100, $maxAmount * 100) / 100;
        return round($amount, 2);
    }
}