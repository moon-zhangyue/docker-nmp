<?php

namespace App\Api\Redis\Interfaces;

interface RedisServiceInterface
{
    /**
     * 设置字符串值
     * @param string $key
     * @param mixed $value
     * @param int|null $ttl
     */
    public function set(string $key, mixed $value, ?int $ttl = null): void;

    /**
     * 获取字符串值
     * @param string $key
     * @return mixed
     */
    public function get(string $key): mixed;

    /**
     * 设置哈希表字段
     * @param string $key
     * @param string $field
     * @param mixed $value
     */
    public function hSet(string $key, string $field, mixed $value): void;

    /**
     * 从左侧推入列表
     * @param string $key
     * @param mixed $value
     */
    public function lPush(string $key, mixed $value): void;

    /**
     * 添加集合成员
     * @param string $key
     * @param mixed $member
     */
    public function sAdd(string $key, mixed $member): void;

    /**
     * 添加有序集合成员
     * @param string $key
     * @param float $score
     * @param mixed $member
     */
    public function zAdd(string $key, float $score, mixed $member): void;

    /**
     * 删除键
     * @param string $key
     */
    public function del(string $key): void;

    /**
     * 设置过期时间
     * @param string $key
     * @param int $ttl
     */
    public function expire(string $key, int $ttl): void;
} 