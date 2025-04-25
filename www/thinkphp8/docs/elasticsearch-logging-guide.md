# Elasticsearch 日志驱动使用指南

## 概述

本项目已集成 Elasticsearch 日志驱动，支持以下功能：

- 结构化日志存储：所有日志以结构化 JSON 格式存储在 Elasticsearch 中
- 批量提交：日志会批量提交到 Elasticsearch，提高性能
- 错误处理：内置错误处理和重试机制，确保日志可靠传输
- Kibana 可视化：支持与 Kibana 集成，提供强大的日志可视化和分析能力

## 配置说明

### 日志配置

日志驱动配置位于 `config/log.php` 文件中，已添加 Elasticsearch 驱动选项：

```php
'elasticsearch' => [
    // 日志记录方式
    'type'           => '\\think\\log\\driver\\Elasticsearch',
    // ES索引前缀
    'index_prefix'   => 'thinkphp_logs',
    // 批量提交大小
    'batch_size'     => 100,
    // 独立日志级别
    'apart_level'    => ['error', 'critical', 'alert', 'emergency'],
    // 使用JSON格式记录
    'json'           => true,
    // 记录上下文信息
    'context_logging' => true,
    // 最大重试次数
    'max_retry'      => 3,
    // 重试间隔(毫秒)
    'retry_interval' => 1000,
    // 日志输出格式化
    'time_format'    => 'Y-m-d H:i:s',
    'format'         => '[%s][%s] %s',
    // 关闭通道日志写入
    'close'          => false,
],
```

### Elasticsearch 连接配置

Elasticsearch 连接配置位于 `config/elasticsearch.php` 文件中：

```php
return [
    'hosts'  => ['elasticsearch:9200'], // Elasticsearch 服务器地址
    'apiKey' => 'your_api_key', // 可选：如果使用 API Key 认证
];
```

请根据您的 Elasticsearch 集群配置修改上述参数。

## 使用方法

### 基本使用

使用 ThinkPHP 的日志方法记录日志，这些日志会自动存储到 Elasticsearch 中：

```php
// 记录调试信息
Log::debug('这是一条调试信息');

// 记录信息
Log::info('这是一条普通信息');

// 记录警告
Log::warning('这是一条警告信息');

// 记录错误
Log::error('这是一条错误信息');

// 记录严重错误
Log::critical('这是一条严重错误信息');
```

### 记录上下文信息

日志驱动会自动记录请求上下文信息，包括：

- IP 地址
- 请求方法
- 请求 URI
- User-Agent
- Referer
- 用户 ID（如果可用）

### 批量提交

日志会在以下情况下批量提交到 Elasticsearch：

1. 当缓冲区达到配置的 `batch_size` 大小时
2. 当应用程序结束时（通过 `register_shutdown_function`）

## 命令行工具

项目提供了命令行工具用于管理 Elasticsearch 日志索引：

### 初始化索引模板

```bash
php think es:log init-template
```

此命令会创建索引模板，确保日志字段映射正确，便于 Kibana 可视化。

### 清理旧索引

```bash
php think es:log clean-indices --days=30
```

此命令会清理 30 天前的日志索引，可以通过 `--days` 参数指定保留天数。

## Kibana 可视化

### 配置 Kibana

1. 登录 Kibana 控制台
2. 创建索引模式：
   - 进入 Stack Management > Index Patterns
   - 点击 "Create index pattern"
   - 输入 `thinkphp_logs*`（或您配置的索引前缀）
   - 选择 `@timestamp` 作为时间字段

### 创建仪表板

在 Kibana 中，您可以创建以下常用可视化：

1. **日志级别分布**：饼图显示不同级别日志的分布
2. **时间线图表**：显示一段时间内的日志数量趋势
3. **错误日志表格**：显示最近的错误日志详情
4. **请求路径分析**：分析最常访问的 URI 路径
5. **用户活动监控**：按用户 ID 分组的活动日志

## 故障排除

### 连接问题

如果日志无法写入 Elasticsearch，请检查：

1. Elasticsearch 服务是否正常运行
2. 配置文件中的连接信息是否正确
3. 网络连接是否通畅

### 索引模板问题

如果索引模板未正确应用，请运行：

```bash
php think es:log init-template
```

### 查看文件日志

如果需要排查 Elasticsearch 日志驱动本身的问题，可以查看 PHP 错误日志，驱动会在出现问题时记录详细信息。

## 最佳实践

1. **定期清理旧索引**：设置定时任务运行 `php think es:log clean-indices` 命令
2. **监控索引大小**：大型应用可能需要调整 Elasticsearch 集群配置
3. **优化日志级别**：在生产环境中，建议只记录 info 级别以上的日志
4. **使用结构化日志**：尽可能使用结构化数据，便于后续分析

## 扩展阅读

- [Elasticsearch 官方文档](https://www.elastic.co/guide/index.html)
- [Kibana 用户指南](https://www.elastic.co/guide/en/kibana/current/index.html)
- [ThinkPHP 日志文档](https://www.kancloud.cn/manual/thinkphp6_0/1037626)