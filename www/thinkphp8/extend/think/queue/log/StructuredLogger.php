<?php

declare(strict_types=1);

namespace think\queue\log;

use think\facade\Log;

/**
 * 结构化日志记录器
 * 用于生成JSON格式的日志，便于日志分析和ELK集成
 */
class StructuredLogger
{
    /**
     * 租户ID
     */
    protected $tenantId = 'default';

    /**
     * 应用名称
     */
    protected $appName = 'queue';

    /**
     * 环境名称
     */
    protected $environment = 'production';

    /**
     * 单例实例映射
     * 按租户ID存储不同的实例
     */
    private static $instances = [];

    /**
     * 私有构造函数，防止外部实例化
     * 
     * @param string $tenantId 租户ID
     */
    private function __construct(string $tenantId = 'default')
    {
        $this->tenantId = $tenantId;
        $this->appName = config('app.name', 'queue');
        $this->environment = config('app.env', 'production');
    }

    /**
     * 获取实例
     * 
     * @param string $tenantId 租户ID
     * @return StructuredLogger
     */
    public static function getInstance(string $tenantId = 'default'): StructuredLogger
    {
        if (!isset(self::$instances[$tenantId])) {
            self::$instances[$tenantId] = new self($tenantId);
        }

        return self::$instances[$tenantId];
    }

    /**
     * 记录信息日志
     * 
     * @param string $message 日志消息
     * @param array $context 上下文信息
     * @return void
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /**
     * 记录警告日志
     * 
     * @param string $message 日志消息
     * @param array $context 上下文信息
     * @return void
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /**
     * 记录错误日志
     * 
     * @param string $message 日志消息
     * @param array $context 上下文信息
     * @return void
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /**
     * 记录调试日志
     * 
     * @param string $message 日志消息
     * @param array $context 上下文信息
     * @return void
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    /**
     * 记录日志
     * 
     * @param string $level 日志级别
     * @param string $message 日志消息
     * @param array $context 上下文信息
     * @return void
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        // 添加基础信息
        $data = [
            'timestamp' => date('Y-m-d H:i:s.v'),
            'level' => strtoupper($level),
            'message' => $message,
            'tenant_id' => $this->tenantId,
            'app_name' => $this->appName,
            'environment' => $this->environment,
            'host' => gethostname(),
            'pid' => getmypid(),
        ];

        // 合并上下文信息
        $data = array_merge($data, $context);

        // 使用ThinkPHP的日志系统记录
        Log::$level(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * 设置应用名称
     * 
     * @param string $appName 应用名称
     * @return self
     */
    public function setAppName(string $appName): self
    {
        $this->appName = $appName;
        return $this;
    }

    /**
     * 设置环境名称
     * 
     * @param string $environment 环境名称
     * @return self
     */
    public function setEnvironment(string $environment): self
    {
        $this->environment = $environment;
        return $this;
    }

    /**
     * 记录异常日志
     * 
     * @param \Throwable $exception 异常对象
     * @param array $context 上下文信息
     * @return void
     */
    public function exception(\Throwable $exception, array $context = []): void
    {
        $context['exception'] = [
            'class' => get_class($exception),
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ];

        $this->error('Exception occurred: ' . $exception->getMessage(), $context);
    }

    /**
     * 记录性能指标
     * 
     * @param string $operation 操作名称
     * @param float $duration 持续时间（毫秒）
     * @param array $context 上下文信息
     * @return void
     */
    public function metric(string $operation, float $duration, array $context = []): void
    {
        $context['metric'] = [
            'operation' => $operation,
            'duration_ms' => $duration,
        ];

        $this->info('Performance metric', $context);
    }

    /**
     * 设置租户ID
     * 
     * @param string $tenantId 租户ID
     * @return self
     */
    public function setTenantId(string $tenantId): self
    {
        $this->tenantId = $tenantId;
        return $this;
    }
}
