# ClickHouse 使用指南

## 简介

ClickHouse 是一个用于联机分析(OLAP)的列式数据库管理系统。它能够使用SQL查询实时生成分析数据报告，速度非常快。

## 基本信息

- HTTP接口: http://localhost:8123
- TCP端口: 9000
- 默认用户: default
- 默认密码: clickhouse

## 启动服务

```bash
# 启动所有服务
docker-compose up -d

# 仅启动ClickHouse服务
docker-compose up -d clickhouse
```

## 连接到ClickHouse

### 使用HTTP接口

```bash
# 通过curl发送查询
curl 'http://localhost:8123/?query=SELECT%201'

# 带认证的查询
curl 'http://localhost:8123/?user=default&password=clickhouse&query=SELECT%201'
```

### 使用命令行客户端

```bash
# 进入ClickHouse容器
docker-compose exec clickhouse clickhouse-client --user default --password clickhouse

# 或者直接执行查询
docker-compose exec clickhouse clickhouse-client --user default --password clickhouse --query "SELECT 1"
```

## 基本操作示例

### 创建数据库

```sql
CREATE DATABASE IF NOT EXISTS example;
```

### 创建表

```sql
CREATE TABLE IF NOT EXISTS example.events
(
    event_date Date,
    event_type String,
    user_id UInt32,
    message String
)
ENGINE = MergeTree()
PARTITION BY toYYYYMM(event_date)
ORDER BY (event_date, event_type, user_id);
```

### 插入数据

```sql
INSERT INTO example.events VALUES
    ('2023-01-01', 'click', 101, 'Button clicked'),
    ('2023-01-01', 'view', 102, 'Page viewed'),
    ('2023-01-02', 'click', 101, 'Another button clicked');
```

### 查询数据

```sql
SELECT * FROM example.events;

SELECT 
    event_date,
    event_type,
    count() AS count
FROM example.events
GROUP BY event_date, event_type
ORDER BY event_date, event_type;
```

## 与其他服务集成

### 与PHP集成

在PHP中，您可以使用官方的ClickHouse PHP客户端或PDO驱动程序连接到ClickHouse：

```php
// 使用官方客户端
$config = [
    'host' => 'clickhouse',  // 使用容器名称作为主机名
    'port' => 8123,
    'username' => 'default',
    'password' => 'clickhouse'
];

$db = new ClickHouseDB\Client($config);
$db->database('example');
$statement = $db->select('SELECT * FROM events');
```

### 与其他服务的集成

ClickHouse可以与Kafka、MySQL、PostgreSQL等服务集成：

- 从Kafka导入数据：使用Kafka引擎表
- 从MySQL/PostgreSQL同步数据：使用MaterializedMySQL引擎或JDBC引擎

## 性能优化建议

1. 合理设计表结构和分区键
2. 使用适当的表引擎（MergeTree系列引擎通常性能最好）
3. 避免使用JOIN和子查询，尽量使用物化视图
4. 使用适当的数据类型（例如，使用LowCardinality优化字符串）
5. 定期进行数据优化和合并

## 监控与管理

您可以使用system表来监控ClickHouse的状态：

```sql
-- 查看当前查询
SELECT * FROM system.processes;

-- 查看表的大小
SELECT 
    database,
    table,
    formatReadableSize(sum(bytes)) as size
FROM system.parts
GROUP BY database, table
ORDER BY sum(bytes) DESC;
```

## 更多资源

- [ClickHouse官方文档](https://clickhouse.com/docs/)
- [ClickHouse GitHub仓库](https://github.com/ClickHouse/ClickHouse)
