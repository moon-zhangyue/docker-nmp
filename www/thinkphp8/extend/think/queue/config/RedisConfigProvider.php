<?php

declare(strict_types=1);

namespace think\queue\config;

use think\facade\Cache;
use think\facade\Log;

/**
 * Redis配置提供者
 * 用于从Redis缓存获取和更新配置
 */
class RedisConfigProvider implements ConfigProviderInterface
{
    /**
     * Redis缓存键前缀
     */
    protected $keyPrefix = 'queue:config:';

    /**
     * 租户ID
     */
    protected $tenantId = 'default';

    /**
     * 构造函数
     * 
     * @param string $tenantId 租户ID
     */
    public function __construct(string $tenantId = 'default')
    {
        $this->tenantId = $tenantId;
    }

    /**
     * 设置租户ID
     * 
     * @param string $tenantId 租户ID
     * @return self
     */
    public function setTenantId(string $tenantId): self
    {
        $this->tenantId = $tenantId;
        return $this;
    }

    /**
     * 获取配置值
     * 
     * @param string $key 配置键名
     * @param mixed $default 默认值
     * @return mixed 配置值
     */
    public function get(string $key, $default = null)
    {
        $cacheKey = $this->getKey($key);
        $value = Cache::get($cacheKey, $default);

        return $value;
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
        $cacheKey = $this->getKey($key);
        $result = Cache::set($cacheKey, $value);

        if ($result) {
            // 更新配置键列表
            $this->addKeyToList($key);

            Log::info('Queue config updated in Redis: {key}, tenant_id: {tenant_id}', [
                'key' => $key,
                'tenant_id' => $this->tenantId
            ]);
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
        $cacheKey = $this->getKey($key);
        $result = Cache::delete($cacheKey);

        if ($result) {
            // 从配置键列表中移除
            $this->removeKeyFromList($key);

            Log::info('Queue config deleted from Redis: {key}, tenant_id: {tenant_id}', [
                'key' => $key,
                'tenant_id' => $this->tenantId
            ]);
        }

        return $result;
    }

    /**
     * 添加键到配置键列表
     * 
     * @param string $key 配置键名
     * @return void
     */
    protected function addKeyToList(string $key): void
    {
        $keysKey = $this->keyPrefix . 'keys';
        $keys = Cache::get($keysKey, []);

        if (!in_array($key, $keys)) {
            $keys[] = $key;
            Cache::set($keysKey, $keys);
        }
    }

    /**
     * 从配置键列表中移除键
     * 
     * @param string $key 配置键名
     * @return void
     */
    protected function removeKeyFromList(string $key): void
    {
        $keysKey = $this->keyPrefix . 'keys';
        $keys = Cache::get($keysKey, []);

        if (in_array($key, $keys)) {
            $keys = array_diff($keys, [$key]);
            Cache::set($keysKey, $keys);
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
        return $this->keyPrefix . $this->tenantId . ':' . str_replace('.', ':', $key);
    }

    /**
     * 获取所有配置
     * 
     * @return array 所有配置
     */
    public function getAll(): array
    {
        $keysKey = $this->keyPrefix . 'keys';
        $keys = Cache::get($keysKey, []);
        $config = [];

        foreach ($keys as $key) {
            $config[$key] = $this->get($key);
        }

        return $config;
    }

    /**
     * 清除所有配置
     * 
     * @return bool 操作是否成功
     */
    public function clear(): bool
    {
        $keysKey = $this->keyPrefix . 'keys';
        $keys = Cache::get($keysKey, []);
        $success = true;

        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }

        // 删除键列表
        if (!Cache::delete($keysKey)) {
            $success = false;
        }

        return $success;
    }

    /**
     * 获取所有配置键
     * 
     * @return array 配置键列表
     */
    public function getKeys(): array
    {
        $keysKey = $this->keyPrefix . 'keys';
        return Cache::get($keysKey, []);
    }
}
