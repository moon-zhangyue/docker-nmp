<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\RedPacket;
use App\Model\RedPacketRecord;
use Hyperf\Database\Model\Builder;

class RedPacketRepository
{
    /**
     * 根据红包编号查找红包
     *
     * @param string $packetNo 红包编号
     * @return RedPacket|null 红包对象
     */
    public function findByPacketNo(string $packetNo): ?RedPacket
    {
        return RedPacket::query()->where('packet_no', $packetNo)->first();
    }

    /**
     * 根据红包编号查找红包（加锁）
     *
     * @param string $packetNo 红包编号
     * @return RedPacket|null 红包对象
     */
    public function findByPacketNoForUpdate(string $packetNo): ?RedPacket
    {
        return RedPacket::query()->where('packet_no', $packetNo)->lockForUpdate()->first();
    }

    /**
     * 创建红包
     *
     * @param array $data 红包数据
     * @return RedPacket 红包对象
     */
    public function create(array $data): RedPacket
    {
        $redPacket = new RedPacket($data);
        $redPacket->save();
        return $redPacket;
    }

    /**
     * 创建红包记录
     *
     * @param array $data 红包记录数据
     * @return RedPacketRecord 红包记录对象
     */
    public function createRecord(array $data): RedPacketRecord
    {
        $record = new RedPacketRecord($data);
        $record->save();
        return $record;
    }

    /**
     * 检查用户是否已抢过该红包
     *
     * @param int $packetId 红包ID
     * @param int $userId 用户ID
     * @return bool 是否已抢过
     */
    public function hasUserGrabbed(int $packetId, int $userId): bool
    {
        return RedPacketRecord::query()
            ->where('packet_id', $packetId)
            ->where('user_id', $userId)
            ->exists();
    }

    /**
     * 抢红包后更新红包信息
     *
     * @param RedPacket $redPacket 红包对象
     * @param float $amount 抢到的金额
     * @return RedPacket 更新后的红包对象
     */
    public function updateAfterGrab(RedPacket $redPacket, float $amount): RedPacket
    {
        $redPacket->remaining_num -= 1;
        $redPacket->remaining_amount = bcsub((string) $redPacket->remaining_amount, (string) $amount, 2);
        if ($redPacket->remaining_num == 0) {
            $redPacket->status = 0; // 红包已抢完，标记为无效
        }
        $redPacket->save();
        return $redPacket;
    }

    /**
     * 获取红包记录并带上用户信息
     *
     * @param int $packetId 红包ID
     * @return array 红包记录列表
     */
    public function getRecordsWithUser(int $packetId): array
    {
        return RedPacketRecord::query()
            ->where('packet_id', $packetId)
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
            })
            ->toArray();
    }
}