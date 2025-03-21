<?php

declare(strict_types=1);

namespace think\queue\config;

use think\facade\Log;
use think\facade\Config;
use think\facade\Cache;

/**
 * 配置热加载管理器
 * 用于在运行时动态加载和更新配置
 */
class HotReloadManager
{
    /**
     * Redis缓存键前缀
     */
    protected $keyPrefix = 'queue:config:';

    /**
     * 缓存的配置
     */
    protected $cachedConfig = [];

    /**
     * 上次刷新时间
     */
    protected $lastRefreshTime = 0;

    /**
     * 刷新间隔（秒）
     */
    protected $refreshInterval = 60;

    /**
     * 租户ID
     */
    protected $tenantId = 'default';

    /**
     * 单例实例映射
     * 按租户ID存储不同的实例
     */
    private static $instances = [];

    /**
     * 私有构造函数，防止外部实例化
     * 
     * @param string $tenantId 租户ID
     */
    private function __construct(string $tenantId = 'default')
    {
        $this->tenantId = $tenantId;
        $this->keyPrefix = 'queue:config:' . $tenantId . ':';
        $this->loadConfigFromCache();
        $this->lastRefreshTime = time();
    }

    /**
     * 获取实例
     * 
     * @param string $tenantId 租户ID
     * @return HotReloadManager
     */
    public static function getInstance(string $tenantId = 'default'): HotReloadManager
    {
        if (!isset(self::$instances[$tenantId])) {
            self::$instances[$tenantId] = new self($tenantId);
        }

        return self::$instances[$tenantId];
    }

    /**
     * 获取配置值
     * 
     * @param string $key 配置键名
     * @param mixed $default 默认值
     * @return mixed 配置值
     */
    public function get(string $key, mixed $default = null): mixed
    {
        // 检查是否需要刷新配置
        $this->checkRefresh();

        // 从缓存的配置中获取值
        return $this->cachedConfig[$key] ?? $default;
    }

    /**
     * 设置配置值
     * 
     * @param string $key 配置键名
     * @param mixed $value 配置值
     * @return bool 操作是否成功
     */
    public function set(string $key, $value): bool
    {
        // 更新本地缓存
        $this->cachedConfig[$key] = $value;

        // 保存到Redis
        $cacheKey = $this->getKey($key);
        $result = Cache::set($cacheKey, $value);

        if ($result) {
            Log::info('Queue config updated', ['key' => $key, 'value' => $value]);
        } else {
            Log::error('Failed to update queue config', ['key' => $key]);
        }

        return $result;
    }

    /**
     * 批量设置配置
     * 
     * @param array $config 配置数组
     * @return bool 操作是否成功
     */
    public function setMultiple(array $config): bool
    {
        $success = true;

        foreach ($config as $key => $value) {
            if (!$this->set($key, $value)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * 删除配置
     * 
     * @param string $key 配置键名
     * @return bool 操作是否成功
     */
    public function delete(string $key): bool
    {
        // 从本地缓存中删除
        if (isset($this->cachedConfig[$key])) {
            unset($this->cachedConfig[$key]);
        }

        // 从Redis中删除
        $cacheKey = $this->getKey($key);
        $result = Cache::delete($cacheKey);

        if ($result) {
            Log::info('Queue config deleted', ['key' => $key]);
        }

        return $result;
    }

    /**
     * 检查是否需要刷新配置
     * 
     * @return void
     */
    protected function checkRefresh(): void
    {
        $now = time();

        // 如果距离上次刷新时间超过刷新间隔，则刷新配置
        if ($now - $this->lastRefreshTime >= $this->refreshInterval) {
            $this->loadConfigFromCache();
            $this->lastRefreshTime = $now;
        }
    }

    /**
     * 从Redis缓存加载配置
     * 
     * @return void
     */
    protected function loadConfigFromCache(): void
    {
        // 获取所有配置键
        $keys = Cache::get($this->keyPrefix . 'keys', []);

        if (empty($keys)) {
            // 如果没有缓存的配置键，则初始化配置
            $this->initializeConfig();
            return;
        }

        // 加载每个配置项
        foreach ($keys as $key) {
            $cacheKey = $this->getKey($key);
            $value = Cache::get($cacheKey);

            if ($value !== null) {
                $this->cachedConfig[$key] = $value;
            }
        }

        Log::debug('Loaded queue config from cache ,count => {count}', ['count' => count($keys)]);
    }

    /**
     * 初始化配置
     * 
     * @return void
     */
    protected function initializeConfig(): void
    {
        // 从ThinkPHP配置中获取队列配置
        $queueConfig = Config::get('queue', []);
        $kafkaConfig = Config::get('kafka', []);

        // 合并配置
        $config = array_merge($queueConfig, $kafkaConfig);

        // 保存到Redis
        $keys = [];
        foreach ($config as $key => $value) {
            if (is_array($value)) {
                // 对于数组类型的配置，递归处理
                $this->saveArrayConfig($key, $value, $keys);
            } else {
                // 对于简单类型的配置，直接保存
                $cacheKey = $this->getKey($key);
                Cache::set($cacheKey, $value);
                $keys[] = $key;
                $this->cachedConfig[$key] = $value;
            }
        }

        // 保存配置键列表
        Cache::set($this->keyPrefix . 'keys', $keys);

        Log::info('Initialized queue config: {count}', ['count' => count($keys)]);
    }

    /**
     * 保存数组类型的配置
     * 
     * @param string $prefix 键前缀
     * @param array $config 配置数组
     * @param array &$keys 配置键列表
     * @return void
     */
    protected function saveArrayConfig(string $prefix, array $config, array &$keys): void
    {
        foreach ($config as $key => $value) {
            $fullKey = $prefix . '.' . $key;

            if (is_array($value)) {
                // 递归处理嵌套数组
                $this->saveArrayConfig($fullKey, $value, $keys);
            } else {
                // 保存叶子节点
                $cacheKey = $this->getKey($fullKey);
                Cache::set($cacheKey, $value);
                $keys[] = $fullKey;
                $this->cachedConfig[$fullKey] = $value;
            }
        }
    }

    /**
     * 生成Redis缓存键
     * 
     * @param string $key 配置键名
     * @return string Redis缓存键
     */
    protected function getKey(string $key): string
    {
        return $this->keyPrefix . str_replace('.', ':', $key);
    }

    /**
     * 设置刷新间隔
     * 
     * @param int $interval 刷新间隔（秒）
     * @return void
     */
    public function setRefreshInterval(int $interval): void
    {
        $this->refreshInterval = $interval;
    }

    /**
     * 强制刷新配置
     * 
     * @return void
     */
    public function forceRefresh(): void
    {
        $this->loadConfigFromCache();
        $this->lastRefreshTime = time();
        Log::info('Force refreshed queue config');
    }
}
