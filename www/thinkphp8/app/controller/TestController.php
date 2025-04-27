<?php

declare(strict_types=1);

namespace app\controller;

use think\facade\Log;
use think\Request;
use app\BaseController;
use think\log\driver\MonologElasticsearch;

class TestController extends BaseController
{

    /**
     * 测试Elasticsearch日志记录
     *
     * @return \think\Response
     */
    public function testEsLog(): \think\Response
    {
        try {
            // 获取ES日志配置
            $config      = config('log.channels.elasticsearch', []);
            $indexPrefix = $config['index_prefix'] ?? 'logs';
            // 创建ES日志驱动实例以便直接操作
            $logger = new MonologElasticsearch(app(), $config);
            $client = $logger->getClient();
            // 确保索引模板存在
            $logger->createIndexTemplate();
            // 确保当天索引存在，严格使用配置的前缀
            $todayIndex  = strtolower($indexPrefix . '-' . date('Y.m.d'));
            $indexExists = $client->indices()->exists(['index' => $todayIndex]);
            if (!$indexExists) {
                // 手动创建索引
                $createParams = [
                    'index' => $todayIndex,
                    'body'  => [
                        'settings' => [
                            'number_of_shards'   => 1,
                            'number_of_replicas' => 0
                        ]
                    ]
                ];
                $client->indices()->create($createParams);
            }

            // 确保所有值都是标量类型（字符串、数字等），不是数组
            Log::info('这是一条 Info 级别的测试日志。{user_id},{order_id}', ['user_id' => 123, 'order_id' => 'SN20231027']);
            Log::warning('这是一条 Warning 级别的测试日志。', ['context' => '一些警告信息']);
            Log::error('这是一条 Error 级别的测试日志。', ['exception' => '模拟异常信息']);

            // 对于数组类型的值，先转换为JSON字符串
            $dataArray = ['key' => 'value', 'nested' => ['a' => 1, 'b' => 2]];
            Log::debug('这是一条 Debug 级别的测试日志。{data}', ['data' => json_encode($dataArray, JSON_UNESCAPED_UNICODE)]);

            // 记录一个在 apart_level 中定义的级别，例如 error
            Log::log('error', '这是一条独立的 Error 级别日志。{details}', ['details' => '需要独立存储的错误详情']);

            return json([
                'code' => 200,
                'msg'  => '日志已记录，请检查 Elasticsearch 或 Kibana。',
                'data' => [
                    'index'   => $todayIndex,
                    'indices' => array_keys($client->indices()->get(['index' => ($config['index_prefix'] ?? 'logs') . '*']))
                ]
            ]);
        } catch (\Throwable $e) {
            return json([
                'code'  => 500,
                'msg'   => '记录日志失败: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * 检查Elasticsearch连接状态
     *
     * @return \think\Response
     */
    public function checkEsConnection(): \think\Response
    {
        $result = [
            'code' => 500,
            'msg'  => '连接失败',
            'data' => []
        ];

        try {
            // 获取ES日志配置
            $config = config('log.channels.elasticsearch', []);
            if (empty($config)) {
                return json(['code' => 500, 'msg' => '未找到Elasticsearch日志配置']);
            }

            // 创建ES日志驱动实例
            $logger = new MonologElasticsearch(app(), $config);
            $client = $logger->getClient();

            // 检查集群健康状态
            $health = $client->cluster()->health();

            // 检查索引
            $indexPattern = ($config['index_prefix'] ?? 'logs') . '*';
            $indices      = [];

            try {
                $indicesResponse = $client->indices()->get(['index' => $indexPattern]);
                if (!empty($indicesResponse)) {
                    foreach (array_keys($indicesResponse) as $indexName) {
                        // 获取文档数量
                        $stats     = $client->indices()->stats(['index' => $indexName]);
                        $docCount  = $stats['indices'][$indexName]['primaries']['docs']['count'] ?? 0;
                        $indices[] = [
                            'name'      => $indexName,
                            'doc_count' => $docCount
                        ];
                    }
                }
            } catch (\Throwable $e) {
                // 索引可能不存在，这不是致命错误
            }

            // 写入测试日志
            $testMessage   = '这是一条通过API接口写入的测试日志 - ' . date('Y-m-d H:i:s');
            $monologLogger = $logger->getLogger();
            $monologLogger->info($testMessage, ['source' => 'api_test']);

            $result = [
                'code' => 200,
                'msg'  => '连接成功',
                'data' => [
                    'cluster'  => [
                        'name'   => $health['cluster_name'] ?? '未知',
                        'status' => $health['status'] ?? '未知',
                        'nodes'  => $health['number_of_nodes'] ?? '未知'
                    ],
                    'indices'  => $indices,
                    'test_log' => [
                        'message' => $testMessage,
                        'time'    => date('Y-m-d H:i:s'),
                        'index'   => strtolower(($config['index_prefix'] ?? 'logs') . '-' . date('Y.m.d'))
                    ],
                    'config'   => [
                        'hosts'        => $config['hosts'] ?? ['未配置'],
                        'index_prefix' => $config['index_prefix'] ?? '未配置',
                        'auth_type'    => !empty($config['username']) ? '用户名密码' : (!empty($config['apiKey']) ? 'API密钥' : '无认证')
                    ]
                ]
            ];
        } catch (\Throwable $e) {
            $result = [
                'code' => 500,
                'msg'  => '连接失败: ' . $e->getMessage(),
                'data' => []
            ];
        }

        return json($result);
    }
}
