<?php

declare(strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Config;
use think\log\driver\MonologElasticsearch as Elasticsearch;
use think\App;

/**
 * Elasticsearch日志查看命令
 * 用于查询ES中存储的日志
 */
class ElasticsearchLogViewer extends Command
{
    /**
     * 应用实例
     * @var App
     */
    protected $app;

    /**
     * 构造函数
     * @param App $app 应用实例
     */
    public function __construct(App $app)
    {
        parent::__construct();
        $this->app = $app;
    }

    /**
     * 配置指令
     */
    protected function configure()
    {
        $this->setName('es-log-view')
            ->addArgument('action', Argument::OPTIONAL, '操作类型: recent, level, search', 'recent')
            ->addOption('level', 'l', Option::VALUE_OPTIONAL, '日志级别', 'error')
            ->addOption('query', 'q', Option::VALUE_OPTIONAL, '搜索关键词', '')
            ->addOption('days', 'd', Option::VALUE_OPTIONAL, '查询最近几天的日志', 7)
            ->addOption('size', 's', Option::VALUE_OPTIONAL, '返回结果数量', 20)
            ->addOption('prefix', 'p', Option::VALUE_OPTIONAL, '索引前缀', 'es_log_')
            ->setDescription('Elasticsearch日志查看工具');
    }

    /**
     * 执行指令
     * @param Input $input 输入对象
     * @param Output $output 输出对象
     * @return int
     */
    protected function execute(Input $input, Output $output)
    {
        $action = $input->getArgument('action');
        $level = $input->getOption('level');
        $query = $input->getOption('query');
        $days = (int) $input->getOption('days');
        $size = (int) $input->getOption('size');
        $prefix = $input->getOption('prefix');

        // 获取ES日志配置
        $config = Config::get('log.channels.elasticsearch', []);
        if (empty($config)) {
            $output->error('未找到Elasticsearch日志配置');
            return 1; // FAILURE
        }

        // 如果指定了索引前缀，则覆盖配置中的前缀
        if ($prefix) {
            $config['index_prefix'] = $prefix;
        }

        try {
            // 创建ES日志驱动实例
            $logger = new Elasticsearch($this->app, $config);

            // 获取ES客户端
            $client = $logger->getClient();
            if (!$client) {
                $output->error('无法获取Elasticsearch客户端');
                return 1; // FAILURE
            }

            // 计算索引范围
            $indices = $this->getIndicesForDateRange($logger, $days);

            switch ($action) {
                case 'recent':
                    return $this->viewRecentLogs($client, $indices, $size, $output);
                case 'level':
                    return $this->viewLogsByLevel($client, $indices, $level, $size, $output);
                case 'search':
                    return $this->searchLogs($client, $indices, $query, $size, $output);
                default:
                    $output->error(sprintf('未知操作: %s', $action));
                    return 1; // FAILURE
            }
        } catch (\Throwable $e) {
            $output->error(sprintf('执行失败: %s', $e->getMessage()));
            return 1; // FAILURE
        }
    }

    /**
     * 获取指定日期范围内的索引
     * @param Elasticsearch $logger ES日志驱动实例
     * @param int $days 天数
     * @return string 索引模式
     */
    protected function getIndicesForDateRange(Elasticsearch $logger, int $days): string
    {
        $indexPattern = $logger->getIndexPattern();

        if ($days <= 0) {
            return $indexPattern . '*';
        }

        // 生成日期范围内的索引名称
        $indices = [];
        for ($i = 0; $i < $days; $i++) {
            $date = date('Y.m.d', strtotime("-{$i} days"));
            $indices[] = $indexPattern . '-' . $date;
        }

        return implode(',', $indices);
    }

    /**
     * 查看最近的日志
     * @param \Elasticsearch\Client $client ES客户端
     * @param string $indices 索引名称
     * @param int $size 返回结果数量
     * @param Output $output 输出对象
     * @return int
     */
    protected function viewRecentLogs($client, string $indices, int $size, Output $output): int
    {
        $output->writeln('正在查询最近的日志...');

        try {
            $params = [
                'index' => $indices,
                'body' => [
                    'sort' => [
                        '@timestamp' => ['order' => 'desc']
                    ],
                    'size' => $size
                ]
            ];

            $response = $client->search($params);
            $this->displaySearchResults($response, $output);

            return 0; // SUCCESS
        } catch (\Throwable $e) {
            $output->error('查询失败: ' . $e->getMessage());
            return 1; // FAILURE
        }
    }

    /**
     * 按日志级别查看日志
     * @param \Elasticsearch\Client $client ES客户端
     * @param string $indices 索引名称
     * @param string $level 日志级别
     * @param int $size 返回结果数量
     * @param Output $output 输出对象
     * @return int
     */
    protected function viewLogsByLevel($client, string $indices, string $level, int $size, Output $output): int
    {
        $output->writeln(sprintf('正在查询级别为 [%s] 的日志...', $level));

        try {
            $params = [
                'index' => $indices,
                'body' => [
                    'query' => [
                        'match' => [
                            'level' => $level
                        ]
                    ],
                    'sort' => [
                        '@timestamp' => ['order' => 'desc']
                    ],
                    'size' => $size
                ]
            ];

            $response = $client->search($params);
            $this->displaySearchResults($response, $output);

            return 0; // SUCCESS
        } catch (\Throwable $e) {
            $output->error('查询失败: ' . $e->getMessage());
            return 1; // FAILURE
        }
    }

    /**
     * 搜索日志
     * @param \Elasticsearch\Client $client ES客户端
     * @param string $indices 索引名称
     * @param string $query 搜索关键词
     * @param int $size 返回结果数量
     * @param Output $output 输出对象
     * @return int
     */
    protected function searchLogs($client, string $indices, string $query, int $size, Output $output): int
    {
        if (empty($query)) {
            $output->error('请提供搜索关键词，使用 --query 或 -q 选项');
            return 1; // FAILURE
        }

        $output->writeln(sprintf('正在搜索包含 [%s] 的日志...', $query));

        try {
            $params = [
                'index' => $indices,
                'body' => [
                    'query' => [
                        'multi_match' => [
                            'query' => $query,
                            'fields' => ['message', 'context.*', 'extra.*']
                        ]
                    ],
                    'sort' => [
                        '@timestamp' => ['order' => 'desc']
                    ],
                    'size' => $size
                ]
            ];

            $response = $client->search($params);
            $this->displaySearchResults($response, $output);

            return 0; // SUCCESS
        } catch (\Throwable $e) {
            $output->error('查询失败: ' . $e->getMessage());
            return 1; // FAILURE
        }
    }

    /**
     * 显示搜索结果
     * @param array $response ES响应
     * @param Output $output 输出对象
     */
    protected function displaySearchResults(array $response, Output $output)
    {
        $hits = $response['hits']['hits'] ?? [];
        $total = $response['hits']['total']['value'] ?? count($hits);

        $output->writeln(sprintf('找到 %d 条日志记录', $total));

        if (empty($hits)) {
            $output->writeln('没有找到匹配的日志记录');
            return;
        }

        foreach ($hits as $index => $hit) {
            $source = $hit['_source'];
            $timestamp = $source['@timestamp'] ?? date('Y-m-d H:i:s');
            $level = strtoupper($source['level'] ?? 'UNKNOWN');
            $message = $source['message'] ?? '(无消息内容)';

            // 格式化输出
            $output->writeln(sprintf("\n[%d] %s", $index + 1, str_repeat('-', 80)));
            $output->writeln(sprintf("<comment>时间:</comment> %s", $timestamp));
            $output->writeln(sprintf("<comment>级别:</comment> %s", $this->formatLevel($level)));
            $output->writeln(sprintf("<comment>消息:</comment> %s", $message));

            // 显示上下文信息
            if (!empty($source['context'])) {
                $output->writeln("<comment>上下文:</comment>");
                $this->displayArray($source['context'], $output, 2);
            }

            // 显示额外信息
            if (!empty($source['extra'])) {
                $output->writeln("<comment>额外信息:</comment>");
                $this->displayArray($source['extra'], $output, 2);
            }
        }
    }

    /**
     * 格式化日志级别
     * @param string $level 日志级别
     * @return string
     */
    protected function formatLevel(string $level): string
    {
        $formats = [
            'DEBUG' => '<fg=cyan>%s</>',
            'INFO' => '<fg=green>%s</>',
            'NOTICE' => '<fg=blue>%s</>',
            'WARNING' => '<fg=yellow>%s</>',
            'ERROR' => '<fg=red>%s</>',
            'CRITICAL' => '<fg=red;options=bold>%s</>',
            'ALERT' => '<fg=red;bg=white>%s</>',
            'EMERGENCY' => '<fg=white;bg=red>%s</>',
        ];

        return isset($formats[$level]) ? sprintf($formats[$level], $level) : $level;
    }

    /**
     * 递归显示数组
     * @param array $array 数组
     * @param Output $output 输出对象
     * @param int $indent 缩进级别
     */
    protected function displayArray(array $array, Output $output, int $indent = 0)
    {
        $indentStr = str_repeat('  ', $indent);

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $output->writeln(sprintf("%s%s:", $indentStr, $key));
                $this->displayArray($value, $output, $indent + 1);
            } else {
                $output->writeln(sprintf("%s%s: %s", $indentStr, $key, $this->formatValue($value)));
            }
        }
    }

    /**
     * 格式化值
     * @param mixed $value 值
     * @return string
     */
    protected function formatValue($value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_null($value)) {
            return 'null';
        }

        if (is_string($value) && json_decode($value) && json_last_error() === JSON_ERROR_NONE) {
            // 尝试美化JSON字符串
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            }
        }

        return (string) $value;
    }
}
