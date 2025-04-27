<?php

declare(strict_types=1);

namespace think\log\driver;

use Monolog\Logger;
use Monolog\Handler\ElasticsearchHandler;
use Monolog\Formatter\ElasticsearchFormatter;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\WebProcessor;
use Monolog\Processor\MemoryUsageProcessor;
use Elasticsearch\ClientBuilder;
use think\App;
use think\contract\LogHandlerInterface;

/**
 * Monolog Elasticsearch日志驱动
 * 基于Monolog实现的Elasticsearch日志处理器，支持结构化日志和Kibana集成
 */
class MonologElasticsearch implements LogHandlerInterface
{
    /**
     * 配置参数
     * @var array
     */
    protected $config = [
        'hosts'           => [], // ES服务器地址
        'index_prefix'    => 'logs', // 索引前缀
        'type'            => '_doc', // 文档类型
        'level'           => 'debug', // 日志级别
        'bubble'          => true, // 是否冒泡
        'timeout'         => 5, // 连接超时时间
        'ssl_verify'      => false, // 是否验证SSL证书
        'apiKey'          => '', // API密钥
        'username'        => '', // 用户名
        'password'        => '', // 密码
        'time_format'     => 'Y-m-d H:i:s', // 时间格式
        'context_logging' => true, // 是否记录上下文信息
        'apart_level'     => [], // 独立日志级别
        'max_retry'       => 3, // 最大重试次数
    ];

    /**
     * Monolog实例
     * @var Logger
     */
    protected $logger;

    /**
     * Elasticsearch客户端实例
     * @var \Elasticsearch\Client
     */
    protected $client;

    /**
     * 应用实例
     * @var App
     */
    protected $app;

    /**
     * 日志级别映射
     * @var array
     */
    protected $levels = [
        'emergency' => \Monolog\Level::Emergency,
        'alert'     => \Monolog\Level::Alert,
        'critical'  => \Monolog\Level::Critical,
        'error'     => \Monolog\Level::Error,
        'warning'   => \Monolog\Level::Warning,
        'notice'    => \Monolog\Level::Notice,
        'info'      => \Monolog\Level::Info,
        'debug'     => \Monolog\Level::Debug,
    ];

    /**
     * 构造函数
     * @param App   $app    应用实例
     * @param array $config 配置参数
     */
    public function __construct(App $app, array $config = [])
    {
        $this->app = $app;

        if (!empty($config)) {
            $this->config = array_merge($this->config, $config);
        }

        // 如果没有配置ES主机，则从应用配置中获取
        if (empty($this->config['hosts'])) {
            $this->config['hosts'] = $app->config->get('elasticsearch.hosts', ['elasticsearch:9200']);
        }

        // 如果没有配置API密钥，则从应用配置中获取
        if (empty($this->config['apiKey'])) {
            $this->config['apiKey'] = $app->config->get('elasticsearch.apiKey', '');
        }

        // 初始化Monolog
        $this->initMonolog();
    }

    /**
     * 初始化Monolog
     */
    protected function initMonolog()
    {
        // 创建ES客户端
        $builder = ClientBuilder::create()
            ->setHosts($this->config['hosts'])
            ->setRetries($this->config['max_retry'])
            ->setSSLVerification($this->config['ssl_verify'])
            ->setConnectionParams([
                'client' => [
                    'timeout'         => $this->config['timeout'],
                    'connect_timeout' => $this->config['timeout']
                ]
            ]);

        // 设置认证方式
        if (!empty($this->config['username']) && !empty($this->config['password'])) {
            $builder->setBasicAuthentication($this->config['username'], $this->config['password']);
        }

        $this->client = $builder->build();

        // 创建Monolog实例
        $this->logger = new Logger('thinkphp');

        // 创建索引名称
        $indexName = strtolower($this->config['index_prefix'] . '-' . date('Y.m.d'));

        // 创建ES处理器
        $handler = new ElasticsearchHandler(
            $this->client,
            [
                'index' => $indexName,
                'type'  => $this->config['type'],
            ],
            $this->getLevelByName($this->config['level']),
            $this->config['bubble']
        );

        // 设置格式化器
        $formatter = new ElasticsearchFormatter($this->config['index_prefix'], $this->config['type']);
        $handler->setFormatter($formatter);

        // 添加处理器
        $this->logger->pushHandler($handler);

        // 添加处理器
        if ($this->config['context_logging']) {
            // 添加内省处理器（记录调用文件、行号等信息）
            $this->logger->pushProcessor(new IntrospectionProcessor());
            // 添加Web请求处理器（记录URL、IP等信息）
            $this->logger->pushProcessor(new WebProcessor());
            // 添加内存使用处理器
            $this->logger->pushProcessor(new MemoryUsageProcessor());
            // 添加自定义上下文处理器
            $this->logger->pushProcessor([$this, 'addContextProcessor']);
        }
    }

    /**
     * 添加上下文处理器
     * @param array $record
     * @return array
     */
    public function addContextProcessor(array $record): array
    {
        try {
            // 添加请求相关信息
            $record['extra']['request_id'] = uniqid('', true);
            $record['extra']['user_id']    = $this->app->request->middleware('auth.user_id', 0);
            $record['extra']['referer']    = $this->app->request->header('referer');
        } catch (\Throwable $e) {
            // 忽略错误
        }

        return $record;
    }

    /**
     * 根据名称获取日志级别
     * @param string $name
     * @return int
     */
    protected function getLevelByName(string $name): int
    {
        $level = $this->levels[strtolower($name)] ?? Logger::DEBUG;
        // 将Monolog\Level枚举对象转换为整数值
        return $level instanceof \Monolog\Level ? $level->value : $level;
    }

    /**
     * 日志写入接口
     * @param array $log 日志信息
     * @return bool
     */
    public function save(array $log): bool
    {
        if (empty($log)) {
            return true;
        }

        foreach ($log as $type => $val) {
            // 独立日志级别处理
            if (!empty($this->config['apart_level']) && in_array($type, $this->config['apart_level'])) {
                $this->writeApartLog($type, $val);
                continue;
            }

            $level = $this->getLevelByName($type);
            foreach ($val as $msg) {
                if (!is_string($msg)) {
                    $msg = var_export($msg, true);
                }

                $this->logger->log($level, $msg);
            }
        }

        return true;
    }

    /**
     * 独立日志级别写入
     * @param string $level 日志级别
     * @param array  $logs  日志信息
     */
    protected function writeApartLog(string $level, array $logs)
    {
        // 创建独立的Logger实例
        $logger = new Logger('thinkphp_' . $level);

        // 创建索引名称
        $indexName = strtolower($this->config['index_prefix'] . '_' . $level . '-' . date('Y.m.d'));

        // 获取ES客户端
        $builder = ClientBuilder::create()
            ->setHosts($this->config['hosts'])
            ->setRetries($this->config['max_retry'])
            ->setSSLVerification($this->config['ssl_verify'])
            ->setConnectionParams([
                'client' => [
                    'timeout'         => $this->config['timeout'],
                    'connect_timeout' => $this->config['timeout']
                ]
            ]);

        // 设置认证方式
        if (!empty($this->config['username']) && !empty($this->config['password'])) {
            $builder->setBasicAuthentication($this->config['username'], $this->config['password']);
        }

        $this->client = $builder->build();

        // 创建ES处理器
        $handler = new ElasticsearchHandler(
            $this->client,
            [
                'index' => $indexName,
                'type'  => $this->config['type'],
            ],
            $this->getLevelByName($level),
            $this->config['bubble']
        );

        // 设置格式化器
        $formatter = new ElasticsearchFormatter($this->config['index_prefix'] . '_' . $level, $this->config['type']);
        $handler->setFormatter($formatter);

        // 添加处理器
        $logger->pushHandler($handler);

        // 添加处理器
        if ($this->config['context_logging']) {
            $logger->pushProcessor(new IntrospectionProcessor());
            $logger->pushProcessor(new WebProcessor());
            $logger->pushProcessor(new MemoryUsageProcessor());
            $logger->pushProcessor([$this, 'addContextProcessor']);
        }

        // 写入日志
        $monologLevel = $this->getLevelByName($level);
        foreach ($logs as $msg) {
            if (!is_string($msg)) {
                $msg = var_export($msg, true);
            }

            $logger->log($monologLevel, $msg);
        }
    }

    /**
     * 获取Monolog实例
     * @return Logger
     */
    public function getLogger(): Logger
    {
        return $this->logger;
    }

    /**
     * 获取Elasticsearch客户端实例
     * @return \Elasticsearch\Client
     */
    public function getClient(): \Elasticsearch\Client
    {
        return $this->client;
    }

    /**
     * 获取索引模式（前缀）
     * @return string
     */
    public function getIndexPattern(): string
    {
        return $this->config['index_prefix'];
    }

    /**
     * 创建索引模板
     * 用于设置索引映射和设置，确保Kibana可以正确识别字段类型
     * @return bool
     */
    public function createIndexTemplate(): bool
    {
        try {
            // 获取ES客户端
            $builder = ClientBuilder::create()
                ->setHosts($this->config['hosts'])
                ->setRetries($this->config['max_retry'])
                ->setSSLVerification($this->config['ssl_verify'])
                ->setConnectionParams([
                    'client' => [
                        'timeout'         => $this->config['timeout'],
                        'connect_timeout' => $this->config['timeout']
                    ]
                ]);

            // 设置认证方式
            if (!empty($this->config['username']) && !empty($this->config['password'])) {
                $builder->setBasicAuthentication($this->config['username'], $this->config['password']);
            }

            $this->client = $builder->build();

            $this->client->indices()->putTemplate([
                'name' => $this->config['index_prefix'] . '_template',
                'body' => [
                    'index_patterns' => [
                        $this->config['index_prefix'] . '-*',             // 匹配标准日志格式 logs-yyyy.mm.dd
                        $this->config['index_prefix'] . '_*-*'            // 匹配独立日志级别格式 logs_error-yyyy.mm.dd
                    ],
                    'settings'       => [
                        'number_of_shards'   => 1,
                        'number_of_replicas' => 1,
                    ],
                    'mappings'       => [
                        'properties' => [
                            '@timestamp' => ['type' => 'date'],
                            'message'    => ['type' => 'text'],
                            'level'      => ['type' => 'keyword'],
                            'level_name' => ['type' => 'keyword'],
                            'channel'    => ['type' => 'keyword'],
                            'datetime'   => ['type' => 'date'],
                            'extra'      => [
                                'properties' => [
                                    'ip'           => ['type' => 'ip'],
                                    'url'          => ['type' => 'keyword'],
                                    'method'       => ['type' => 'keyword'],
                                    'user_id'      => ['type' => 'long'],
                                    'referer'      => ['type' => 'keyword'],
                                    'user_agent'   => ['type' => 'text'],
                                    'request_id'   => ['type' => 'keyword'],
                                    'memory_usage' => ['type' => 'float'],
                                    'file'         => ['type' => 'keyword'],
                                    'line'         => ['type' => 'integer'],
                                    'class'        => ['type' => 'keyword'],
                                    'function'     => ['type' => 'keyword'],
                                ]
                            ]
                        ]
                    ]
                ]
            ]);
            return true;
        } catch (\Throwable $e) {
            error_log(sprintf(
                "创建Elasticsearch索引模板失败: %s\n%s",
                $e->getMessage(),
                $e->getTraceAsString()
            ));
            return false;
        }
    }
}
