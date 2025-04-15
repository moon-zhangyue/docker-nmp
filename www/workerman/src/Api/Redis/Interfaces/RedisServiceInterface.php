<?php

namespace App\Api\Redis\Interfaces;

use Workerman\Connection\TcpConnection;

interface RedisServiceInterface
{
    /**
     * 设置字符串值
     * @param string $key
     * @param mixed $value
     * @param int|null $ttl
     * @param TcpConnection|null $connection
     */
    public function set(string $key, mixed $value, ?int $ttl = null, ?TcpConnection $connection = null): void;

    /**
     * 获取字符串值
     * @param string $key
     * @param TcpConnection|null $connection
     */
    public function get(string $key, ?TcpConnection $connection = null): void;

    /**
     * 设置哈希表字段
     * @param string $key
     * @param string $field
     * @param mixed $value
     * @param TcpConnection|null $connection
     */
    public function hSet(string $key, string $field, mixed $value, ?TcpConnection $connection = null): void;

    /**
     * 从左侧推入列表
     * @param string $key
     * @param mixed $value
     * @param TcpConnection|null $connection
     */
    public function lPush(string $key, mixed $value, ?TcpConnection $connection = null): void;

    /**
     * 添加集合成员
     * @param string $key
     * @param mixed $member
     * @param TcpConnection|null $connection
     */
    public function sAdd(string $key, mixed $member, ?TcpConnection $connection = null): void;

    /**
     * 添加有序集合成员
     * @param string $key
     * @param float $score
     * @param mixed $member
     * @param TcpConnection|null $connection
     */
    public function zAdd(string $key, float $score, mixed $member, ?TcpConnection $connection = null): void;

    /**
     * 删除键
     * @param string $key
     * @param TcpConnection|null $connection
     */
    public function del(string $key, ?TcpConnection $connection = null): void;

    /**
     * 设置过期时间
     * @param string $key
     * @param int $ttl
     * @param TcpConnection|null $connection
     */
    public function expire(string $key, int $ttl, ?TcpConnection $connection = null): void;
} 