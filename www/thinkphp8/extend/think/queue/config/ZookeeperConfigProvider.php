<?php

declare(strict_types=1);

namespace think\queue\config;

use think\queue\log\StructuredLogger;

/**
 * ZooKeeper配置提供者
 * 用于从ZooKeeper配置中心获取和更新配置
 */
class ZookeeperConfigProvider implements ConfigProviderInterface
{
    /**
     * ZooKeeper连接字符串
     */
    protected $connectionString;

    /**
     * ZooKeeper路径前缀
     */
    protected $keyPrefix = '/queue/config/';

    /**
     * 租户ID
     */
    protected $tenantId = 'default';

    /**
     * ZooKeeper客户端
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
     * @param string $connectionString ZooKeeper连接字符串
     * @param string $tenantId 租户ID
     */
    public function __construct(string $connectionString = 'localhost:2181', string $tenantId = 'default')
    {
        $this->connectionString = $connectionString;
        $this->tenantId = $tenantId;
        $this->logger = StructuredLogger::getInstance($tenantId);

        // 初始化ZooKeeper客户端
        $this->initClient();
        $this->loadConfigFromZookeeper();
    }

    /**
     * 初始化ZooKeeper客户端
     * 
     * @return void
     */
    protected function initClient(): void
    {
        // 检查ZooKeeper扩展是否可用
        if (!class_exists('\ZooKeeper')) {
            $this->logger->error('ZooKeeper extension is not available', [
                'connection_string' => $this->connectionString
            ]);
            return;
        }

        try {
            $this->client = new \ZooKeeper($this->connectionString);
            $this->logger->info('ZooKeeper client initialized', [
                'connection_string' => $this->connectionString
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to initialize ZooKeeper client', [
                'connection_string' => $this->connectionString,
                'error' => $e->getMessage()
            ]);
        }
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
            // 检查ZooKeeper客户端是否可用
            if (!$this->client) {
                $this->logger->warning('ZooKeeper client not available, storing config in local cache only', [
                    'key' => $key
                ]);
                // 仅更新本地缓存
                $this->cachedConfig[$key] = $value;
                return true;
            }

            $zkPath = $this->getZkPath($key);
            $encodedValue = json_encode($value);

            // 确保父节点存在
            $this->ensurePathExists(dirname($zkPath));

            // 如果节点不存在，创建节点
            if (!$this->client->exists($zkPath)) {
                $this->client->create($zkPath, $encodedValue, [["perms" => \ZooKeeper::PERM_ALL, "scheme" => "world", "id" => "anyone"]]);
            } else {
                // 如果节点存在，更新节点
                $this->client->set($zkPath, $encodedValue);
            }

            // 更新本地缓存
            $this->cachedConfig[$key] = $value;

            $this->logger->info('Queue config updated in ZooKeeper', [
                'key' => $key,
                'value' => $value
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to update queue config in ZooKeeper', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);

            // 尝试仅更新本地缓存
            $this->cachedConfig[$key] = $value;
            return true;
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
        // 检查ZooKeeper客户端是否可用
        if (!$this->client) {
            $this->logger->warning('ZooKeeper client not available, storing configs in local cache only');
            // 仅更新本地缓存
            foreach ($config as $key => $value) {
                $this->cachedConfig[$key] = $value;
            }
            return true;
        }

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
            // 检查ZooKeeper客户端是否可用
            if (!$this->client) {
                $this->logger->warning('ZooKeeper client not available, removing config from local cache only', [
                    'key' => $key
                ]);
                // 从本地缓存中删除
                if (isset($this->cachedConfig[$key])) {
                    unset($this->cachedConfig[$key]);
                    return true;
                }
                return false;
            }

            $zkPath = $this->getZkPath($key);

            if ($this->client->exists($zkPath)) {
                $this->client->delete($zkPath);

                // 从本地缓存中删除
                if (isset($this->cachedConfig[$key])) {
                    unset($this->cachedConfig[$key]);
                }

                $this->logger->info('Queue config deleted from ZooKeeper', [
                    'key' => $key
                ]);

                return true;
            }

            return false;
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete queue config from ZooKeeper', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);

            // 尝试仅从本地缓存中删除
            if (isset($this->cachedConfig[$key])) {
                unset($this->cachedConfig[$key]);
                return true;
            }

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
            // 检查ZooKeeper客户端是否可用
            if (!$this->client) {
                $this->logger->warning('ZooKeeper client not available, clearing local cache only');
                // 清空本地缓存
                $this->cachedConfig = [];
                return true;
            }

            $basePath = $this->keyPrefix . $this->tenantId;

            // 递归删除节点
            $this->recursiveDelete($basePath);

            // 清空本地缓存
            $this->cachedConfig = [];

            $this->logger->info('All queue configs cleared from ZooKeeper');

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to clear queue configs from ZooKeeper', [
                'error' => $e->getMessage()
            ]);

            // 尝试仅清空本地缓存
            $this->cachedConfig = [];
            return true;
        }
    }

    /**
     * 递归删除ZooKeeper节点
     * 
     * @param string $path 路径
     * @return void
     */
    protected function recursiveDelete(string $path): void
    {
        // 检查ZooKeeper客户端是否可用
        if (!$this->client) {
            return;
        }

        if ($this->client->exists($path)) {
            $children = $this->client->getChildren($path);

            foreach ($children as $child) {
                $childPath = $path . '/' . $child;
                $this->recursiveDelete($childPath);
            }

            $this->client->delete($path);
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
            $this->loadConfigFromZookeeper();
            $this->lastRefreshTime = $now;
        }
    }

    /**
     * 从ZooKeeper加载配置
     * 
     * @return void
     */
    protected function loadConfigFromZookeeper(): void
    {
        // 检查ZooKeeper客户端是否可用
        if (!$this->client) {
            $this->logger->warning('ZooKeeper client not available, using local cache only');
            return;
        }

        try {
            $basePath = $this->keyPrefix . $this->tenantId;

            // 确保基础路径存在
            $this->ensurePathExists($basePath);

            // 递归获取所有配置
            $config = [];
            $this->recursiveGetConfig($basePath, '', $config);

            $this->cachedConfig = $config;

            $this->logger->debug('Loaded queue config from ZooKeeper', [
                'count' => count($config)
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to load queue config from ZooKeeper', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 递归获取ZooKeeper配置
     * 
     * @param string $path 当前路径
     * @param string $keyPrefix 键前缀
     * @param array &$config 配置数组引用
     * @return void
     */
    protected function recursiveGetConfig(string $path, string $keyPrefix, array &$config): void
    {
        // 检查ZooKeeper客户端是否可用
        if (!$this->client) {
            return;
        }

        if ($this->client->exists($path)) {
            // 获取当前节点的值
            if ($keyPrefix !== '') {
                $data = $this->client->get($path);
                if ($data) {
                    $config[$keyPrefix] = json_decode($data, true);
                }
            }

            // 获取子节点
            $children = $this->client->getChildren($path);

            foreach ($children as $child) {
                $childPath = $path . '/' . $child;
                $childKey = $keyPrefix === '' ? $child : $keyPrefix . '.' . $child;
                $this->recursiveGetConfig($childPath, $childKey, $config);
            }
        }
    }

    /**
     * 确保路径存在
     * 
     * @param string $path 路径
     * @return void
     */
    protected function ensurePathExists(string $path): void
    {
        // 检查ZooKeeper客户端是否可用
        if (!$this->client) {
            return;
        }

        if ($path === '' || $path === '/') {
            return;
        }

        if (!$this->client->exists($path)) {
            // 确保父路径存在
            $this->ensurePathExists(dirname($path));

            // 创建当前路径
            $this->client->create($path, '', [["perms" => \ZooKeeper::PERM_ALL, "scheme" => "world", "id" => "anyone"]]);
        }
    }

    /**
     * 生成ZooKeeper路径
     * 
     * @param string $key 配置键名
     * @return string ZooKeeper路径
     */
    protected function getZkPath(string $key): string
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
        $this->loadConfigFromZookeeper();
        $this->lastRefreshTime = time();
        $this->logger->info('Force refreshed queue config from ZooKeeper');
    }

    /**
     * 设置ZooKeeper客户端（用于测试）
     * 
     * @param object $client ZooKeeper客户端或其模拟对象
     * @return void
     */
    public function setClient(object $client): void
    {
        $this->client = $client;
    }
}
