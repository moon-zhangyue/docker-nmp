# Elasticsearch 使用指南

## 概述

本文档提供了在ThinkPHP 8项目中使用Elasticsearch的详细指南，包括索引设计、查询优化和常见问题解决方案。

## 索引设计

### 用户索引设计

用户索引(`users`)的映射设计如下：

```json
{
  "mappings": {
    "properties": {
      "name": {
        "type": "text",
        "analyzer": "ik_max_word",
        "search_analyzer": "ik_smart",
        "fields": {
          "keyword": {
            "type": "keyword",
            "ignore_above": 256
          }
        }
      },
      "email": {
        "type": "text",
        "analyzer": "standard",
        "fields": {
          "keyword": {
            "type": "keyword",
            "ignore_above": 256
          }
        }
      },
      "age": {
        "type": "integer"
      },
      "country": {
        "type": "keyword"
      },
      "created_at": {
        "type": "date",
        "format": "yyyy-MM-dd HH:mm:ss||yyyy-MM-dd||epoch_millis"
      },
      "updated_at": {
        "type": "date",
        "format": "yyyy-MM-dd HH:mm:ss||yyyy-MM-dd||epoch_millis"
      }
    }
  }
}
```

### 索引设计最佳实践

1. **字段类型选择**：
   - 对于需要全文搜索的字段，使用`text`类型
   - 对于需要精确匹配或聚合的字段，使用`keyword`类型
   - 对于数值型字段，根据数据范围选择合适的类型（integer, long等）
   - 对于日期字段，使用`date`类型并指定格式

2. **分词器选择**：
   - 中文内容建议使用IK分词器（ik_max_word, ik_smart）
   - 英文内容可使用标准分词器（standard）

3. **多字段设计**：
   - 对于既需要全文搜索又需要精确匹配的字段，使用多字段设计
   - 例如：`name`字段同时具有`name.keyword`子字段

## 查询优化

### 基础查询优化

1. **合理使用查询类型**：
   - `match`查询：适用于全文搜索
   - `term`查询：适用于精确匹配
   - `range`查询：适用于范围查询
   - `multi_match`查询：适用于多字段搜索

2. **分页查询优化**：
   - 避免深度分页，使用`search_after`或`scroll API`代替大偏移量
   - 控制每页大小，建议不超过100条记录

3. **字段过滤**：
   - 只返回需要的字段，减少网络传输量
   - 使用`_source`参数指定需要返回的字段

### 高级查询优化

1. **复合查询**：
   - 使用`bool`查询组合多个查询条件
   - 合理使用`must`、`should`、`must_not`和`filter`子句

2. **聚合查询优化**：
   - 对于大数据量的聚合，考虑使用采样聚合
   - 使用`filter`聚合先过滤数据再聚合
   - 对于频繁使用的聚合，考虑使用预计算

3. **缓存利用**：
   - 合理使用查询缓存和过滤器缓存
   - 对于不变的数据，设置较长的缓存时间

## 常见问题解决方案

### 连接问题

**问题**：无法连接到Elasticsearch服务器

**解决方案**：
1. 检查Elasticsearch服务是否正常运行
2. 检查配置文件中的主机地址和端口是否正确
3. 检查网络连接和防火墙设置
4. 检查Elasticsearch的日志文件，查找可能的错误

### 索引问题

**问题**：创建索引失败

**解决方案**：
1. 检查索引名称是否合法（必须全小写，不能包含特殊字符）
2. 检查映射设计是否正确
3. 检查Elasticsearch集群状态是否正常
4. 检查磁盘空间是否充足

### 查询问题

**问题**：查询结果不符合预期

**解决方案**：
1. 使用Elasticsearch的`_analyze` API测试分词效果
2. 检查查询语法是否正确
3. 使用Elasticsearch的`explain` API分析查询过程
4. 调整相关性算法和权重设置

### 性能问题

**问题**：查询响应时间过长

**解决方案**：
1. 优化索引设计，添加合适的字段类型和分析器
2. 使用过滤器缓存减少计算量
3. 增加Elasticsearch节点，提高集群性能
4. 使用慢查询日志定位性能瓶颈
5. 考虑使用索引别名和索引生命周期管理

## 与ThinkPHP 8集成

### 配置文件

在`config/elasticsearch.php`中配置Elasticsearch连接信息：

```php
<?php

return [
    'hosts' => [
        'http://elasticsearch:9200'
    ],
    'apiKey' => env('ELASTICSEARCH_API_KEY', ''),
    'indices' => [
        'users' => [
            'settings' => [
                'number_of_shards' => 1,
                'number_of_replicas' => 0
            ]
        ]
    ]
];
```

### 服务类使用

在项目中使用`ElasticsearchService`类进行索引操作和搜索：

```php
// 创建服务实例
$esService = new \app\service\ElasticsearchService('users');

// 检查索引是否存在
if (!$esService->indexExists()) {
    // 创建索引
    $mappings = [
        'properties' => [
            'name' => [
                'type' => 'text',
                'analyzer' => 'ik_max_word',
                'search_analyzer' => 'ik_smart'
            ],
            // 其他字段...
        ]
    ];
    $esService->createIndex($mappings);
}

// 索引文档
$document = [
    'name' => '张三',
    'email' => 'zhangsan@example.com',
    'age' => 25,
    'country' => '中国',
    'created_at' => date('Y-m-d H:i:s')
];
$esService->indexDocument($document, 1);

// 搜索文档
$result = $esService->search('张三', ['name', 'email']);
```

## 参考资料

1. [Elasticsearch官方文档](https://www.elastic.co/guide/en/elasticsearch/reference/current/index.html)
2. [Elasticsearch PHP客户端文档](https://www.elastic.co/guide/en/elasticsearch/client/php-api/current/index.html)
3. [IK中文分词器](https://github.com/medcl/elasticsearch-analysis-ik)
4. [Elasticsearch查询DSL](https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl.html)