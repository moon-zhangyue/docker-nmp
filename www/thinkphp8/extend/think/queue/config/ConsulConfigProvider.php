<?php

declare(strict_types=1);

namespace think\queue\config;

use think\facade\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Consul配置提供者
 * 用于从Consul配置中心获取和更新配置
 */
class ConsulConfigProvider implements ConfigProviderInterface
{
    /**
     * Consul API地址
     */
    protected $apiUrl;

    /**
     * Consul KV路径前缀
     */
    protected $keyPrefix = 'queue/config/';

    /**
     * 租户ID
     */
    protected $tenantId = 'default';

    /**
     * HTTP客户端
     */
    protected $client;

    /**
     * 缓存的配置
     */
    protected $cachedConfig = [];

    /**
     * 上次刷新时间
     */
    protected $lastRefreshTime = 0;

    /**
     * 配置刷新间隔（秒）
     */
    protected $refreshInterval = 60;

    /**
     * 构造函数
     * 
     * @param string $apiUrl Consul API地址
     * @param string $tenantId 租户ID
     */
    public function __construct(string $apiUrl = 'http://localhost:8500', string $tenantId = 'default')
    {
        $this->apiUrl = $apiUrl;
        $this->tenantId = $tenantId;
        $this->client = new Client([
            'base_uri' => $apiUrl,
            'timeout' => 5.0,
        ]);
        $this->lastRefreshTime = time();
        $this->loadConfigFromConsul();
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
        try {
            $consulKey = $this->getConsulKey($key);
            $encodedValue = base64_encode(json_encode($value));

            $response = $this->client->put("/v1/kv/{$consulKey}", [
                'body' => $encodedValue
            ]);

            if ($response->getStatusCode() === 200) {
                // 更新本地缓存
                $this->cachedConfig[$key] = $value;

                Log::info('Queue config updated in Consul: {key}, tenant_id: {tenant_id}', [
                    'key' => $key,
                    'tenant_id' => $this->tenantId,
                    'value' => $value
                ]);

                return true;
            }

            return false;
        } catch (GuzzleException $e) {
            Log::error('Failed to update queue config in Consul', [
                'key' => $key,
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
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
        try {
            $consulKey = $this->getConsulKey($key);

            $response = $this->client->delete("/v1/kv/{$consulKey}");

            if ($response->getStatusCode() === 200) {
                // 从本地缓存中删除
                if (isset($this->cachedConfig[$key])) {
                    unset($this->cachedConfig[$key]);
                }

                Log::info('Queue config deleted from Consul: {key}, tenant_id: {tenant_id}', [
                    'key' => $key,
                    'tenant_id' => $this->tenantId
                ]);

                return true;
            }

            return false;
        } catch (GuzzleException $e) {
            Log::error('Failed to delete queue config from Consul', [
                'key' => $key,
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * 获取所有配置键
     * 
     * @return array 配置键列表
     */
    public function getKeys(): array
    {
        return array_keys($this->cachedConfig);
    }

    /**
     * 获取所有配置
     * 
     * @return array 配置数组
     */
    public function getAll(): array
    {
        // 检查是否需要刷新配置
        $this->checkRefresh();

        return $this->cachedConfig;
    }

    /**
     * 清除所有配置
     * 
     * @return bool 操作是否成功
     */
    public function clear(): bool
    {
        try {
            $prefix = $this->keyPrefix . $this->tenantId . '/';

            $response = $this->client->delete("/v1/kv/{$prefix}?recurse=true");

            if ($response->getStatusCode() === 200) {
                // 清空本地缓存
                $this->cachedConfig = [];

                Log::info('All queue configs cleared from Consul, tenant_id: {tenant_id}', [
                    'tenant_id' => $this->tenantId
                ]);

                return true;
            }

            return false;
        } catch (GuzzleException $e) {
            Log::error('Failed to clear queue configs from Consul', [
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
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
            $this->loadConfigFromConsul();
            $this->lastRefreshTime = $now;
        }
    }

    /**
     * 从Consul加载配置
     * 
     * @return void
     */
    protected function loadConfigFromConsul(): void
    {
        try {
            $prefix = $this->keyPrefix . $this->tenantId . '/';

            Log::info('Attempting to load config from Consul, tenant_id: {tenant_id}, api_url: {api_url}, prefix: {prefix}', [
                'tenant_id' => $this->tenantId,
                'api_url' => $this->apiUrl,
                'prefix' => $prefix
            ]);

            $response = $this->client->get("/v1/kv/{$prefix}?recurse=true");

            if ($response->getStatusCode() === 200) {
                $content = $response->getBody()->getContents();
                $data = json_decode($content, true);

                if (is_array($data) && !empty($data)) {
                    $config = [];

                    foreach ($data as $item) {
                        $key = str_replace($prefix, '', $item['Key']);
                        $key = str_replace('/', '.', $key);
                        $value = json_decode(base64_decode($item['Value']), true);

                        $config[$key] = $value;
                    }

                    $this->cachedConfig = $config;

                    Log::info('Loaded queue config from Consul, tenant_id: {tenant_id}, count: {count}', [
                        'tenant_id' => $this->tenantId,
                        'count' => count($config),
                        'keys' => array_keys($config)
                    ]);
                } else {
                    Log::warning('No config data found in Consul or invalid response', [
                        'tenant_id' => $this->tenantId,
                        'response_status' => $response->getStatusCode(),
                        'is_array' => is_array($data),
                        'data_count' => is_array($data) ? count($data) : 0
                    ]);
                }
            } else {
                Log::warning('Unexpected response from Consul', [
                    'tenant_id' => $this->tenantId,
                    'status_code' => $response->getStatusCode()
                ]);
            }
        } catch (GuzzleException $e) {
            Log::error('Failed to load queue config from Consul', [
                'tenant_id' => $this->tenantId,
                'api_url' => $this->apiUrl,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_type' => get_class($e)
            ]);
        } catch (\Exception $e) {
            Log::error('Unexpected error when loading config from Consul', [
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_type' => get_class($e)
            ]);
        }
    }

    /**
     * 生成Consul键
     * 
     * @param string $key 配置键名
     * @return string Consul键
     */
    protected function getConsulKey(string $key): string
    {
        return $this->keyPrefix . $this->tenantId . '/' . str_replace('.', '/', $key);
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
        $this->loadConfigFromConsul();
        $this->lastRefreshTime = time();
        Log::info('Force refreshed queue config from Consul, tenant_id: {tenant_id}', [
            'tenant_id' => $this->tenantId
        ]);
    }

    /**
     * 设置HTTP客户端（用于测试）
     * 
     * @param object $client HTTP客户端或其模拟对象
     * @return void
     */
    public function setClient(object $client): void
    {
        $this->client = $client;
    }
}
