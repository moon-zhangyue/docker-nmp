![](https://www.thinkphp.cn/uploads/images/20230630/300c856765af4d8ae758c503185f8739.png)

ThinkPHP 8
===============

## 特性

* 基于PHP`8.0+`重构
* 升级`PSR`依赖
* 依赖`think-orm`3.0+版本
* 全新的`think-dumper`服务，支持远程调试
* 支持`6.0`/`6.1`无缝升级

> ThinkPHP8的运行环境要求PHP8.0+

## 文档

[完全开发手册](https://doc.thinkphp.cn)


## 安装

~~~
composer create-project topthink/think tp
~~~

启动服务

~~~
cd tp
php think run
~~~

然后就可以在浏览器中访问

~~~
http://localhost:8000
~~~

如果需要更新框架使用
~~~
composer update topthink/framework
~~~

## 命名规范

`ThinkPHP`遵循PSR-2命名规范和PSR-4自动加载规范。


# ThinkPHP 8 多租户队列使用指南

这个项目演示了如何在ThinkPHP 8中使用多租户队列功能，支持不同租户的任务隔离和资源管理。

## 功能特点

- 多租户隔离：不同租户的队列任务互相隔离
- 租户配置管理：支持为不同租户配置不同的队列参数
- 支持延迟任务：可以创建延迟执行的队列任务
- 错误重试机制：任务执行失败时自动重试
- 支持Redis和Kafka作为队列驱动

## 使用方法

### 1. 创建租户

使用`TenantManager`创建新的租户：

```php
// 获取租户管理器实例
$manager = TenantManager::getInstance();

// 创建新租户配置
$config = [
    'redis' => [
        'host' => 'redis',
        'port' => 6379,
        'password' => '',
        'select' => 0,
    ],
    'kafka' => [
        'brokers' => ['kafka:9092'],
        'group_id' => 'think-queue-tenant-1',
        'topics' => ['default'],
    ]
];

// 创建租户
$result = $manager->createTenant('tenant-1', $config);
```

### 2. 设置当前租户

```php
// 设置当前租户
$manager->setCurrentTenant('tenant-1');
```

### 3. 创建租户特定的队列任务

```php
// 获取租户特定的队列名称
$queueName = $manager->getTenantSpecificTopic('tenant-1', 'default');

// 推送任务到队列
$jobData = [
    'tenant_id' => 'tenant-1',
    'task_type' => 'process_data',
    'created_at' => date('Y-m-d H:i:s')
];
$isPushed = Queue::push('app\job\TenantAwareJob', $jobData, $queueName);
```

### 4. 创建延迟任务

```php
// 60秒后执行
$delay = 60;
$isPushed = Queue::later($delay, 'app\job\TenantAwareJob', $jobData, $queueName);
```

## 任务处理类实现

创建一个支持多租户的任务处理类：

```php
namespace app\job;

use think\facade\Log;
use think\queue\Job;
use think\queue\tenant\TenantManager;

class TenantAwareJob
{
    public function fire(Job $job, $data): void
    {
        try {
            // 获取租户ID
            $tenantId = $data['tenant_id'] ?? 'default';
            
            // 获取租户管理器实例
            $manager = TenantManager::getInstance();
            
            // 设置当前租户
            $manager->setCurrentTenant($tenantId);
            
            // 获取租户配置
            $tenantConfig = $manager->getTenantConfig($tenantId);
            
            // 执行租户特定的业务逻辑
            // ...
            
            // 标记任务为已完成
            $job->delete();
        } catch (\Exception $e) {
            // 异常处理和重试逻辑
            // ...
        }
    }
}
```

## 示例代码

项目中包含以下示例：

- `app/controller/TenantQueueExample.php` - 多租户队列控制器示例
- `app/controller/QueueTaskExample.php` - 基本队列控制器示例
- `app/job/TenantAwareJob.php` - 多租户任务处理类
- `app/job/TestJob.php` - 基本任务处理类

## 启动队列处理

启动队列监听程序：

```bash
# 启动默认队列处理
php think queue:work

# 启动指定队列处理
php think queue:work --queue=tenant-1-default

# 指定连接和队列
php think queue:work --connection=redis --queue=tenant-1-default

# 守护进程模式
php think queue:work --daemon
```

## 测试队列功能

1. 访问 `/tenant_queue_example/create_tenant_and_queue` 创建租户和队列任务
2. 访问 `/tenant_queue_example/create_delayed_task` 创建延迟任务
3. 访问 `/queue_task_example/create_task` 创建普通队列任务

## 配置说明

在`config/queue.php`中配置队列：

```php
return [
    'default'     => 'redis',
    'connections' => [
        'redis' => [
            'type'       => 'redis',
            'host'       => 'redis',
            'port'       => 6379,
            'password'   => '',
            'select'     => 0,
            'timeout'    => 0,
            'persistent' => false,
        ],
        'kafka' => [
            'type'       => 'kafka',
            'brokers'    => ['kafka:9092'],
            'group_id'   => 'think-queue',
            'topics'     => ['default'],
        ],
    ],
];
```

# ThinkPHP8 Kafka 管理系统

基于ThinkPHP8框架开发的Kafka消息队列管理系统，提供Kafka主题管理、消费者监控、队列管理等功能，集成JWT认证和审计日志。

## 功能特性

- **认证与授权**：基于JWT的用户认证系统，支持多角色权限控制
- **Kafka管理**：主题创建、删除，消费者组监控
- **连接池管理**：高效管理Kafka连接资源，优化性能
- **系统监控**：实时监控系统资源、Kafka状态和队列指标
- **队列管理**：消息发送、死信队列处理、队列状态监控
- **审计日志**：记录关键操作，追踪用户行为

## 系统架构

系统采用前后端分离架构：

- 后端：ThinkPHP 8 + RdKafka扩展
- 前端：可对接Vue/React等前端框架（前端代码需另行开发）
- 数据库：支持MySQL/MariaDB
- 缓存：Redis

## 安装与配置

### 系统要求

- PHP >= 8.0
- MySQL >= 5.7 或 MariaDB >= 10.3
- Redis >= 4.0
- RdKafka PHP扩展
- Composer

### 安装步骤

1. 克隆代码库

```bash
git clone https://your-repository/thinkphp8-kafka-manager.git
cd thinkphp8-kafka-manager
```

2. 安装依赖

```bash
composer install
```

3. 配置环境

复制`.example.env`为`.env`并修改数据库、Redis、Kafka等配置：

```bash
cp .example.env .env
```

4. 数据库迁移与初始化

```bash
php think migrate:run
php think seed:run
```

5. 启动服务

```bash
php think run
```

## API接口文档

系统提供以下主要API接口：

### 认证相关

| 接口            | 方法   | 描述         | 所需权限 |
|---------------|------|------------|------|
| /api/auth/login | POST | 用户登录       | 无    |
| /api/auth/register | POST | 用户注册       | 无    |
| /api/auth/refresh | POST | 刷新令牌       | 无    |
| /api/auth/logout | POST | 用户登出       | 用户   |
| /api/auth/me   | GET  | 获取当前用户信息   | 用户   |

### Kafka管理

| 接口                  | 方法   | 描述         | 所需权限   |
|---------------------|------|------------|--------|
| /api/kafka/topics    | GET  | 获取所有主题     | 查看者+   |
| /api/kafka/topics/create | POST | 创建主题       | 管理员    |
| /api/kafka/topics/delete | POST | 删除主题       | 管理员    |
| /api/kafka/brokers   | GET  | 获取所有Broker  | 查看者+   |

### 队列管理

| 接口                        | 方法   | 描述         | 所需权限   |
|---------------------------|------|------------|--------|
| /api/queue/connections     | GET  | 获取所有队列连接   | 查看者+   |
| /api/queue/connections/update | POST | 更新队列连接配置   | 管理员    |
| /api/queue/status         | GET  | 获取队列状态     | 查看者+   |
| /api/queue/push           | POST | 推送消息到队列    | 操作员+   |
| /api/queue/dead-letters    | GET  | 获取死信队列消息   | 查看者+   |
| /api/queue/dead-letters/clear | POST | 清空死信队列     | 管理员    |
| /api/queue/dead-letters/retry | POST | 重试死信队列消息   | 操作员+   |

### 系统监控

| 接口                    | 方法  | 描述        | 所需权限   |
|-----------------------|-----|-----------|--------|
| /api/monitoring/metrics | GET | 获取系统指标    | 查看者+   |
| /api/monitoring/health  | GET | 获取系统健康状态  | 无      |
| /api/monitoring/consumers | GET | 获取消费者状态   | 查看者+   |

### 连接池管理

| 接口                | 方法   | 描述        | 所需权限   |
|-------------------|------|-----------|--------|
| /api/pool/status   | GET  | 获取连接池状态   | 操作员+   |
| /api/pool/config   | POST | 更新连接池配置   | 管理员    |

## 用户角色与权限

系统定义了以下角色：

- **管理员(admin)**：拥有所有权限，可以管理系统配置、用户、主题等
- **操作员(operator)**：可以执行数据操作，如发送消息、重试失败消息等
- **查看者(viewer)**：只有查看权限，不能执行修改操作

## 开发与扩展

### 目录结构

```
├── app                     # 应用目录
│   ├── controller          # 控制器
│   │   └── api             # API控制器
│   ├── middleware          # 中间件
│   ├── model               # 数据模型
│   └── service             # 业务服务
├── config                  # 配置目录
├── extend                  # 扩展目录
│   └── think               # ThinkPHP扩展
│       └── queue           # 队列扩展
│           └── pool        # 连接池实现
├── public                  # 公共目录
├── route                   # 路由目录
└── runtime                 # 运行时目录
```

### 扩展开发

1. 添加新的控制器

```bash
php think make:controller api/YourController
```

2. 添加新的模型

```bash
php think make:model YourModel
```

3. 添加新的中间件

```bash
php think make:middleware YourMiddleware
```

## 安全建议

1. 生产环境中修改JWT密钥，设置为复杂随机字符串
2. 使用HTTPS保护API请求
3. 定期更换密码和令牌
4. 对关键操作添加日志记录

## 性能优化

1. 调整连接池参数，根据服务器负载情况设置合适的连接数
2. 启用压缩，减少网络传输量
3. 对大量并发请求场景，考虑使用Swoole提供高性能服务

## 常见问题

**Q: 连接Kafka失败怎么办？**

A: 检查Kafka服务器地址、端口是否正确，防火墙是否开放对应端口。

**Q: JWT令牌过期无法使用？**

A: 使用刷新令牌API获取新的访问令牌。

**Q: 如何添加新用户角色？**

A: 修改`app/model/User.php`中的角色定义，并在`config/jwt.php`中添加对应的权限配置。

## 许可证

MIT

## 联系方式

如有问题或建议，请提交Issue或联系开发团队。
