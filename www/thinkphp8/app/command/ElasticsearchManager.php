<?php
declare(strict_types=1);

namespace app\command;

use Elasticsearch\ClientBuilder;
use Elasticsearch\Client;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\input\Option;
use think\facade\Config;

/**
 * Elasticsearch索引管理命令
 */
class ElasticsearchManager extends Command
{
    /**
     * Elasticsearch客户端
     * @var Client
     */
    protected $client;

    /**
     * 配置指令
     */
    protected function configure()
    {
        $this->setName('es:manager')
            ->addOption('action', null, Option::VALUE_REQUIRED, '操作类型: test, list, create, delete, clear, view', 'test')
            ->addOption('index', null, Option::VALUE_OPTIONAL, '索引名称')
            ->addOption('level', null, Option::VALUE_OPTIONAL, '日志级别: info, warning, error, etc.')
            ->addOption('limit', null, Option::VALUE_OPTIONAL, '限制条数', 20)
            ->setDescription('Elasticsearch索引管理工具');
    }

    /**
     * 初始化客户端
     */
    protected function initClient()
    {
        $config = Config::get('elasticsearch');

        $builder = ClientBuilder::create()
            ->setHosts($config['hosts'])
            ->setRetries($config['retries'] ?? 2);

        // 设置请求超时和连接超时
        $builder->setConnectionParams([
            'client' => [
                'timeout'         => $config['timeout'] ?? 10,
                'connect_timeout' => $config['connect_timeout'] ?? 5
            ]
        ]);

        // 设置身份验证
        if (!empty($config['auth']) && isset($config['auth'][0]) && isset($config['auth'][1])) {
            $builder->setBasicAuthentication($config['auth'][0], $config['auth'][1]);
        }

        // 设置API Key (如果提供)
        if (!empty($config['apiKey'])) {
            $builder->setApiKey($config['apiKey'], '');
        }

        // 设置SSL相关配置
        if (!empty($config['ssl']) && $config['ssl']['enabled']) {
            $sslConfig = [];

            if (isset($config['ssl']['verify'])) {
                $sslConfig['verify'] = $config['ssl']['verify'];
            }

            if (!empty($config['ssl']['cert'])) {
                $sslConfig['cert'] = $config['ssl']['cert'];
            }

            if (!empty($config['ssl']['ca'])) {
                $sslConfig['ca'] = $config['ssl']['ca'];
            }

            $builder->setSSLVerification($sslConfig);
        }

        if (!empty($config['debug']) && $config['debug']) {
            $builder->setLogger(app()->log->channel('file'));
        }

        $this->client = $builder->build();
    }

    /**
     * 执行命令
     * @param Input $input
     * @param Output $output
     * @return int
     */
    protected function execute(Input $input, Output $output)
    {
        $this->initClient();

        $action = $input->getOption('action');
        $index  = $input->getOption('index');

        if (empty($index) && in_array($action, ['create', 'delete', 'view'])) {
            $output->error('当执行 create, delete 或 view 操作时，必须指定索引名称');
            return 1;
        }

        switch ($action) {
            case 'test':
                return $this->testConnection($output);
            case 'list':
                return $this->listIndices($output);
            case 'create':
                return $this->createIndex($index, $output);
            case 'delete':
                return $this->deleteIndex($index, $output);
            case 'clear':
                return $this->clearOldIndices($output, $input);
            case 'view':
                return $this->viewLogs($index, $input, $output);
            default:
                $output->error('未知操作类型: ' . $action);
                return 1;
        }
    }

    /**
     * 测试与Elasticsearch的连接
     * @param Output $output
     * @return int
     */
    protected function testConnection(Output $output)
    {
        try {
            $info        = $this->client->info();
            $version     = $info['version']['number'] ?? 'unknown';
            $clusterName = $info['cluster_name'] ?? 'unknown';

            $output->info('Elasticsearch连接成功!');
            $output->info("版本: {$version}");
            $output->info("集群名称: {$clusterName}");

            return 0;
        } catch (\Exception $e) {
            $output->error('Elasticsearch连接失败: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * 列出所有索引
     * @param Output $output
     * @return int
     */
    protected function listIndices(Output $output)
    {
        try {
            $indices = $this->client->cat()->indices(['format' => 'json']);
            
            if (empty($indices)) {
                $output->info('没有找到索引');
                return 0;
            }
            
            $output->info('索引列表:');
            
            // 输出表头
            $output->writeln("索引名称\t\t文档数\t\t大小\t\t状态");
            $output->writeln(str_repeat('-', 80));
            
            // 输出每一行索引信息
            foreach ($indices as $index) {
                $indexName = $index['index'] ?? 'N/A';
                $docsCount = $index['docs.count'] ?? '0';
                $storeSize = $index['store.size'] ?? '0';
                $health = $index['health'] ?? 'unknown';
                
                $output->writeln(sprintf("%-30s\t%-10s\t%-10s\t%s", 
                    $indexName, 
                    $docsCount, 
                    $storeSize, 
                    $health
                ));
            }
            
            return 0;
        } catch (\Exception $e) {
            $output->error('获取索引列表失败: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * 创建索引
     * @param string $index 索引名称
     * @param Output $output
     * @return int
     */
    protected function createIndex(string $index, Output $output)
    {
        try {
            $config = Config::get('elasticsearch');

            // 检查索引是否已存在
            $exists = $this->client->indices()->exists(['index' => $index]);

            if ($exists) {
                $output->warning("索引 '{$index}' 已存在。");
                return 0;
            }

            // 创建索引
            $response = $this->client->indices()->create([
                'index' => $index,
                'body'  => [
                    'settings' => [
                        'number_of_shards'   => $config['number_of_shards'] ?? 3,
                        'number_of_replicas' => $config['number_of_replicas'] ?? 1,
                    ],
                    'mappings' => [
                        'properties' => [
                            '@timestamp' => ['type' => 'date'],
                            'level'      => ['type' => 'keyword'],
                            'channel'    => ['type' => 'keyword'],
                            'message'    => ['type' => 'text'],
                            'context'    => ['type' => 'object', 'dynamic' => true],
                            'extra'      => ['type' => 'object', 'dynamic' => true],
                            'datetime'   => ['type' => 'date'],
                            'app_name'   => ['type' => 'keyword'],
                            'host'       => ['type' => 'keyword'],
                            'request_id' => ['type' => 'keyword'],
                            'trace_id'   => ['type' => 'keyword'],
                            'ip'         => ['type' => 'ip']
                        ]
                    ]
                ]
            ]);

            $output->info("索引 '{$index}' 创建成功.");
            return 0;
        } catch (\Exception $e) {
            $output->error("创建索引 '{$index}' 失败: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * 删除索引
     * @param string $index 索引名称
     * @param Output $output
     * @return int
     */
    protected function deleteIndex(string $index, Output $output)
    {
        try {
            // 检查索引是否存在
            $exists = $this->client->indices()->exists(['index' => $index]);

            if (!$exists) {
                $output->warning("索引 '{$index}' 不存在.");
                return 0;
            }

            // 删除索引
            $this->client->indices()->delete(['index' => $index]);

            $output->info("索引 '{$index}' 已删除.");
            return 0;
        } catch (\Exception $e) {
            $output->error("删除索引 '{$index}' 失败: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * 清理旧索引
     * @param Output $output
     * @param Input $input 输入对象
     * @return int
     */
    protected function clearOldIndices(Output $output, Input $input = null)
    {
        try {
            $config = Config::get('elasticsearch');
            $prefix = $config['index_prefix'] ?? 'logs';
            
            // 获取所有索引
            $indices = $this->client->cat()->indices(['format' => 'json']);
            
            if (empty($indices)) {
                $output->info('没有找到索引');
                return 0;
            }
            
            // 过滤出匹配前缀的日期索引
            $pattern     = "/{$prefix}-(\d{4}\.\d{2}\.\d{2})/";
            $dateIndices = [];
            
            foreach ($indices as $index) {
                $indexName = $index['index'] ?? '';
                if (preg_match($pattern, $indexName, $matches)) {
                    $dateIndices[$indexName] = $matches[1];
                }
            }
            
            if (empty($dateIndices)) {
                $output->info("没有找到匹配前缀 '{$prefix}' 的日期索引");
                return 0;
            }
            
            // 保留30天内的索引
            $cutoffDate         = date('Y.m.d', strtotime('-30 days'));
            $indicesForDeletion = [];
            
            foreach ($dateIndices as $indexName => $indexDate) {
                if ($indexDate < $cutoffDate) {
                    $indicesForDeletion[] = $indexName;
                }
            }
            
            if (empty($indicesForDeletion)) {
                $output->info('没有需要删除的旧索引');
                return 0;
            }
            
            $output->info("将删除以下旧索引:");
            foreach ($indicesForDeletion as $index) {
                $output->writeln(" - {$index}");
            }
            
            // 使用简单的确认方法（避免使用confirm方法）
            $output->writeln("确认删除上述索引？[y/N]");
            $handle = fopen("php://stdin", "r");
            $line = trim(fgets($handle));
            fclose($handle);
            
            if (strtolower($line) === 'y') {
                foreach ($indicesForDeletion as $index) {
                    try {
                        $this->client->indices()->delete(['index' => $index]);
                        $output->info("- 已删除 {$index}");
                    } catch (\Exception $e) {
                        $output->error("- 删除 {$index} 失败: " . $e->getMessage());
                    }
                }
                
                $output->info('旧索引清理完成.');
            } else {
                $output->info('操作已取消.');
            }
            
            return 0;
        } catch (\Exception $e) {
            $output->error('清理旧索引失败: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * 查看日志内容
     * @param string $index 索引名称
     * @param Input $input
     * @param Output $output
     * @return int
     */
    protected function viewLogs(string $index, Input $input, Output $output)
    {
        try {
            // 检查索引是否存在
            $exists = $this->client->indices()->exists(['index' => $index]);
            
            if (!$exists) {
                $output->error("索引 '{$index}' 不存在");
                return 1;
            }
            
            $level = $input->getOption('level');
            $limit = (int)$input->getOption('limit');
            
            // 构建查询
            $params = [
                'index' => $index,
                'size' => $limit,
                'sort' => [
                    '@timestamp' => ['order' => 'desc']
                ]
            ];
            
            // 如果指定了日志级别，添加过滤条件
            if (!empty($level)) {
                $params['body'] = [
                    'query' => [
                        'term' => [
                            'level' => $level
                        ]
                    ]
                ];
            }
            
            // 执行查询
            $response = $this->client->search($params);
            
            $hits = $response['hits']['hits'] ?? [];
            
            if (empty($hits)) {
                $output->info("没有找到符合条件的日志记录");
                return 0;
            }
            
            $output->info("共找到 {$response['hits']['total']['value']} 条日志记录，显示前 {$limit} 条:");
            $output->writeln(str_repeat('-', 100));
            
            foreach ($hits as $hit) {
                $source = $hit['_source'];
                $timestamp = $source['@timestamp'] ?? 'N/A';
                $level = $source['level'] ?? 'N/A';
                $message = $source['message'] ?? 'N/A';
                
                // 格式化输出
                $output->writeln("[{$timestamp}] [{$level}] {$message}");
                
                // 如果有上下文信息，也输出
                if (isset($source['context']) && is_array($source['context'])) {
                    $output->writeln("上下文信息:");
                    foreach ($source['context'] as $key => $value) {
                        if (is_array($value)) {
                            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                        }
                        $output->writeln("  {$key}: {$value}");
                    }
                }
                
                $output->writeln(str_repeat('-', 100));
            }
            
            return 0;
        } catch (\Exception $e) {
            $output->error("查看日志失败: " . $e->getMessage());
            return 1;
        }
    }
}