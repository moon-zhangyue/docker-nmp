<?php

declare(strict_types=1);

namespace think\queue\tests;

use PHPUnit\Framework\TestCase;
use think\queue\tenant\TenantManager;
use think\facade\Cache;

/**
 * 多租户管理器单元测试
 */
class TenantManagerTest extends TestCase
{
    /**
     * 测试获取单例实例
     */
    public function testGetInstance()
    {
        $instance1 = TenantManager::getInstance();
        $instance2 = TenantManager::getInstance();

        $this->assertSame($instance1, $instance2);
    }

    /**
     * 测试创建租户
     */
    public function testCreateTenant()
    {
        // 模拟Cache门面
        Cache::shouldReceive('get')
            ->once()
            ->with('queue:tenants', ['default'])
            ->andReturn(['default']);

        Cache::shouldReceive('set')
            ->once()
            ->with('queue:tenants', ['default', 'test_tenant'])
            ->andReturn(true);

        Cache::shouldReceive('set')
            ->once()
            ->with('queue:tenant:config:test_tenant', ['key' => 'value'])
            ->andReturn(true);

        $tenantManager = TenantManager::getInstance();
        $result = $tenantManager->createTenant('test_tenant', ['key' => 'value']);

        $this->assertTrue($result);
    }

    /**
     * 测试创建已存在的租户
     */
    public function testCreateExistingTenant()
    {
        // 模拟Cache门面
        Cache::shouldReceive('get')
            ->once()
            ->with('queue:tenants', ['default'])
            ->andReturn(['default', 'existing_tenant']);

        $tenantManager = TenantManager::getInstance();
        $result = $tenantManager->createTenant('existing_tenant');

        $this->assertFalse($result);
    }

    /**
     * 测试删除租户
     */
    public function testDeleteTenant()
    {
        // 模拟Cache门面
        Cache::shouldReceive('get')
            ->once()
            ->with('queue:tenants', ['default'])
            ->andReturn(['default', 'test_tenant']);

        Cache::shouldReceive('set')
            ->once()
            ->with('queue:tenants', ['default'])
            ->andReturn(true);

        Cache::shouldReceive('delete')
            ->once()
            ->with('queue:tenant:config:test_tenant')
            ->andReturn(true);

        $tenantManager = TenantManager::getInstance();
        $result = $tenantManager->deleteTenant('test_tenant');

        $this->assertTrue($result);
    }

    /**
     * 测试删除不存在的租户
     */
    public function testDeleteNonExistingTenant()
    {
        // 模拟Cache门面
        Cache::shouldReceive('get')
            ->once()
            ->with('queue:tenants', ['default'])
            ->andReturn(['default']);

        $tenantManager = TenantManager::getInstance();
        $result = $tenantManager->deleteTenant('non_existing_tenant');

        $this->assertFalse($result);
    }

    /**
     * 测试获取所有租户
     */
    public function testGetAllTenants()
    {
        // 模拟Cache门面
        Cache::shouldReceive('get')
            ->once()
            ->with('queue:tenants', ['default'])
            ->andReturn(['default', 'tenant1', 'tenant2']);

        $tenantManager = TenantManager::getInstance();
        $tenants = $tenantManager->getAllTenants();

        $this->assertEquals(['default', 'tenant1', 'tenant2'], $tenants);
    }

    /**
     * 测试设置和获取当前租户
     */
    public function testSetAndGetCurrentTenant()
    {
        $tenantManager = TenantManager::getInstance();
        $tenantManager->setCurrentTenant('test_tenant');

        $this->assertEquals('test_tenant', $tenantManager->getCurrentTenantId());
    }

    /**
     * 测试设置和获取租户配置
     */
    public function testSetAndGetTenantConfig()
    {
        // 模拟Cache门面
        Cache::shouldReceive('set')
            ->once()
            ->with('queue:tenant:config:test_tenant', ['key1' => 'value1', 'key2' => 'value2'])
            ->andReturn(true);

        Cache::shouldReceive('get')
            ->once()
            ->with('queue:tenant:config:test_tenant', [])
            ->andReturn(['key1' => 'value1', 'key2' => 'value2']);

        $tenantManager = TenantManager::getInstance();
        $result = $tenantManager->setTenantConfig('test_tenant', ['key1' => 'value1', 'key2' => 'value2']);

        $this->assertTrue($result);

        $config = $tenantManager->getTenantConfig('test_tenant');
        $this->assertEquals(['key1' => 'value1', 'key2' => 'value2'], $config);

        $value = $tenantManager->getTenantConfigItem('test_tenant', 'key1', null);
        $this->assertEquals('value1', $value);

        $defaultValue = $tenantManager->getTenantConfigItem('test_tenant', 'non_existing_key', 'default_value');
        $this->assertEquals('default_value', $defaultValue);
    }

    /**
     * 测试设置租户特定配置项
     */
    public function testSetTenantConfigItem()
    {
        // 模拟Cache门面
        Cache::shouldReceive('get')
            ->once()
            ->with('queue:tenant:config:test_tenant', [])
            ->andReturn(['existing_key' => 'existing_value']);

        Cache::shouldReceive('set')
            ->once()
            ->with('queue:tenant:config:test_tenant', ['existing_key' => 'existing_value', 'new_key' => 'new_value'])
            ->andReturn(true);

        $tenantManager = TenantManager::getInstance();
        $result = $tenantManager->setTenantConfigItem('test_tenant', 'new_key', 'new_value');

        $this->assertTrue($result);
    }

    /**
     * 测试生成租户特定的主题名称
     */
    public function testGetTenantSpecificTopic()
    {
        $tenantManager = TenantManager::getInstance();
        $topic = $tenantManager->getTenantSpecificTopic('test_tenant', 'original_topic');

        $this->assertEquals('test_tenant.original_topic', $topic);
    }

    /**
     * 测试检查租户是否存在
     */
    public function testTenantExists()
    {
        // 模拟Cache门面
        Cache::shouldReceive('get')
            ->once()
            ->with('queue:tenants', ['default'])
            ->andReturn(['default', 'existing_tenant']);

        $tenantManager = TenantManager::getInstance();

        $this->assertTrue($tenantManager->tenantExists('existing_tenant'));
        $this->assertFalse($tenantManager->tenantExists('non_existing_tenant'));
    }
}
