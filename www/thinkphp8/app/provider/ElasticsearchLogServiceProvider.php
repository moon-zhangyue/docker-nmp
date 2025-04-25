<?php
declare(strict_types=1);

namespace app\provider;

use think\App;
use think\Service;
use think\log\driver\MonologElasticsearch;

/**
 * Elasticsearch日志服务提供者
 * 用于初始化Elasticsearch索引模板，确保日志字段映射正确
 */
class ElasticsearchLogServiceProvider extends Service
{
    /**
     * 注册服务
     *
     * @return void
     */
    public function register()
    {
        // 注册服务逻辑
    }

    /**
     * 服务启动
     *
     * @return void
     */
    public function boot()
    {
        // 获取日志配置
        $config = $this->app->config->get('log.channels.elasticsearch', []);

        // 如果启用了Elasticsearch日志驱动，则创建索引模板
        if ($this->app->config->get('log.default') === 'elasticsearch' && !empty($config)) {
            try {
                // 创建基于Monolog的Elasticsearch日志驱动实例
                $logger = new MonologElasticsearch($this->app, $config);

                // 创建索引模板
                $logger->createIndexTemplate();

                // 记录初始化成功日志
                $this->app->log->channel('file')->info('基于Monolog的Elasticsearch日志索引模板初始化成功');
            } catch (\Throwable $e) {
                // 记录初始化失败日志
                $this->app->log->channel('file')->error('基于Monolog的Elasticsearch日志索引模板初始化失败: ' . $e->getMessage());
            }
        }
    }
}