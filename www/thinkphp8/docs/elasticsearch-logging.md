# ThinkPHP8 Elasticsearch日志集成

本文档介绍如何在ThinkPHP8项目中使用Elasticsearch作为日志存储后端。

## 功能特点

- 支持按日期自动创建索引（格式：logs-YYYY.MM.DD）
- 自动记录请求上下文信息（URL、Method、IP等）
- 支持集群配置和多种认证方式
- 提供命令行工具管理索引
- 支持与ThinkPHP现有日志通道同时使用

## 配置说明

### 1. 环境变量配置

在`.env`文件中添加以下配置：

```bash
# Elasticsearch配置
ELASTICSEARCH_HOST=elasticsearch:9200
ELASTICSEARCH_USER=elastic
ELASTICSEARCH_PASSWORD=changeme
ELASTICSEARCH_INDEX_PREFIX=logs
ELASTICSEARCH_API_KEY=
ELASTICSEARCH_SSL_ENABLED=false
```

### 2. 日志配置

在`config/log.php`中已添加`elasticsearch`通道：

```php
'elasticsearch' => [
    // 日志记录方式
    'type'           => 'Elasticsearch',
    // 日志级别
    'level'          => ['info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'],
    // 使用JSON格式记录
    'json'           => true,
    // 日志处理
    'processor'      => null,
    // 关闭通道日志写入
    'close'          => false,
    // 日志输出格式化
    'time_format'    => 'Y-m-d H:i:s',
    'format'         => '[%s][%s] %s',
    // 是否按天轮转索引
    'day_rotate'     => true,
    // 索引前缀
    'index_prefix'   => env('ELASTICSEARCH_INDEX_PREFIX', 'logs'),
],
```

### 3. Elasticsearch详细配置

在`config/elasticsearch.php`中可配置更多参数：

```php
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

// 索引分片数
'number_of_shards'    => env('ELASTICSEARCH_SHARDS', 3),

// 索引副本数
'number_of_replicas'  => env('ELASTICSEARCH_REPLICAS', 1),

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
```

## 使用方法

### 基本日志记录

使用ThinkPHP的标准日志记录方法即可，系统会自动将日志发送到Elasticsearch：

```php
// 直接使用Log门面
use think\facade\Log;

Log::info('这是一条信息日志');
Log::error('这是一条错误日志');
Log::warning('这是一条警告日志', ['data' => 'additional info']);

// 使用特定通道
Log::channel('elasticsearch')->info('仅发送到Elasticsearch');

// 使用多通道
Log::channel(['file', 'elasticsearch'])->error('同时发送到文件和Elasticsearch');
```

### 索引管理命令

系统提供了命令行工具用于管理Elasticsearch索引：

```bash
# 测试连接
php think es:manager --action=test

# 列出所有索引
php think es:manager --action=list

# 创建一个新索引
php think es:manager --action=create --index=logs-custom

# 删除指定索引
php think es:manager --action=delete --index=logs-custom

# 清理30天前的旧索引
php think es:manager --action=clear
```

## 调试与故障排除

如果日志记录出现问题，可以尝试以下方法：

1. 在`.env`文件中设置`ELASTICSEARCH_DEBUG=true`开启调试
2. 检查Elasticsearch服务是否可访问：`php think es:manager --action=test`
3. 查看PHP错误日志，寻找Elasticsearch相关错误
4. 确保索引模板创建成功

### 常见问题

#### 1. API兼容性问题

如果遇到类似以下错误：

```
Call to undefined method Elasticsearch\ClientBuilder::setRequestTimeout()
```

可能是Elasticsearch PHP客户端版本与代码不兼容。本驱动已适配7.17.x版本，使用`setConnectionParams()`方法设置超时参数。如果使用其他版本的客户端，可能需要查阅相应版本的API文档并调整代码。

#### 2. 连接问题

如果无法连接到Elasticsearch服务器，请确保：

- Elasticsearch服务已启动并可访问
- 配置的主机名和端口正确
- 如果启用了安全认证，确保提供了正确的凭据
- 检查网络防火墙设置

## 日志结构说明

发送到Elasticsearch的日志包含以下字段：

- `@timestamp`: ISO 8601格式的时间戳
- `level`: 日志级别（info, error等）
- `channel`: 日志通道
- `message`: 日志消息内容
- `datetime`: 格式化的日期时间
- `app_name`: 应用名称
- `host`: 主机名
- `request_id`: 请求唯一ID
- `ip`: 客户端IP地址
- `context`: 请求上下文信息（URL, 方法, User-Agent等）

## 推荐的可视化方案

推荐使用以下工具查看和分析日志：

1. **Kibana**: Elasticsearch官方可视化工具
2. **Grafana**: 支持Elasticsearch数据源的开源监控平台

## 性能优化建议

1. 如果日志量较大，建议配置异步日志记录
2. 合理设置索引分片数和副本数
3. 定期清理旧索引，避免空间占用过大
4. 考虑使用Elasticsearch的Index Lifecycle Management功能

## 注意事项

1. 确保Elasticsearch服务器有足够的磁盘空间
2. 生产环境建议启用安全认证
3. 调整日志级别，避免记录过多不必要的信息
4. 定期备份重要日志索引 

# 查看最近100条日志
curl -X GET "http://your-es-host:9200/logs-*/_search?pretty" -H "Content-Type: application/json" -d'
{
  "size": 100,
  "sort": [
    {
      "@timestamp": {
        "order": "desc"
      }
    }
  ]
}
'

# 根据日志级别查询（如错误日志）
curl -X GET "http://your-es-host:9200/logs-*/_search?pretty" -H "Content-Type: application/json" -d'
{
  "query": {
    "term": {
      "level": "error"
    }
  },
  "sort": [
    {
      "@timestamp": {
        "order": "desc"
      }
    }
  ]
}
'