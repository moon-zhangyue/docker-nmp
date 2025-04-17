# Elasticsearch API 文档

## 概述

本文档详细介绍了系统中与Elasticsearch相关的API接口，包括用户搜索、索引管理等功能。这些接口主要位于`User`控制器中，通过`ElasticsearchService`服务类实现。

## API接口列表

### 1. 基础搜索接口

**接口名称**：用户搜索

**接口路径**：`/user/search`

**请求方式**：GET

**接口描述**：根据关键词搜索用户信息，支持分页。

**请求参数**：

| 参数名 | 类型 | 必填 | 描述 |
| ------ | ---- | ---- | ---- |
| query | string | 是 | 搜索关键词 |
| page | int | 否 | 页码，默认为1 |
| size | int | 否 | 每页记录数，默认为10 |

**返回参数**：

```json
{
  "code": 0,
  "msg": "Search successful",
  "data": {
    "total": 100,
    "users": [
      {
        "_id": "1",
        "name": "张三",
        "email": "zhangsan@example.com",
        "_score": 1.5
      }
    ],
    "page": 1,
    "size": 10
  }
}
```

### 2. 按年龄范围搜索

**接口名称**：按年龄范围搜索用户

**接口路径**：`/user/searchByAge`

**请求方式**：GET

**接口描述**：根据年龄范围搜索用户信息，支持分页。

**请求参数**：

| 参数名 | 类型 | 必填 | 描述 |
| ------ | ---- | ---- | ---- |
| min_age | int | 否 | 最小年龄，默认为18 |
| max_age | int | 否 | 最大年龄，默认为30 |
| page | int | 否 | 页码，默认为1 |
| size | int | 否 | 每页记录数，默认为10 |

**返回参数**：

```json
{
  "code": 0,
  "msg": "Search by age successful",
  "data": {
    "total": 50,
    "users": [
      {
        "_id": "2",
        "name": "李四",
        "email": "lisi@example.com",
        "age": 25,
        "_score": 1.0
      }
    ],
    "page": 1,
    "size": 10
  }
}
```

### 3. 按国家聚合用户数量

**接口名称**：按国家聚合用户数量

**接口路径**：`/user/aggregateByCountry`

**请求方式**：GET

**接口描述**：统计各个国家的用户数量。

**请求参数**：

| 参数名 | 类型 | 必填 | 描述 |
| ------ | ---- | ---- | ---- |
| size | int | 否 | 返回的国家数量，默认为10 |

**返回参数**：

```json
{
  "code": 0,
  "msg": "Aggregation successful",
  "data": [
    {
      "key": "中国",
      "doc_count": 500
    },
    {
      "key": "美国",
      "doc_count": 300
    }
  ]
}
```

### 4. 批量索引用户数据

**接口名称**：批量索引用户数据

**接口路径**：`/user/bulkIndexUsers`

**请求方式**：POST

**接口描述**：批量将用户数据索引到Elasticsearch中。

**请求参数**：

| 参数名 | 类型 | 必填 | 描述 |
| ------ | ---- | ---- | ---- |
| users | array | 是 | 用户数据数组 |

**请求示例**：

```json
{
  "users": [
    {
      "id": "1",
      "name": "张三",
      "email": "zhangsan@example.com",
      "age": 25,
      "country": "中国"
    },
    {
      "id": "2",
      "name": "李四",
      "email": "lisi@example.com",
      "age": 30,
      "country": "中国"
    }
  ]
}
```

**返回参数**：

```json
{
  "code": 0,
  "msg": "批量索引成功",
  "data": {
    "took": 100,
    "items_count": 2
  }
}
```

### 5. 带高亮的搜索功能

**接口名称**：带高亮的搜索功能

**接口路径**：`/user/searchWithHighlight`

**请求方式**：GET

**接口描述**：根据关键词搜索用户信息，并高亮显示匹配的内容。

**请求参数**：

| 参数名 | 类型 | 必填 | 描述 |
| ------ | ---- | ---- | ---- |
| query | string | 是 | 搜索关键词 |
| page | int | 否 | 页码，默认为1 |
| size | int | 否 | 每页记录数，默认为10 |

**返回参数**：

```json
{
  "code": 0,
  "msg": "Search with highlight successful",
  "data": {
    "total": 100,
    "users": [
      {
        "_id": "1",
        "name": "张三",
        "email": "zhangsan@example.com",
        "_score": 1.5,
        "highlight": {
          "name": ["<em>张</em>三"],
          "email": ["<em>zhangsan</em>@example.com"]
        }
      }
    ],
    "page": 1,
    "size": 10
  }
}
```

### 6. 模糊搜索功能

**接口名称**：模糊搜索功能

**接口路径**：`/user/fuzzySearch`

**请求方式**：GET

**接口描述**：根据指定字段进行模糊搜索。

**请求参数**：

| 参数名 | 类型 | 必填 | 描述 |
| ------ | ---- | ---- | ---- |
| field | string | 否 | 搜索字段，默认为name |
| value | string | 是 | 搜索值 |
| page | int | 否 | 页码，默认为1 |
| size | int | 否 | 每页记录数，默认为10 |

**返回参数**：

```json
{
  "code": 0,
  "msg": "Fuzzy search successful",
  "data": {
    "total": 5,
    "users": [
      {
        "_id": "1",
        "name": "张三",
        "email": "zhangsan@example.com",
        "_score": 1.2
      }
    ],
    "page": 1,
    "size": 10
  }
}
```

### 7. 创建或更新用户索引

**接口名称**：创建或更新用户索引

**接口路径**：`/user/indexUser`

**请求方式**：POST

**接口描述**：创建或更新单个用户的索引数据。

**请求参数**：

| 参数名 | 类型 | 必填 | 描述 |
| ------ | ---- | ---- | ---- |
| id | string | 否 | 用户ID，如果提供则更新，否则创建新索引 |
| [其他字段] | mixed | 是 | 用户的其他字段数据 |

**请求示例**：

```json
{
  "id": "1",
  "name": "张三",
  "email": "zhangsan@example.com",
  "age": 25,
  "country": "中国"
}
```

**返回参数**：

```json
{
  "code": 0,
  "msg": "用户索引成功",
  "data": {
    "id": "1",
    "result": "updated"
  }
}
```

## 错误码说明

| 错误码 | 描述 |
| ------ | ---- |
| 0 | 成功 |
| 1 | 失败 |

## 注意事项

1. 所有搜索接口均支持分页，默认每页10条记录。
2. 搜索失败时会返回详细的错误信息。
3. 高亮搜索结果中，匹配的内容会被`<em></em>`标签包围。
4. 批量索引接口支持一次性索引多条用户数据，提高效率。