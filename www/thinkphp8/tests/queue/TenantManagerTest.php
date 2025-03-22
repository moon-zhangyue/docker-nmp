<?php
declare(strict_types=1);

namespace tests\queue;

use PHPUnit\Framework\TestCase;
use think\queue\tenant\TenantManager;
use think\facade\Cache;

class TenantManagerTest extends TestCase
{
    protected $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = TenantManager::getInstance();
    }

    public function testGetInstance()
    {
        $instance = TenantManager::getInstance();
        $this->assertInstanceOf(TenantManager::class, $instance);
        $this->assertSame($instance, $this->manager);
    }

    public function testCreateTenant()
    {
        $tenantId = 'test_tenant_' . uniqid();
        $config = [
            'redis' => [
                'host' => 'redis',
                'port' => 6379,
            ],
            'kafka' => [
                'brokers' => ['kafka:9092'],
            ],
        ];

        $result = $this->manager->createTenant($tenantId, $config);
        $this->assertTrue($result);

        $tenantConfig = Cache::get("tenant:{$tenantId}:config");
        $this->assertEquals($config, $tenantConfig);
    }

    public function testCreateExistingTenant()
    {
        $tenantId = 'test_tenant_' . uniqid();
        $config = [
            'redis' => [
                'host' => 'redis',
                'port' => 6379,
            ],
        ];

        $this->manager->createTenant($tenantId, $config);
        $result = $this->manager->createTenant($tenantId, $config);
        $this->assertFalse($result);
    }

    public function testDeleteTenant()
    {
        $tenantId = 'test_tenant_' . uniqid();
        $config = [
            'redis' => [
                'host' => 'redis',
                'port' => 6379,
            ],
        ];

        $this->manager->createTenant($tenantId, $config);
        $result = $this->manager->deleteTenant($tenantId);
        $this->assertTrue($result);

        $tenantConfig = Cache::get("tenant:{$tenantId}:config");
        $this->assertNull($tenantConfig);
    }

    public function testDeleteNonExistingTenant()
    {
        $tenantId = 'non_existing_tenant';
        $result = $this->manager->deleteTenant($tenantId);
        $this->assertFalse($result);
    }

    public function testGetAllTenants()
    {
        $tenantIds = [
            'test_tenant_1_' . uniqid(),
            'test_tenant_2_' . uniqid(),
        ];

        foreach ($tenantIds as $tenantId) {
            $config = [
                'redis' => [
                    'host' => 'redis',
                    'port' => 6379,
                ],
            ];
            $this->manager->createTenant($tenantId, $config);
        }

        $tenants = $this->manager->getAllTenants();
        $this->assertCount(2, $tenants);
        $this->assertContains($tenantIds[0], $tenants);
        $this->assertContains($tenantIds[1], $tenants);
    }

    public function testSetAndGetCurrentTenant()
    {
        $tenantId = 'test_tenant_' . uniqid();
        $config = [
            'redis' => [
                'host' => 'redis',
                'port' => 6379,
            ],
        ];

        $this->manager->createTenant($tenantId, $config);
        $this->manager->setCurrentTenant($tenantId);
        $this->assertEquals($tenantId, $this->manager->getCurrentTenant());
    }

    public function testSetAndGetTenantConfig()
    {
        $tenantId = 'test_tenant_' . uniqid();
        $config = [
            'redis' => [
                'host' => 'redis',
                'port' => 6379,
            ],
            'kafka' => [
                'brokers' => ['kafka:9092'],
            ],
        ];

        $this->manager->createTenant($tenantId, $config);
        $this->manager->setCurrentTenant($tenantId);
        $this->assertEquals($config, $this->manager->getTenantConfig());
    }

    public function testSetTenantConfigItem()
    {
        $tenantId = 'test_tenant_' . uniqid();
        $config = [
            'redis' => [
                'host' => 'redis',
                'port' => 6379,
            ],
        ];

        $this->manager->createTenant($tenantId, $config);
        $this->manager->setCurrentTenant($tenantId);

        $newConfig = [
            'redis' => [
                'host' => 'redis2',
                'port' => 6380,
            ],
        ];

        $this->manager->setTenantConfigItem('redis', $newConfig['redis']);
        $this->assertEquals($newConfig['redis'], $this->manager->getTenantConfigItem('redis'));
    }

    public function testGetTenantSpecificTopic()
    {
        $tenantId = 'test_tenant_' . uniqid();
        $config = [
            'redis' => [
                'host' => 'redis',
                'port' => 6379,
            ],
        ];

        $this->manager->createTenant($tenantId, $config);
        $this->manager->setCurrentTenant($tenantId);

        $topic = 'test_topic';
        $tenantTopic = $this->manager->getTenantSpecificTopic($topic);
        $this->assertEquals("{$tenantId}:{$topic}", $tenantTopic);
    }
} 