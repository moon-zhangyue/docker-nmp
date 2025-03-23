<?php

declare(strict_types=1);

namespace think\queue\error;

use think\facade\Log;
use think\facade\Config;
use Sentry\SentrySdk;

/**
 * Sentry错误报告器
 * 用于捕获和报告队列处理过程中的异常到Sentry
 */
class SentryReporter
{
    /**
     * Sentry客户端实例
     * @var \Sentry\ClientInterface|null
     */
    protected $client = null;

    /**
     * 是否已初始化
     * @var bool
     */
    protected $initialized = false;

    /**
     * 单例实例
     * @var SentryReporter|null
     */
    private static $instance = null;

    /**
     * 私有构造函数，防止外部实例化
     */
    private function __construct()
    {
        $this->initialize();
    }

    /**
     * 获取单例实例
     * 
     * @return SentryReporter
     */
    public static function getInstance(): SentryReporter
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * 初始化Sentry客户端
     * 
     * @return void
     */
    protected function initialize(): void
    {
        try {
            // 检查Sentry SDK是否已安装
            if (!class_exists('\\Sentry\\ClientBuilder')) {
                Log::warning('Sentry SDK not installed. Please run: composer require sentry/sdk');
                return;
            }

            // 获取Sentry DSN配置
            $dsn = Config::get('queue.sentry');
            if (empty($dsn)) {
                Log::warning('Sentry DSN not configured');
                return;
            }

            // 初始化Sentry
            \Sentry\init([
                'dsn' => $dsn,
                'environment' => Config::get('queue.sentry_config.environment', Config::get('app.env', 'production')),
                'release' => Config::get('queue.sentry_config.release', '1.0.0'),
                'traces_sample_rate' => Config::get('queue.sentry_config.traces_sample_rate', 0.2),
                'max_breadcrumbs' => Config::get('queue.sentry_config.max_breadcrumbs', 50),
            ]);

            $this->client = SentrySdk::getCurrentHub()->getClient();
            $this->initialized = true;

            Log::info('Sentry error reporter initialized');
        } catch (\Exception $e) {
            Log::error('Failed to initialize Sentry: ' . $e->getMessage());
        }
    }

    /**
     * 捕获异常并报告到Sentry
     * 
     * @param \Throwable $exception 异常对象
     * @param array $context 上下文信息
     * @return string|null 事件ID
     */
    public function captureException(\Throwable $exception, array $context = []): ?string
    {
        if (!$this->initialized) {
            Log::warning('Sentry not initialized, exception not reported');
            return null;
        }

        try {
            // 设置额外上下文
            if (!empty($context)) {
                \Sentry\configureScope(function (\Sentry\State\Scope $scope) use ($context): void {
                    foreach ($context as $key => $value) {
                        $scope->setExtra($key, $value);
                    }
                });
            }

            // 捕获异常并将EventId对象转换为字符串
            $eventIdObj = \Sentry\captureException($exception);
            $eventId = $eventIdObj ? (string)$eventIdObj : null;

            Log::info('Exception reported to Sentry', ['event_id' => $eventId]);
            return $eventId;
        } catch (\Exception $e) {
            Log::error('Failed to report exception to Sentry: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 捕获消息并报告到Sentry
     * 
     * @param string $message 消息内容
     * @param array $context 上下文信息
     * @param string $level 日志级别
     * @return string|null 事件ID
     */
    public function captureMessage(string $message, array $context = [], string $level = 'info'): ?string
    {
        if (!$this->initialized) {
            Log::warning('Sentry not initialized, message not reported');
            return null;
        }

        try {
            // 设置额外上下文
            if (!empty($context)) {
                \Sentry\configureScope(function (\Sentry\State\Scope $scope) use ($context): void {
                    foreach ($context as $key => $value) {
                        $scope->setExtra($key, $value);
                    }
                });
            }

            // 捕获消息 - 修复Severity类型
            $severityLevel = null;
            switch (strtolower($level)) {
                case 'debug':
                    $severityLevel = \Sentry\Severity::debug();
                    break;
                case 'info':
                    $severityLevel = \Sentry\Severity::info();
                    break;
                case 'warning':
                    $severityLevel = \Sentry\Severity::warning();
                    break;
                case 'error':
                    $severityLevel = \Sentry\Severity::error();
                    break;
                case 'fatal':
                    $severityLevel = \Sentry\Severity::fatal();
                    break;
                default:
                    $severityLevel = \Sentry\Severity::info();
            }
            
            // 捕获消息并将EventId对象转换为字符串
            $eventIdObj = \Sentry\captureMessage($message, $severityLevel);
            $eventId = $eventIdObj ? (string)$eventIdObj : null;

            Log::info('Message reported to Sentry', ['event_id' => $eventId]);
            return $eventId;
        } catch (\Exception $e) {
            Log::error('Failed to report message to Sentry: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 添加面包屑
     * 
     * @param string $message 消息内容
     * @param string $category 分类
     * @param array $data 数据
     * @param string $level 级别
     * @return void
     */
    public function addBreadcrumb(string $message, string $category = 'queue', array $data = [], string $level = 'info'): void
    {
        if (!$this->initialized) {
            return;
        }

        try {
            // 转换level字符串为常量值
            $levelConstant = \Sentry\Breadcrumb::LEVEL_INFO;
            switch (strtolower($level)) {
                case 'debug':
                    $levelConstant = \Sentry\Breadcrumb::LEVEL_DEBUG;
                    break;
                case 'info':
                    $levelConstant = \Sentry\Breadcrumb::LEVEL_INFO;
                    break;
                case 'warning':
                    $levelConstant = \Sentry\Breadcrumb::LEVEL_WARNING;
                    break;
                case 'error':
                    $levelConstant = \Sentry\Breadcrumb::LEVEL_ERROR;
                    break;
                case 'fatal':
                    $levelConstant = \Sentry\Breadcrumb::LEVEL_FATAL;
                    break;
            }
            
            \Sentry\addBreadcrumb(
                new \Sentry\Breadcrumb(
                    $levelConstant,
                    \Sentry\Breadcrumb::TYPE_DEFAULT,
                    $category,
                    $message,
                    $data
                )
            );
        } catch (\Exception $e) {
            Log::error('Failed to add breadcrumb: ' . $e->getMessage());
        }
    }

    /**
     * 设置用户上下文
     * 
     * @param array $user 用户信息
     * @return void
     */
    public function setUser(array $user): void
    {
        if (!$this->initialized) {
            return;
        }

        try {
            \Sentry\configureScope(function (\Sentry\State\Scope $scope) use ($user): void {
                $scope->setUser($user);
            });
        } catch (\Exception $e) {
            Log::error('Failed to set user context: ' . $e->getMessage());
        }
    }

    /**
     * 设置标签
     * 
     * @param string $key 标签键
     * @param string $value 标签值
     * @return void
     */
    public function setTag(string $key, string $value): void
    {
        if (!$this->initialized) {
            return;
        }

        try {
            \Sentry\configureScope(function (\Sentry\State\Scope $scope) use ($key, $value): void {
                $scope->setTag($key, $value);
            });
        } catch (\Exception $e) {
            Log::error('Failed to set tag: ' . $e->getMessage());
        }
    }
}
