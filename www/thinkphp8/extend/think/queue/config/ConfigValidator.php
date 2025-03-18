<?php

declare(strict_types=1);

namespace think\queue\config;

use think\facade\Config;

/**
 * 队列配置验证器
 * 用于验证配置项的合法性，避免运行时错误
 */
class ConfigValidator
{
    /**
     * 配置规则
     * @var array
     */
    protected $rules = [
        'kafka.connections.*.brokers' => [
            'required' => true,
            'type' => 'string',
            'message' => 'Kafka brokers配置必须是字符串'
        ],
        'kafka.connections.*.consumer_group_id' => [
            'required' => true,
            'type' => 'string',
            'message' => '消费者组ID必须是字符串'
        ],
        'kafka.connections.*.consumer.enable.auto.commit' => [
            'type' => 'boolean',
            'message' => '自动提交配置必须是布尔值'
        ],
        'kafka.connections.*.consumer.auto.commit.interval.ms' => [
            'type' => 'integer',
            'message' => '自动提交间隔必须是整数'
        ],
        'kafka.connections.*.consumer.session.timeout.ms' => [
            'type' => 'integer',
            'message' => '会话超时时间必须是整数'
        ],
        'kafka.connections.*.producer.compression.codec' => [
            'type' => 'string',
            'enum' => ['none', 'gzip', 'snappy', 'lz4', 'zstd'],
            'message' => '压缩编码必须是有效的压缩类型'
        ],
        'kafka.connections.*.producer.message.send.max.retries' => [
            'type' => 'integer',
            'min' => 0,
            'message' => '重试次数必须是非负整数'
        ],
        'queue.sentry' => [
            'type' => 'string',
            'message' => 'Sentry DSN必须是有效的URL'
        ]
    ];

    /**
     * 单例实例
     */
    private static $instance = null;

    /**
     * 私有构造函数，防止外部实例化
     */
    private function __construct() {}

    /**
     * 获取单例实例
     * 
     * @return ConfigValidator
     */
    public static function getInstance(): ConfigValidator
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * 验证配置
     * 
     * @param string $path 配置路径
     * @param mixed $value 配置值
     * @return array 验证结果，包含是否有效和错误消息
     */
    public function validate(string $path, $value): array
    {
        // 查找匹配的规则
        $matchedRule = null;
        $matchedPath = null;

        foreach ($this->rules as $rulePath => $rule) {
            // 将通配符转换为正则表达式
            $pattern = '/^' . str_replace(['*', '.'], ['[^.]+', '\.'], $rulePath) . '$/i';

            if (preg_match($pattern, $path)) {
                $matchedRule = $rule;
                $matchedPath = $rulePath;
                break;
            }
        }

        // 如果没有匹配的规则，则认为有效
        if (!$matchedRule) {
            return ['valid' => true, 'message' => ''];
        }

        // 验证必填项
        if (isset($matchedRule['required']) && $matchedRule['required'] && empty($value)) {
            return [
                'valid' => false,
                'message' => $matchedRule['message'] ?? "配置项 {$path} 不能为空"
            ];
        }

        // 如果值为空且不是必填项，则认为有效
        if (empty($value) && (!isset($matchedRule['required']) || !$matchedRule['required'])) {
            return ['valid' => true, 'message' => ''];
        }

        // 验证类型
        if (isset($matchedRule['type'])) {
            $typeValid = false;

            switch ($matchedRule['type']) {
                case 'string':
                    $typeValid = is_string($value);
                    break;
                case 'integer':
                    $typeValid = is_numeric($value) && (int)$value == $value;
                    break;
                case 'boolean':
                    $typeValid = is_bool($value) || $value === 'true' || $value === 'false' || $value === '1' || $value === '0' || $value === 1 || $value === 0;
                    break;
                case 'array':
                    $typeValid = is_array($value);
                    break;
            }

            if (!$typeValid) {
                return [
                    'valid' => false,
                    'message' => $matchedRule['message'] ?? "配置项 {$path} 类型不正确，应为 {$matchedRule['type']}"
                ];
            }
        }

        // 验证枚举值
        if (isset($matchedRule['enum']) && !in_array($value, $matchedRule['enum'])) {
            return [
                'valid' => false,
                'message' => $matchedRule['message'] ?? "配置项 {$path} 的值必须是以下之一: " . implode(', ', $matchedRule['enum'])
            ];
        }

        // 验证最小值
        if (isset($matchedRule['min']) && $value < $matchedRule['min']) {
            return [
                'valid' => false,
                'message' => $matchedRule['message'] ?? "配置项 {$path} 的值不能小于 {$matchedRule['min']}"
            ];
        }

        // 验证最大值
        if (isset($matchedRule['max']) && $value > $matchedRule['max']) {
            return [
                'valid' => false,
                'message' => $matchedRule['message'] ?? "配置项 {$path} 的值不能大于 {$matchedRule['max']}"
            ];
        }

        return ['valid' => true, 'message' => ''];
    }

    /**
     * 验证所有配置
     * 
     * @return array 验证结果，包含是否全部有效和错误消息列表
     */
    public function validateAll(): array
    {
        $errors = [];
        $allValid = true;

        // 验证Kafka配置
        $kafkaConnections = Config::get('kafka.connections', []);

        foreach ($kafkaConnections as $name => $connection) {
            // 验证brokers
            $path = "kafka.connections.{$name}.brokers";
            $result = $this->validate($path, $connection['brokers'] ?? null);

            if (!$result['valid']) {
                $errors[$path] = $result['message'];
                $allValid = false;
            }

            // 验证consumer_group_id
            $path = "kafka.connections.{$name}.consumer_group_id";
            $result = $this->validate($path, $connection['consumer_group_id'] ?? null);

            if (!$result['valid']) {
                $errors[$path] = $result['message'];
                $allValid = false;
            }

            // 验证consumer配置
            if (isset($connection['consumer'])) {
                foreach ($connection['consumer'] as $key => $value) {
                    $path = "kafka.connections.{$name}.consumer.{$key}";
                    $result = $this->validate($path, $value);

                    if (!$result['valid']) {
                        $errors[$path] = $result['message'];
                        $allValid = false;
                    }
                }
            }

            // 验证producer配置
            if (isset($connection['producer'])) {
                foreach ($connection['producer'] as $key => $value) {
                    $path = "kafka.connections.{$name}.producer.{$key}";
                    $result = $this->validate($path, $value);

                    if (!$result['valid']) {
                        $errors[$path] = $result['message'];
                        $allValid = false;
                    }
                }
            }
        }

        // 验证Sentry配置
        $sentryDsn = Config::get('queue.sentry.dsn');
        if ($sentryDsn) {
            $result = $this->validate('queue.sentry.dsn', $sentryDsn);

            if (!$result['valid']) {
                $errors['queue.sentry.dsn'] = $result['message'];
                $allValid = false;
            }
        }

        return [
            'valid' => $allValid,
            'errors' => $errors
        ];
    }
}
