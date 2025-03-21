<?php

declare(strict_types=1);

namespace think\queue\tenant;

use think\facade\Log;
use think\facade\Cache;
use think\queue\log\StructuredLogger;

/**
 * 多租户管理器
 * 用于管理不同租户的配置和资源隔离
 */
class TenantManager
{
    /**
     * 租户列表缓存键
     */
    protected $tenantsKey = 'queue:tenants';

    /**
     * 当前租户ID
     */
    protected $currentTenantId = 'default';

    /**
     * 租户配置缓存键前缀
     */
    protected $tenantConfigPrefix = 'queue:tenant:config:';

    /**
     * 结构化日志记录器
     */
    protected $logger;

    /**
     * 单例实例
     */
    private static $instance = null;

    /**
     * 私有构造函数，防止外部实例化
     */
    private function __construct()
    {
        $this->logger = StructuredLogger::getInstance();
    }

    /**
     * 获取单例实例
     * 
     * @return TenantManager
     */
    public static function getInstance(): TenantManager
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * 创建新租户
     * 
     * @param string $tenantId 租户ID
     * @param array $config 租户配置
     * @return bool 操作是否成功
     */
    public function createTenant(string $tenantId, array $config = []): bool
    {
        // 检查租户ID是否已存在
        $tenants = $this->getAllTenants();

        if (in_array($tenantId, $tenants)) {
            $this->logger->warning('Tenant already exists', ['tenant_id' => $tenantId]);
            return false;
        }

        // 添加到租户列表
        $tenants[] = $tenantId;
        Cache::set($this->tenantsKey, $tenants);

        // 保存租户配置
        if (!empty($config)) {
            $this->setTenantConfig($tenantId, $config);
        }

        $this->logger->info('Tenant created', ['tenant_id' => $tenantId]);
        return true;
    }

    /**
     * 删除租户
     * 
     * @param string $tenantId 租户ID
     * @return bool 操作是否成功
     */
    public function deleteTenant(string $tenantId): bool
    {
        // 检查租户ID是否存在
        $tenants = $this->getAllTenants();

        if (!in_array($tenantId, $tenants)) {
            $this->logger->warning('Tenant does not exist', ['tenant_id' => $tenantId]);
            return false;
        }

        // 从租户列表中移除
        $tenants = array_diff($tenants, [$tenantId]);
        Cache::set($this->tenantsKey, $tenants);

        // 删除租户配置
        Cache::delete($this->tenantConfigPrefix . $tenantId);

        $this->logger->info('Tenant deleted', ['tenant_id' => $tenantId]);
        return true;
    }

    /**
     * 获取所有租户
     * 
     * @return array 租户ID列表
     */
    public function getAllTenants(): array
    {
        $tenants = Cache::get($this->tenantsKey, ['default']);
        return $tenants;
    }

    /**
     * 设置当前租户
     * 
     * @param string $tenantId 租户ID
     * @return self
     */
    public function setCurrentTenant(string $tenantId): self
    {
        $this->currentTenantId = $tenantId;
        $this->logger->setTenantId($tenantId);
        return $this;
    }

    /**
     * 获取当前租户ID
     * 
     * @return string 当前租户ID
     */
    public function getCurrentTenantId(): string
    {
        return $this->currentTenantId;
    }

    /**
     * 获取租户配置
     * 
     * @param string $tenantId 租户ID
     * @return array 租户配置
     */
    public function getTenantConfig(string $tenantId): array
    {
        return Cache::get($this->tenantConfigPrefix . $tenantId, []);
    }

    /**
     * 设置租户配置
     * 
     * @param string $tenantId 租户ID
     * @param array $config 租户配置
     * @return bool 操作是否成功
     */
    public function setTenantConfig(string $tenantId, array $config): bool
    {
        $result = Cache::set($this->tenantConfigPrefix . $tenantId, $config);

        if ($result) {
            $this->logger->info('Tenant config updated', [
                'tenant_id' => $tenantId,
                'config_keys' => array_keys($config)
            ]);
        }

        return $result;
    }

    /**
     * 获取租户特定配置项
     * 
     * @param string $tenantId 租户ID
     * @param string $key 配置键名
     * @param mixed $default 默认值
     * @return mixed 配置值
     */
    public function getTenantConfigItem(string $tenantId, string $key, $default = null)
    {
        $config = $this->getTenantConfig($tenantId);
        return $config[$key] ?? $default;
    }

    /**
     * 设置租户特定配置项
     * 
     * @param string $tenantId 租户ID
     * @param string $key 配置键名
     * @param mixed $value 配置值
     * @return bool 操作是否成功
     */
    public function setTenantConfigItem(string $tenantId, string $key, $value): bool
    {
        $config = $this->getTenantConfig($tenantId);
        $config[$key] = $value;
        return $this->setTenantConfig($tenantId, $config);
    }

    /**
     * 生成租户特定的主题名称
     * 
     * @param string $tenantId 租户ID
     * @param string $topic 原始主题名称
     * @return string 带有租户前缀的主题名称
     */
    public function getTenantSpecificTopic(string $tenantId, string $topic): string
    {
        return $tenantId . '.' . $topic;
    }

    /**
     * 检查租户是否存在
     * 
     * @param string $tenantId 租户ID
     * @return bool 租户是否存在
     */
    public function tenantExists(string $tenantId): bool
    {
        $tenants = $this->getAllTenants();
        return in_array($tenantId, $tenants);
    }
}
