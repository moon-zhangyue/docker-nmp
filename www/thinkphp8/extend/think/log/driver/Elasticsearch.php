<?php
declare(strict_types=1);

namespace think\log\driver;

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use think\facade\App;
use think\facade\Config;
use think\contract\LogHandlerInterface;

/**
 * Elasticsearch日志驱动
 * 使用Elasticsearch实现日志存储
 */
class Elasticsearch implements LogHandlerInterface
{
    /**
     * 配置参数
     * @var array
     */
    protected $config = [
        'time_format'     => 'Y-m-d H:i:s',
        'json'            => false,
        'json_options'    => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        'format'          => '[%s][%s] %s',
        'index_prefix'    => 'logs',
        'day_rotate'      => true, // 是否按天轮转索引
        'hosts'           => [],
        'auth'            => [],
        'timeout'         => 10,
        'connect_timeout' => 5,
    ];

    /**
     * Elasticsearch客户端
     * @var Client
     */
    protected $client;

    /**
     * 当前索引名称
     * @var string
     */
    protected $index;

    /**
     * 构造函数
     * @param array $config 配置参数
     */
    public function __construct(array $config = [])
    {
        if (!empty($config)) {
            $this->config = array_merge($this->config, $config);
        }

        // 合并Elasticsearch配置
        $esConfig     = \think\facade\Config::get('elasticsearch') ?: [];
        $this->config = array_merge($this->config, $esConfig);

        // 合并应用名（兼容无门面环境）
        if (function_exists('config')) {
            $this->config['app_name'] = config('app.app_name') ?: 'thinkphp';
        } elseif (isset($this->config['app_name'])) {
            $this->config['app_name'] = $this->config['app_name'];
        } else {
            $this->config['app_name'] = 'thinkphp';
        }

        // 初始化索引名称
        $this->setIndex();

        // 初始化Elasticsearch客户端
        $this->client = $this->createClient();
    }

    /**
     * 创建Elasticsearch客户端
     * @return Client
     */
    protected function createClient(): Client
    {
        $builder = ClientBuilder::create()
            ->setHosts($this->config['hosts'])
            ->setRetries(2);

        // 设置请求超时和连接超时
        $builder->setConnectionParams([
            'client' => [
                'timeout'         => $this->config['timeout'],
                'connect_timeout' => $this->config['connect_timeout']
            ]
        ]);

        // 设置身份验证
        if (!empty($this->config['auth']) && isset($this->config['auth'][0]) && isset($this->config['auth'][1])) {
            $builder->setBasicAuthentication($this->config['auth'][0], $this->config['auth'][1]);
        }

        // 设置API Key (如果提供)
        if (!empty($this->config['apiKey'])) {
            $builder->setApiKey($this->config['apiKey'], '');
        }

        return $builder->build();
    }

    /**
     * 设置索引名称
     * @return void
     */
    protected function setIndex(): void
    {
        $prefix = $this->config['index_prefix'];

        if ($this->config['day_rotate']) {
            $this->index = $prefix . '-' . date('Y.m.d');
        } else {
            $this->index = $prefix;
        }
    }

    /**
     * 检查并创建索引模板（如果不存在）
     * @return void
     */
    protected function checkAndCreateTemplate(): void
    {
        $templateName = $this->config['index_prefix'] . '_template';

        // 检查模板是否存在
        try {
            $exists = $this->client->indices()->existsTemplate([
                'name' => $templateName
            ]);

            if (!$exists) {
                // 创建索引模板
                $this->client->indices()->putTemplate([
                    'name' => $templateName,
                    'body' => [
                        'index_patterns' => [$this->config['index_prefix'] . '-*'],
                        'mappings'       => [
                            'properties' => [
                                '@timestamp' => ['type' => 'date'],
                                'level'      => ['type' => 'keyword'],
                                'channel'    => ['type' => 'keyword'],
                                'message'    => ['type' => 'text'],
                                'context'    => ['type' => 'object', 'dynamic' => true],
                                'extra'      => ['type' => 'object', 'dynamic' => true],
                                'datetime'   => ['type' => 'date', 'format' => 'yyyy-MM-dd HH:mm:ss||yyyy-MM-dd||strict_date_optional_time||epoch_millis'],
                                'app_name'   => ['type' => 'keyword'],
                                'host'       => ['type' => 'keyword'],
                                'request_id' => ['type' => 'keyword'],
                                'trace_id'   => ['type' => 'keyword'],
                                'ip'         => ['type' => 'ip']
                            ]
                        ],
                        'settings'       => [
                            'number_of_shards'   => 3,
                            'number_of_replicas' => 1
                        ]
                    ]
                ]);
            }
        } catch (\Exception $e) {
            // 记录错误，但不中断流程
            error_log('[ES Template Error] ' . $e->getMessage());

            // 尝试直接创建索引
            try {
                $this->createIndex();
            } catch (\Exception $ex) {
                error_log('[ES Index Creation Error] ' . $ex->getMessage());
            }
        }
    }

    /**
     * 直接创建索引（当模板创建失败时使用）
     */
    protected function createIndex(): void
    {
        // 检查索引是否存在
        if (!$this->client->indices()->exists(['index' => $this->index])) {
            // 创建索引
            $this->client->indices()->create([
                'index' => $this->index,
                'body'  => [
                    'settings' => [
                        'number_of_shards'   => $this->config['number_of_shards'] ?? 3,
                        'number_of_replicas' => $this->config['number_of_replicas'] ?? 1,
                    ],
                    'mappings' => [
                        'properties' => [
                            '@timestamp' => ['type' => 'date'],
                            'level'      => ['type' => 'keyword'],
                            'channel'    => ['type' => 'keyword'],
                            'message'    => ['type' => 'text'],
                            'context'    => ['type' => 'object', 'dynamic' => true],
                            'extra'      => ['type' => 'object', 'dynamic' => true],
                            'datetime'   => ['type' => 'date', 'format' => 'yyyy-MM-dd HH:mm:ss||yyyy-MM-dd||strict_date_optional_time||epoch_millis'],
                            'app_name'   => ['type' => 'keyword'],
                            'host'       => ['type' => 'keyword'],
                            'request_id' => ['type' => 'keyword'],
                            'trace_id'   => ['type' => 'keyword'],
                            'ip'         => ['type' => 'ip']
                        ]
                    ]
                ]
            ]);
        }
    }

    /**
     * 日志写入
     * @param array $log 日志信息
     * @return bool
     */
    public function save(array $log): bool
    {
        if (empty($log)) {
            return true;
        }

        try {
            // 检查并创建索引模板
            $this->checkAndCreateTemplate();

            $appName   = $this->config['app_name'] ?? 'thinkphp';
            $requestId = uniqid('', true);

            foreach ($log as $type => $val) {
                foreach ($val as $msg) {
                    if (!is_string($msg)) {
                        $msg = var_export($msg, true);
                    }

                    $document = [
                        '@timestamp' => date('c'),
                        'level'      => $type,
                        'channel'    => 'thinkphp',
                        'message'    => $msg,
                        'datetime'   => date('c'),
                        'app_name'   => $appName,
                        'host'       => gethostname(),
                        'request_id' => $requestId,
                        'ip'         => $this->getClientIp()
                    ];

                    // 添加请求信息
                    if (isset($_SERVER['REQUEST_URI'])) {
                        $document['context'] = [
                            'url'        => $_SERVER['REQUEST_URI'] ?? '',
                            'method'     => $_SERVER['REQUEST_METHOD'] ?? '',
                            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                            'referer'    => $_SERVER['HTTP_REFERER'] ?? '',
                        ];
                    }

                    // 发送到Elasticsearch
                    try {
                        $this->client->index([
                            'index' => $this->index,
                            'body'  => $document
                        ]);
                    } catch (\Exception $e) {
                        // 如果索引操作失败，尝试重新创建索引
                        error_log('[ES Index Operation Error] ' . $e->getMessage());
                        $this->createIndex();
                        // 再次尝试
                        $this->client->index([
                            'index' => $this->index,
                            'body'  => $document
                        ]);
                    }
                }
            }

            return true;
        } catch (\Exception $e) {
            // 记录更详细的错误信息
            $errorMsg = '[Elasticsearch Log Error] ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine();
            error_log($errorMsg);
            // 写入到本地日志文件，确保可以看到错误
            $logDir = rtrim(runtime_path('log'), '/\\');
            if (!is_dir($logDir)) {
                mkdir($logDir, 0777, true);
            }
            $logPath = $logDir . DIRECTORY_SEPARATOR . 'es_error.log';
            file_put_contents($logPath, date('Y-m-d H:i:s') . ' ' . $errorMsg . PHP_EOL, FILE_APPEND);
            return false;
        }
    }

    /**
     * 获取客户端IP
     * @return string
     */
    protected function getClientIp(): string
    {
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        } else {
            $ip = '0.0.0.0';
        }

        // 处理多IP情况，只取第一个
        if (strpos($ip, ',') !== false) {
            $ip = explode(',', $ip)[0];
        }

        return $ip;
    }
}