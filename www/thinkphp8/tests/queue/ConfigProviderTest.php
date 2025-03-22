<?php
declare(strict_types=1);

namespace tests\queue;

use PHPUnit\Framework\TestCase;
use think\queue\config\RedisConfigProvider;
use think\queue\config\ConsulConfigProvider;
use think\queue\config\EtcdConfigProvider;
use think\queue\config\ZookeeperConfigProvider;
use think\queue\config\ConfigProviderFactory;

class ConfigProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testRedisConfigProvider()
    {
        $config = [
            'host' => 'redis',
            'port' => 6379,
            'password' => '',
            'select' => 0,
            'timeout' => 0,
            'persistent' => false,
        ];
        
        $provider = new RedisConfigProvider('test', $config);
        $this->assertInstanceOf(RedisConfigProvider::class, $provider);
        
        // 测试设置配置
        $this->assertTrue($provider->set('test_key', 'test_value'));
        
        // 测试获取配置
        $this->assertEquals('test_value', $provider->get('test_key'));
        
        // 测试删除配置
        $this->assertTrue($provider->delete('test_key'));
        $this->assertNull($provider->get('test_key'));
    }

    public function testConsulConfigProvider()
    {
        $config = [
            'host' => 'consul',
            'port' => 8500,
            'token' => '',
        ];
        
        $provider = new ConsulConfigProvider('http://consul:8500', 'test');
        $this->assertInstanceOf(ConsulConfigProvider::class, $provider);
        
        // 测试设置配置
        $this->assertTrue($provider->set('test_key', 'test_value'));
        
        // 测试获取配置
        $this->assertEquals('test_value', $provider->get('test_key'));
        
        // 测试删除配置
        $this->assertTrue($provider->delete('test_key'));
        $this->assertNull($provider->get('test_key'));
    }

    public function testEtcdConfigProvider()
    {
        $config = [
            'host' => 'etcd',
            'port' => 2379,
            'username' => '',
            'password' => '',
        ];
        
        $provider = new EtcdConfigProvider('http://etcd:2379', 'test');
        $this->assertInstanceOf(EtcdConfigProvider::class, $provider);
        
        // 测试设置配置
        $this->assertTrue($provider->set('test_key', 'test_value'));
        
        // 测试获取配置
        $this->assertEquals('test_value', $provider->get('test_key'));
        
        // 测试删除配置
        $this->assertTrue($provider->delete('test_key'));
        $this->assertNull($provider->get('test_key'));
    }

    public function testZookeeperConfigProvider()
    {
        $config = [
            'host' => 'zookeeper',
            'port' => 2181,
            'timeout' => 10000,
        ];
        
        $provider = new ZookeeperConfigProvider('zookeeper:2181', 'test');
        $this->assertInstanceOf(ZookeeperConfigProvider::class, $provider);
        
        // 测试设置配置
        $this->assertTrue($provider->set('test_key', 'test_value'));
        
        // 测试获取配置
        $this->assertEquals('test_value', $provider->get('test_key'));
        
        // 测试删除配置
        $this->assertTrue($provider->delete('test_key'));
        $this->assertNull($provider->get('test_key'));
    }

    public function testConfigProviderFactory()
    {
        $factory = new ConfigProviderFactory();
        
        // 测试创建 Redis 配置提供者
        $redisProvider = $factory->create('redis', [
            'host' => 'redis',
            'port' => 6379,
        ]);
        $this->assertInstanceOf(RedisConfigProvider::class, $redisProvider);
        
        // 测试创建 Consul 配置提供者
        $consulProvider = $factory->create('consul', [
            'host' => 'consul',
            'port' => 8500,
        ]);
        $this->assertInstanceOf(ConsulConfigProvider::class, $consulProvider);
        
        // 测试创建 Etcd 配置提供者
        $etcdProvider = $factory->create('etcd', [
            'host' => 'etcd',
            'port' => 2379,
        ]);
        $this->assertInstanceOf(EtcdConfigProvider::class, $etcdProvider);
        
        // 测试创建 Zookeeper 配置提供者
        $zkProvider = $factory->create('zookeeper', [
            'host' => 'zookeeper',
            'port' => 2181,
        ]);
        $this->assertInstanceOf(ZookeeperConfigProvider::class, $zkProvider);
    }

    public function testRedisConnection()
    {
        $redis = new \Redis();
        $connected = $redis->connect('redis', 6379);
        $this->assertTrue($connected);
    }
} 