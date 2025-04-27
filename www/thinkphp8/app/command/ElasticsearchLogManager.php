<?php

declare(strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Config;
use think\log\driver\MonologElasticsearch as Elasticsearch; // Alias to minimize changes
use think\App;

/**
 * Elasticsearch日志管理命令
 * 用于管理ES日志索引、创建索引模板、清理旧索引等
 */
class ElasticsearchLogManager extends Command
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
        $this->setName('es:log')
            ->addArgument('action', Argument::REQUIRED, '操作类型: init-template, clean-indices')
            ->addOption('days', 'd', Option::VALUE_OPTIONAL, '保留最近几天的日志索引', 30)
            ->addOption('prefix', 'p', Option::VALUE_OPTIONAL, '索引前缀', 'es_log_')
            ->setDescription('Elasticsearch日志管理工具');
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
        $days   = (int) $input->getOption('days');
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

            switch ($action) {
                case 'init-template':
                    return $this->initTemplate($logger, $output);
                case 'clean-indices':
                    return $this->cleanIndices($logger, $days, $output);
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
     * 初始化索引模板
     * @param Elasticsearch $logger ES日志驱动实例 // Using aliased MonologElasticsearch
     * @param Output $output 输出对象
     * @return int
     */
    protected function initTemplate(Elasticsearch $logger, Output $output) // Using aliased MonologElasticsearch
    {
        $output->writeln('正在初始化Elasticsearch日志索引模板...');

        if ($logger->createIndexTemplate()) {
            $output->info('索引模板创建成功');
            return 0; // SUCCESS
        } else {
            $output->error('索引模板创建失败');
            return 1; // FAILURE
        }
    }

    /**
     * 清理旧索引
     * @param Elasticsearch $logger ES日志驱动实例 // Using aliased MonologElasticsearch
     * @param int $days 保留天数
     * @param Output $output 输出对象
     * @return int
     */
    protected function cleanIndices(Elasticsearch $logger, int $days, Output $output) // Using aliased MonologElasticsearch
    {
        $output->writeln(sprintf('正在清理%d天前的Elasticsearch日志索引...', $days));

        // 获取ES客户端
        $client = $logger->getClient();
        if (!$client) {
            $output->error('无法获取Elasticsearch客户端');
            return 1; // FAILURE
        }

        // 计算截止日期
        $cutoffDate = new \DateTime();
        $cutoffDate->modify("-{$days} days");
        $cutoffDateStr = $cutoffDate->format('Y.m.d');

        // 获取所有索引
        $indicesResponse = $client->indices()->get(['index' => $logger->getIndexPattern() . '*']);
        // 从响应中提取索引数组
        $indices = (array) $indicesResponse;

        $deletedCount = 0;

        // 检查 $indices 是否确实是数组
        if (!is_array($indices)) {
            $output->error('无法从Elasticsearch响应中获取索引列表');
            // 可以选择性地记录响应以进行调试
            // $output->writeln('ES Response: ' . print_r($indicesResponse->asString(), true)); 
            return 1; // FAILURE
        }

        foreach (array_keys($indices) as $indexName) {
            // 从索引名称中提取日期部分
            if (preg_match('/-(\d{4}\.\d{2}\.\d{2})$/', $indexName, $matches)) {
                $indexDate = $matches[1];

                // 如果索引日期早于截止日期，则删除
                if ($indexDate < $cutoffDateStr) {
                    try {
                        $client->indices()->delete(['index' => $indexName]);
                        $output->writeln(sprintf('已删除索引: %s', $indexName));
                        $deletedCount++;
                    } catch (\Throwable $e) {
                        $output->warning(sprintf('删除索引失败 %s: %s', $indexName, $e->getMessage()));
                    }
                }
            }
        }

        $output->info(sprintf('清理完成，共删除%d个旧索引', $deletedCount));
        return 0; // SUCCESS
    }
}
