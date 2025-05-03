# ThinkPHP 8 Elasticsearch 日志记录指南

本文档旨在指导您如何在 ThinkPHP 8 项目中配置和使用 Elasticsearch 进行日志记录。

## 1. 安装依赖

首先，确保您已经安装了 Elasticsearch PHP 客户端库：

```bash
composer require elasticsearch/elasticsearch
```

## 2. 配置 Elasticsearch 连接

在 `config/elasticsearch.php` 文件中配置 Elasticsearch 的连接信息。以下是一个示例配置：

```php
<?php
// config/elasticsearch.php

return [
    // Elasticsearch服务器地址，支持多节点集群
    'hosts'           => [env('ELASTICSEARCH_HOST', 'elasticsearch:9200')], 
    
    // API Key认证 (如果使用)
    'apiKey'          => env('ELASTICSEARCH_API_KEY', ''),
    
    // 请求超时时间（秒）
    'timeout'         => env('ELASTICSEARCH_TIMEOUT', 10),
    
    // 连接超时时间（秒）
    'connect_timeout' => env('ELASTICSEARCH_CONNECT_TIMEOUT', 5),
    
    // 重试次数
    'retries'         => env('ELASTICSEARCH_RETRIES', 2),

    // 索引名称前缀，日期将自动附加形成 prefix-YYYY.MM.DD 格式
    'index_prefix'    => env('ELASTICSEARCH_INDEX_PREFIX', 'logs'),
    
    // 是否按天创建索引
    'day_rotate'      => env('ELASTICSEARCH_DAY_ROTATE', true),
    
    // 索引分片数
    'number_of_shards'    => env('ELASTICSEARCH_SHARDS', 3),
    
    // 索引副本数
    'number_of_replicas'  => env('ELASTICSEARCH_REPLICAS', 1),

    // 基本身份验证
    'auth'            => [
        env('ELASTICSEARCH_USER', ''),
        env('ELASTICSEARCH_PASSWORD', '')
    ],
    
    // SSL设置
    'ssl' => [
        // 是否启用SSL
        'enabled' => env('ELASTICSEARCH_SSL_ENABLED', false),
        // 是否验证SSL证书
        'verify' => env('ELASTICSEARCH_SSL_VERIFY', true),
        // 证书路径
        'cert' => env('ELASTICSEARCH_SSL_CERT', ''),
        // 自签名CA证书路径
        'ca' => env('ELASTICSEARCH_SSL_CA', ''),
    ],
    
    // 调试模式
    'debug'           => env('ELASTICSEARCH_DEBUG', false),
];
```

建议使用 `.env` 文件来管理敏感信息，如主机地址、用户名、密码和 API Key。

## 3. 配置日志通道

在 `config/log.php` 文件中配置 Elasticsearch 日志通道。确保 `channels` 数组中包含 `elasticsearch` 配置，并可以将其设置为默认通道。

```php
<?php
// config/log.php

return [
    // 默认日志记录通道
    'default'      => env('log.channel', 'elasticsearch'), // 设置为 elasticsearch
    // ... 其他配置 ...

    // 日志通道列表
    'channels'     => [
        'file'          => [
            // ... 文件日志配置 ...
        ],
        // Elasticsearch日志通道
        'elasticsearch' => [
            // 日志记录方式
            'type'         => 'Elasticsearch', // 必须为 Elasticsearch
            // 日志级别 (可以根据需要调整)
            'level'        => ['info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency', 'debug'],
            // 使用JSON格式记录 (驱动内部处理，通常保持true)
            'json'         => true,
            // 日志处理
            'processor'    => null,
            // 关闭通道日志写入
            'close'        => false,
            // 日志输出格式化 (驱动内部会覆盖此格式)
            'time_format'  => 'Y-m-d H:i:s',
            'format'       => '[%s][%s] %s',
            // 是否按天轮转索引 (会读取 elasticsearch.php 中的配置)
            'day_rotate'   => true, 
            // 索引前缀 (会读取 elasticsearch.php 中的配置)
            'index_prefix' => env('ELASTICSEARCH_INDEX_PREFIX', 'logs'),
        ],
        // ... 其他通道 ...
    ],

];
```

- `type`: 必须设置为 `Elasticsearch`，对应 `extend/think/log/driver/Elasticsearch.php` 驱动。
- `level`: 定义此通道记录哪些级别的日志。
- `day_rotate` 和 `index_prefix`: 这些配置会优先从 `config/elasticsearch.php` 读取，这里的配置可以作为备用。

## 4. 使用日志

配置完成后，您可以使用 `think\facade\Log` Facade 来记录日志。

### 4.1 基本日志记录

```php
use think\facade\Log;

Log::info('这是一条信息日志');
Log::error('这是一条错误日志');
Log::debug('这是一条调试日志', ['user_id' => 123]); // 可以附加上下文信息
```

### 4.2 记录带上下文的日志

上下文信息会作为独立的字段存储在 Elasticsearch 中，方便查询和分析。

```php
$user = [
    'id'    => 1,
    'name'  => '测试用户',
    'email' => 'test@example.com'
];

// 使用占位符
Log::info('用户登录成功 {user_name}', ['user_name' => $user['name'], 'user_info' => $user]);

// 记录异常信息
try {
    throw new \Exception('模拟异常测试');
} catch (\Exception $e) {
    Log::error('捕获到异常: ' . $e->getMessage(), [
        'exception' => $e, // 直接传递异常对象
        'file'      => $e->getFile(),
        'line'      => $e->getLine(),
        // 'trace'     => $e->getTraceAsString() // Trace信息可能很长，按需记录
    ]);
}
```

驱动会自动处理上下文信息，将其存储在 `context` 字段中。

### 4.3 指定通道记录

如果您配置了多个日志通道，可以明确指定将日志发送到哪个通道。

```php
// 仅记录到 Elasticsearch
Log::channel('elasticsearch')->info('这条日志只去 Elasticsearch');

// 同时记录到文件和 Elasticsearch
Log::channel(['file', 'elasticsearch'])->warning('这条警告会记录到两个地方');
```

### 4.4 记录所有级别的日志

ThinkPHP 支持 PSR-3 标准的所有日志级别：

```php
Log::emergency('系统无法使用 - EMERGENCY');
Log::alert('必须立即采取行动 - ALERT');
Log::critical('危急情况 - CRITICAL');
Log::error('运行时错误 - ERROR');
Log::warning('警告事件 - WARNING');
Log::notice('普通但重要的事件 - NOTICE');
Log::info('信息性消息 - INFO');
Log::debug('调试信息 - DEBUG');
```

## 5. Elasticsearch 驱动特性

- **自动创建索引**: 驱动会根据配置的 `index_prefix` 和 `day_rotate` 自动创建或选择合适的索引。
- **自动创建索引模板**: 首次运行时，驱动会尝试创建一个索引模板 (`<index_prefix>_template`)，定义通用的字段映射（如 `@timestamp`, `level`, `message`, `context` 等），以优化存储和查询。
- **字段映射**: 
    - `@timestamp`: 日志记录时间 (UTC)
    - `level`: 日志级别 (keyword)
    - `channel`: 日志通道名称 (keyword)
    - `message`: 日志消息主体 (text)
    - `context`: 上下文字段 (object, dynamic)
    - `extra`: 额外信息 (object, dynamic)
    - `datetime`: 本地格式化时间 (date)
    - `app_name`: 应用名称 (keyword)
    - `host`: 服务器主机名 (keyword)
    - `request_id`: 请求ID (keyword, 如果可用)
    - `trace_id`: 链路追踪ID (keyword, 如果可用)
    - `ip`: 客户端IP (ip, 如果可用)
- **批量写入**: 驱动内部使用 Elasticsearch 的 Bulk API 进行批量写入，提高性能。

## 6. 查询日志

您可以使用 Kibana 或其他 Elasticsearch 查询工具来查询和分析存储在 Elasticsearch 中的日志。

**常用查询示例 (Kibana Dev Tools):**

```json
# 查询特定级别的日志
GET logs-*/_search
{
  "query": {
    "match": {
      "level": "error"
    }
  }
}

# 查询包含特定关键字的日志
GET logs-*/_search
{
  "query": {
    "match": {
      "message": "用户登录"
    }
  }
}

# 查询特定用户的操作日志 (假设 context 中有 user_id)
GET logs-*/_search
{
  "query": {
    "term": {
      "context.user_id": 1
    }
  }
}

# 按时间范围查询
GET logs-*/_search
{
  "query": {
    "range": {
      "@timestamp": {
        "gte": "now-1h",
        "lte": "now"
      }
    }
  },
  "sort": [
    { "@timestamp": "desc" }
  ]
}
```

## 7. 注意事项

- **性能**: 大量日志写入可能会对 Elasticsearch 集群产生压力，请确保您的集群配置能够承受预期的负载。
- **索引管理**: 定期管理 Elasticsearch 索引，例如使用 ILM (Index Lifecycle Management) 策略来自动处理旧索引（如删除或归档）。
- **错误处理**: 驱动在连接或写入 Elasticsearch 失败时会尝试记录错误到 PHP 的错误日志 (`error_log`)，但不会中断应用程序的正常流程。

通过以上配置和示例，您可以有效地将 ThinkPHP 8 项目的日志集中存储到 Elasticsearch 中，便于后续的监控、查询和分析。