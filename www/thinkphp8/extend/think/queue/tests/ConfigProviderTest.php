<?php

declare(strict_types=1);

namespace think\queue\tests;

use PHPUnit\Framework\TestCase;
use think\queue\config\RedisConfigProvider;
use think\queue\config\ConsulConfigProvider;
use think\queue\config\EtcdConfigProvider;
use think\queue\config\ZookeeperConfigProvider;
use think\queue\config\ConfigProviderFactory;
use think\facade\Cache;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

/**
 * 配置提供者单元测试
 */
class ConfigProviderTest extends TestCase
{
    /**
     * 测试Redis配置提供者
     */
    public function testRedisConfigProvider()
    {
        // 模拟Cache门面
        Cache::shouldReceive('get')
            ->once()
            ->with('queue:config:test:test_key', null)
            ->andReturn('test_value');

        Cache::shouldReceive('set')
            ->once()
            ->with('queue:config:test:new_key', 'new_value')
            ->andReturn(true);

        // 创建Redis配置提供者
        $provider = new RedisConfigProvider('test');

        // 测试获取配置
        $this->assertEquals('test_value', $provider->get('test_key'));

        // 测试设置配置
        $this->assertTrue($provider->set('new_key', 'new_value'));
    }

    /**
     * 测试Consul配置提供者
     */
    public function testConsulConfigProvider()
    {
        // 创建模拟HTTP响应
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                [
                    'Key' => 'queue/config/test/test_key',
                    'Value' => base64_encode(json_encode('test_value'))
                ]
            ])),
            new Response(200, [], '{}')
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);

        // 创建Consul配置提供者
        $provider = new ConsulConfigProvider('http://localhost:8500', 'test');
        $provider->setClient($client); // 注入模拟客户端

        // 测试加载配置
        $provider->forceRefresh();

        // 测试获取配置
        $this->assertEquals('test_value', $provider->get('test_key'));

        // 测试设置配置
        $this->assertTrue($provider->set('new_key', 'new_value'));
    }

    /**
     * 测试Etcd配置提供者
     */
    public function testEtcdConfigProvider()
    {
        // 创建模拟HTTP响应
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'kvs' => [
                    [
                        'key' => base64_encode('queue/config/test/test_key'),
                        'value' => base64_encode(json_encode('test_value'))
                    ]
                ]
            ])),
            new Response(200, [], '{}')
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);

        // 创建Etcd配置提供者
        $provider = new EtcdConfigProvider('http://localhost:2379', 'test');
        $provider->setClient($client); // 注入模拟客户端

        // 测试加载配置
        $provider->forceRefresh();

        // 测试获取配置
        $this->assertEquals('test_value', $provider->get('test_key'));

        // 测试设置配置
        $this->assertTrue($provider->set('new_key', 'new_value'));
    }

    /**
     * 测试ZooKeeper配置提供者
     */
    public function testZookeeperConfigProvider()
    {
        // 创建模拟ZooKeeper客户端
        $mockZk = $this->createMock(\ZooKeeper::class);

        // 设置模拟方法
        $mockZk->method('exists')
            ->willReturn(true);

        $mockZk->method('get')
            ->willReturn(json_encode('test_value'));

        $mockZk->method('getChildren')
            ->willReturn([]);

        $mockZk->method('create')
            ->willReturn(true);

        $mockZk->method('set')
            ->willReturn(true);

        $mockZk->method('delete')
            ->willReturn(true);

        // 创建ZooKeeper配置提供者
        $provider = new ZookeeperConfigProvider('localhost:2181', 'test');
        $provider->setClient($mockZk); // 注入模拟客户端

        // 测试加载配置
        $provider->forceRefresh();

        // 测试设置配置
        $this->assertTrue($provider->set('new_key', 'new_value'));

        // 测试获取配置
        $this->assertEquals('test_value', $provider->get('test_key'));
    }

    /**
     * 测试配置提供者工厂
     */
    public function testConfigProviderFactory()
    {
        // 测试创建Redis配置提供者
        $redisProvider = ConfigProviderFactory::create('redis', 'test');
        $this->assertInstanceOf(RedisConfigProvider::class, $redisProvider);

        // 测试创建Consul配置提供者
        $consulProvider = ConfigProviderFactory::create('consul', 'test');
        $this->assertInstanceOf(ConsulConfigProvider::class, $consulProvider);

        // 测试创建Etcd配置提供者
        $etcdProvider = ConfigProviderFactory::create('etcd', 'test');
        $this->assertInstanceOf(EtcdConfigProvider::class, $etcdProvider);

        // 测试创建ZooKeeper配置提供者
        $zkProvider = ConfigProviderFactory::create('zookeeper', 'test');
        $this->assertInstanceOf(ZookeeperConfigProvider::class, $zkProvider);

        // 测试无效类型
        $this->expectException(\InvalidArgumentException::class);
        ConfigProviderFactory::create('invalid', 'test');
    }
}
