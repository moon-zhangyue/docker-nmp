<?php

namespace think\queue\pool;

use think\facade\Log;
use think\queue\pool\ConnectionPool;

/**
 * 连接池工厂
 * 
 * 用于管理和获取不同的连接池实例
 */
class PoolFactory
{
    /**
     * 连接池实例
     * @var array
     */
    protected static $pools = [];
    
    /**
     * 配置信息
     * @var array
     */
    protected static $config = [];
    
    /**
     * 初始化工厂
     * 
     * @param array $config 全局配置
     * @return void
     */
    public static function init(array $config): void
    {
        self::$config = $config;
        Log::info('连接池工厂初始化完成');
    }
    
    /**
     * 获取连接池实例
     * 
     * @param string $name 连接池名称
     * @param array $config 连接池配置，如果未提供则使用全局配置
     * @return ConnectionPool
     */
    public static function getPool(string $name, array $config = []): ConnectionPool
    {
        // 如果已经存在该名称的连接池，直接返回
        if (isset(self::$pools[$name])) {
            return self::$pools[$name];
        }
        
        // 合并全局配置和具体配置
        $poolConfig = $config ?: (self::$config[$name] ?? []);
        
        // 确保配置中包含连接池设置
        if (!isset($poolConfig['pool'])) {
            $poolConfig['pool'] = [
                'min_connections' => 5,
                'max_connections' => 20,
                'max_idle_time' => 60,
                'max_wait_time' => 3.0,
                'get_timeout' => 3.0,
                'check_interval' => 30 * 1000,
            ];
        }
        
        // 创建连接池实例
        $pool = new ConnectionPool($poolConfig);
        
        // 添加到连接池数组
        self::$pools[$name] = $pool;
        
        Log::info("创建连接池: {$name}", [
            'min_connections' => $poolConfig['pool']['min_connections'] ?? 5,
            'max_connections' => $poolConfig['pool']['max_connections'] ?? 20,
        ]);
        
        return $pool;
    }
    
    /**
     * 关闭所有连接池
     * 
     * @return void
     */
    public static function closeAll(): void
    {
        foreach (self::$pools as $name => $pool) {
            try {
                Log::info("关闭连接池: {$name}");
                $pool->closePool();
            } catch (\Exception $e) {
                Log::error("关闭连接池{$name}失败: " . $e->getMessage());
            }
        }
        
        self::$pools = [];
    }
    
    /**
     * 获取所有连接池状态
     * 
     * @return array
     */
    public static function getAllPoolStatus(): array
    {
        $status = [];
        
        foreach (self::$pools as $name => $pool) {
            $status[$name] = [
                'created' => $pool->getCreatedNum(),
                'idle' => $pool->getIdleNum(),
                'using' => $pool->getUsingNum(),
                'max' => $pool->getMaxObjectNum(),
            ];
        }
        
        return $status;
    }
} 