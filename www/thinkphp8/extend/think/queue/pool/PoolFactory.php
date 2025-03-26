<?php

namespace think\queue\pool;

use think\facade\Config;
use think\facade\Log;

/**
 * 连接池工厂
 * 
 * 用于创建和管理各种连接池
 */
class PoolFactory
{
    /**
     * 连接池实例
     * @var array
     */
    protected static $pools = [];
    
    /**
     * 获取连接池
     * 
     * @param string $name 连接池名称
     * @return mixed
     */
    public static function getPool(string $name)
    {
        if (isset(self::$pools[$name])) {
            return self::$pools[$name];
        }
        var_dump(self::$pools[$name]);
        $config = Config::get('queue.connections.' . $name);
        if (!$config) {
            Log::error('获取连接池失败: 连接配置不存在,name:{name}', ['name' => $name]);
            return null;
        }
        
        try {
            switch ($config['type']) {
                case 'kafka':
                    $pool = new KafkaConnectionPool($config);
                    break;
                default:
                    Log::error('不支持的连接池类型,type:{type}', ['type' => $config['type']]);
                    return null;
            }
            
            self::$pools[$name] = $pool;
            
            Log::info('连接池创建成功,name:{name},type:{type}', [
                'name' => $name,
                'type' => $config['type']
            ]);
            
            return $pool;
        } catch (\Exception $e) {
            Log::error('连接池创建失败,name:{name},error:{error},trace:{trace}', [
                'name' => $name,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }
    
    /**
     * 关闭所有连接池
     */
    public static function closeAll()
    {
        foreach (self::$pools as $name => $pool) {
            try {
                $pool->close();
                Log::info('连接池已关闭,name:{name}', ['name' => $name]);
            } catch (\Exception $e) {
                Log::error('关闭连接池失败,name:{name},error:{error}', [
                    'name' => $name,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        self::$pools = [];
    }
} 