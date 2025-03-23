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

现在开始，你可以使用官方提供的[ThinkChat](https://chat.topthink.com/)，让你在学习ThinkPHP的旅途中享受私人AI助理服务！

![](https://www.topthink.com/uploads/assistant/20230630/4d1a3f0ad2958b49bb8189b7ef824cb0.png)

ThinkPHP生态服务由[顶想云](https://www.topthink.com)（TOPThink Cloud）提供，为生态提供专业的开发者服务和价值之选。

## 文档

[完全开发手册](https://doc.thinkphp.cn)


## 赞助

全新的[赞助计划](https://www.thinkphp.cn/sponsor)可以让你通过我们的网站、手册、欢迎页及GIT仓库获得巨大曝光，同时提升企业的品牌声誉，也更好保障ThinkPHP的可持续发展。

[![](https://www.thinkphp.cn/sponsor/special.svg)](https://www.thinkphp.cn/sponsor/special)

[![](https://www.thinkphp.cn/sponsor.svg)](https://www.thinkphp.cn/sponsor)

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

## 参与开发

直接提交PR或者Issue即可

## 版权信息

ThinkPHP遵循Apache2开源协议发布，并提供免费使用。

本项目包含的第三方源码和二进制文件之版权信息另行标注。

版权所有Copyright © 2006-2024 by ThinkPHP (http://thinkphp.cn) All rights reserved。

ThinkPHP® 商标和著作权所有者为上海顶想信息科技有限公司。

更多细节参阅 [LICENSE.txt](LICENSE.txt)

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
