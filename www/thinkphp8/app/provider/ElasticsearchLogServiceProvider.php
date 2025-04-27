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

                // 尝试连接ES服务器并检查状态
                $client = $logger->getClient();
                $health = $client->cluster()->health();
                
                // 确保索引模板存在
                $templateName = ($config['index_prefix'] ?? 'logs') . '_template';
                $templateExists = false;
                
                try {
                    $templateExists = $client->indices()->existsTemplate(['name' => $templateName]);
                } catch (\Throwable $e) {
                    // 模板可能不存在或API不支持
                    $this->app->log->channel('file')->warning('检查Elasticsearch索引模板失败: ' . $e->getMessage());
                }
                
                // 创建或更新索引模板
                $createResult = $logger->createIndexTemplate();
                
                // 记录详细日志
                $logInfo = sprintf(
                    '基于Monolog的Elasticsearch日志系统初始化: 集群[%s]状态[%s]节点数[%s], 索引模板[%s]%s', 
                    $health['cluster_name'] ?? '未知',
                    $health['status'] ?? '未知',
                    $health['number_of_nodes'] ?? '未知',
                    $templateName,
                    ($createResult ? '创建成功' : '创建失败')
                );
                
                $this->app->log->channel('file')->info($logInfo);
                
                // 确保索引存在，尝试创建当天的索引
                $todayIndex = strtolower(($config['index_prefix'] ?? 'logs') . '-' . date('Y.m.d'));
                $indexExists = $client->indices()->exists(['index' => $todayIndex]);
                
                if (!$indexExists) {
                    // 手动创建索引
                    $createParams = [
                        'index' => $todayIndex,
                        'body'  => [
                            'settings' => [
                                'number_of_shards'   => 1,
                                'number_of_replicas' => 0  // 开发环境可以设置为0
                            ]
                        ]
                    ];
                    
                    $response = $client->indices()->create($createParams);
                    $this->app->log->channel('file')->info(
                        sprintf('手动创建Elasticsearch索引[%s]%s', 
                            $todayIndex, 
                            (isset($response['acknowledged']) && $response['acknowledged'] ? '成功' : '失败')
                        )
                    );
                }
            } catch (\Throwable $e) {
                // 记录初始化失败日志，包含详细错误信息
                $this->app->log->channel('file')->error(sprintf(
                    '基于Monolog的Elasticsearch日志系统初始化失败: %s%s详细错误: %s', 
                    $e->getMessage(),
                    PHP_EOL,
                    $e->getTraceAsString()
                ));
            }
        }
    }
}