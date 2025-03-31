<?php

// app/service/RedPacketService.php
namespace app\service;

use app\model\RedPacket;
use app\model\RedPacketRecord;
use think\facade\Cache;
use think\facade\Db;

class RedPacketService
{
    /**
     * 创建红包
     * @param float $totalAmount 总金额
     * @param int $totalCount 总个数
     * @return int 红包ID
     */
    public function createRedPacket(float $totalAmount, int $totalCount): int
    {
        $redPacket                = new RedPacket();
        $redPacket->total_amount  = $totalAmount;
        $redPacket->remain_amount = $totalAmount;
        $redPacket->total_count   = $totalCount;
        $redPacket->remain_count  = $totalCount;
        $redPacket->save();

        // 将红包信息存入 Redis
        Cache::set("red_packet_{$redPacket->id}_amount", $totalAmount, 86400);
        Cache::set("red_packet_{$redPacket->id}_count", $totalCount, 86400);

        return $redPacket->id;
    }

    /**
     * 抢红包
     * @param int $redPacketId 红包ID
     * @param int $userId 用户ID
     * @return array
     */
    public function grabRedPacket(int $redPacketId, int $userId): array
    {
        // 使用 Redis 分布式锁
        $lockKey = "red_packet_lock_{$redPacketId}";
        $lock    = Cache::store('redis')->lock($lockKey, 10); // 锁10秒

        if (!$lock->get()) {
            return ['code' => 0, 'msg' => '系统繁忙，请稍后再试'];
        }

        try {
            // 检查是否已经抢过
            $hasGrabbed = RedPacketRecord::where('red_packet_id', $redPacketId)
                ->where('user_id', $userId)
                ->find();
            if ($hasGrabbed) {
                return ['code' => 0, 'msg' => '你已经抢过这个红包了'];
            }

            // 检查红包是否有效
            $redPacket = RedPacket::find($redPacketId);
            if (!$redPacket || $redPacket->remain_count <= 0 || $redPacket->remain_amount <= 0) {
                return ['code' => 0, 'msg' => '红包已被抢完'];
            }

            // 从 Redis 获取剩余数量和金额
            $remainCount  = Cache::get("red_packet_{$redPacketId}_count");
            $remainAmount = Cache::get("red_packet_{$redPacketId}_amount");

            if ($remainCount <= 0 || $remainAmount <= 0) {
                return ['code' => 0, 'msg' => '红包已被抢完'];
            }

            // 计算抢到的金额
            $amount = $this->calculateAmount($remainAmount, $remainCount);

            // 开启事务
            Db::startTrans();
            try {
                // 更新红包剩余数量和金额
                $redPacket->remain_count -= 1;
                $redPacket->remain_amount -= $amount;
                $redPacket->save();

                // 记录抢红包记录
                $record                = new RedPacketRecord();
                $record->red_packet_id = $redPacketId;
                $record->user_id       = $userId;
                $record->amount        = $amount;
                $record->save();

                // 更新 Redis
                Cache::set("red_packet_{$redPacketId}_count", $remainCount - 1, 86400);
                Cache::set("red_packet_{$redPacketId}_amount", $remainAmount - $amount, 86400);

                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();
                throw $e;
            }

            return ['code' => 1, 'msg' => '抢红包成功', 'data' => ['amount' => $amount]];
        } catch (\Exception $e) {
            return ['code' => 0, 'msg' => '抢红包失败：' . $e->getMessage()];
        } finally {
            $lock->release();
        }
    }

    /**
     * 计算抢到的金额（随机算法）
     * @param float $remainAmount 剩余金额
     * @param int $remainCount 剩余个数
     * @return float
     */
    private function calculateAmount(float $remainAmount, int $remainCount): float
    {
        if ($remainCount == 1) {
            return round($remainAmount, 2);
        }

        // 随机分配，防止金额过大或过小
        $max    = $remainAmount / $remainCount * 2; // 最大值是平均值的两倍
        $amount = mt_rand(1, $max * 100) / 100; // 保留两位小数
        $amount = min($amount, $remainAmount); // 不能超过剩余金额

        return round($amount, 2);
    }
}