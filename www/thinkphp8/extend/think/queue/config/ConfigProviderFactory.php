<?php

declare(strict_types=1);

namespace think\queue\config;

use think\facade\Config;
use think\queue\log\StructuredLogger;

/**
 * 配置提供者工厂
 * 用于创建不同类型的配置提供者实例
 */
class ConfigProviderFactory
{
    /**
     * 创建配置提供者实例
     * 
     * @param string $type 配置提供者类型（redis, consul, etcd, zookeeper）
     * @param string $tenantId 租户ID
     * @return ConfigProviderInterface 配置提供者实例
     * @throws \InvalidArgumentException 如果提供的类型无效
     */
    public static function create(string $type = 'redis', string $tenantId = 'default'): ConfigProviderInterface
    {
        $logger = StructuredLogger::getInstance($tenantId);

        switch ($type) {
            case 'redis':
                $logger->info('Creating Redis config provider', ['tenant_id' => $tenantId]);
                return new RedisConfigProvider($tenantId);

            case 'consul':
                $consulConfig = Config::get('queue.config_provider.consul', [
                    'api_url' => 'http://localhost:8500'
                ]);

                $logger->info('Creating Consul config provider', [
                    'tenant_id' => $tenantId,
                    'api_url' => $consulConfig['api_url']
                ]);

                return new ConsulConfigProvider($consulConfig['api_url'], $tenantId);

            case 'etcd':
                $etcdConfig = Config::get('queue.config_provider.etcd', [
                    'api_url' => 'http://localhost:2379'
                ]);

                $logger->info('Creating Etcd config provider', [
                    'tenant_id' => $tenantId,
                    'api_url' => $etcdConfig['api_url']
                ]);

                return new EtcdConfigProvider($etcdConfig['api_url'], $tenantId);

            case 'zookeeper':
                $zkConfig = Config::get('queue.config_provider.zookeeper', [
                    'connection_string' => 'localhost:2181'
                ]);

                $logger->info('Creating ZooKeeper config provider', [
                    'tenant_id' => $tenantId,
                    'connection_string' => $zkConfig['connection_string']
                ]);

                return new ZookeeperConfigProvider($zkConfig['connection_string'], $tenantId);

            default:
                $logger->error('Invalid config provider type', ['type' => $type]);
                throw new \InvalidArgumentException("Invalid config provider type: {$type}");
        }
    }
}
