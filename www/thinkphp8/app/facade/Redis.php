<?php
declare(strict_types=1);

namespace app\facade;

use think\Facade;
use app\service\RedisService;

/**
 * Redis Facade类
 * 
 * @method static \Redis getRedis() 获取Redis原始实例
 * @method static \think\cache\driver\Redis getThinkRedis() 获取ThinkPHP Redis缓存驱动实例
 * @method static bool acquireLock(string $key, int $ttl = 10, string $value = '') 尝试获取分布式锁
 * @method static bool releaseLock(string $key) 释放分布式锁
 * @method static \app\service\redis\StringService string() 获取String类型服务
 * @method static \app\service\redis\HashService hash() 获取Hash类型服务
 * @method static \app\service\redis\ListService list() 获取List类型服务
 * @method static \app\service\redis\SetService set() 获取Set类型服务
 * @method static \app\service\redis\ZSetService zset() 获取Sorted Set类型服务
 * @method static \app\service\redis\GeoService geo() 获取Geo类型服务
 * @method static \app\service\redis\HyperLogLogService hyperLogLog() 获取HyperLogLog类型服务
 * @method static \app\service\redis\BitMapService bitmap() 获取BitMap类型服务
 * 
 * @see \app\service\RedisService
 */
class Redis extends Facade
{
    /**
     * 获取当前Facade对应类名
     * 
     * @return string
     */
    protected static function getFacadeClass(): string
    {
        return RedisService::class;
    }
} 