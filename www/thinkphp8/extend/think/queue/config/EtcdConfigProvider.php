<?php

declare(strict_types=1);

namespace think\queue\config;

use think\queue\log\StructuredLogger;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Etcd配置提供者
 * 用于从Etcd配置中心获取和更新配置
 */
class EtcdConfigProvider implements ConfigProviderInterface
{
    /**
     * Etcd API地址
     */
    protected $apiUrl;

    /**
     * Etcd KV路径前缀
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
     * 结构化日志记录器
     */
    protected $logger;

    /**
     * 构造函数
     * 
     * @param string $apiUrl Etcd API地址
     * @param string $tenantId 租户ID
     */
    public function __construct(string $apiUrl = 'http://localhost:2379', string $tenantId = 'default')
    {
        $this->apiUrl = $apiUrl;
        $this->tenantId = $tenantId;
        $this->client = new Client([
            'base_uri' => $apiUrl,
            'timeout' => 5.0,
        ]);
        $this->lastRefreshTime = time();
        $this->logger = StructuredLogger::getInstance($tenantId);
        $this->loadConfigFromEtcd();
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
        $this->logger = StructuredLogger::getInstance($tenantId);
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
            $etcdKey = $this->getEtcdKey($key);
            $encodedValue = base64_encode(json_encode($value));

            $response = $this->client->put("/v3/kv/put", [
                'json' => [
                    'key' => base64_encode($etcdKey),
                    'value' => $encodedValue
                ]
            ]);

            if ($response->getStatusCode() === 200) {
                // 更新本地缓存
                $this->cachedConfig[$key] = $value;

                $this->logger->info('Queue config updated in Etcd', [
                    'key' => $key,
                    'value' => $value
                ]);

                return true;
            }

            return false;
        } catch (GuzzleException $e) {
            $this->logger->error('Failed to update queue config in Etcd', [
                'key' => $key,
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
            $etcdKey = $this->getEtcdKey($key);

            $response = $this->client->post("/v3/kv/deleterange", [
                'json' => [
                    'key' => base64_encode($etcdKey)
                ]
            ]);

            if ($response->getStatusCode() === 200) {
                // 从本地缓存中删除
                if (isset($this->cachedConfig[$key])) {
                    unset($this->cachedConfig[$key]);
                }

                $this->logger->info('Queue config deleted from Etcd', [
                    'key' => $key
                ]);

                return true;
            }

            return false;
        } catch (GuzzleException $e) {
            $this->logger->error('Failed to delete queue config from Etcd', [
                'key' => $key,
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

            $response = $this->client->post("/v3/kv/deleterange", [
                'json' => [
                    'key' => base64_encode($prefix),
                    'range_end' => base64_encode($this->getNextPrefixKey($prefix))
                ]
            ]);

            if ($response->getStatusCode() === 200) {
                // 清空本地缓存
                $this->cachedConfig = [];

                $this->logger->info('All queue configs cleared from Etcd');

                return true;
            }

            return false;
        } catch (GuzzleException $e) {
            $this->logger->error('Failed to clear queue configs from Etcd', [
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
            $this->loadConfigFromEtcd();
            $this->lastRefreshTime = $now;
        }
    }

    /**
     * 从Etcd加载配置
     * 
     * @return void
     */
    protected function loadConfigFromEtcd(): void
    {
        try {
            $prefix = $this->keyPrefix . $this->tenantId . '/';

            $response = $this->client->post("/v3/kv/range", [
                'json' => [
                    'key' => base64_encode($prefix),
                    'range_end' => base64_encode($this->getNextPrefixKey($prefix))
                ]
            ]);

            if ($response->getStatusCode() === 200) {
                $data = json_decode($response->getBody()->getContents(), true);

                if (isset($data['kvs']) && is_array($data['kvs'])) {
                    $config = [];

                    foreach ($data['kvs'] as $item) {
                        $key = str_replace($prefix, '', base64_decode($item['key']));
                        $key = str_replace('/', '.', $key);
                        $value = json_decode(base64_decode($item['value']), true);

                        $config[$key] = $value;
                    }

                    $this->cachedConfig = $config;

                    $this->logger->debug('Loaded queue config from Etcd', [
                        'count' => count($config)
                    ]);
                }
            }
        } catch (GuzzleException $e) {
            $this->logger->error('Failed to load queue config from Etcd', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 生成Etcd键
     * 
     * @param string $key 配置键名
     * @return string Etcd键
     */
    protected function getEtcdKey(string $key): string
    {
        return $this->keyPrefix . $this->tenantId . '/' . str_replace('.', '/', $key);
    }

    /**
     * 获取下一个前缀键（用于范围查询）
     * 
     * @param string $prefix 前缀
     * @return string 下一个前缀
     */
    protected function getNextPrefixKey(string $prefix): string
    {
        $lastChar = substr($prefix, -1);
        $nextChar = chr(ord($lastChar) + 1);
        return substr($prefix, 0, -1) . $nextChar;
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
        $this->loadConfigFromEtcd();
        $this->lastRefreshTime = time();
        $this->logger->info('Force refreshed queue config from Etcd');
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
