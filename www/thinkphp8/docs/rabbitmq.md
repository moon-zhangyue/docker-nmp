# ThinkPHP8 RabbitMQ 队列驱动

本文档介绍了如何在 ThinkPHP8 中使用 RabbitMQ 作为消息队列驱动。

## 简介

RabbitMQ 是一个开源的消息代理软件，它实现了高级消息队列协议（AMQP）。RabbitMQ 提供了可靠的消息传递、路由、队列和发布/订阅功能，非常适合构建分布式系统和微服务架构。

本扩展为 ThinkPHP8 提供了 RabbitMQ 队列驱动，使您可以轻松地在应用中使用 RabbitMQ 作为消息队列。

## 安装

### 1. 安装 PHP AMQP 扩展

首先，您需要安装 PHP AMQP 扩展。可以通过 Composer 安装：

```bash
composer require php-amqplib/php-amqplib
```

### 2. 配置队列

在 `config/queue.php` 中添加 RabbitMQ 连接配置：

```php
'rabbitmq' => [  // RabbitMQ 队列配置
    'type'          => 'RabbitMQ',  // 队列类型为 RabbitMQ
    'host'          => env('RABBITMQ_HOST', 'localhost'),  // RabbitMQ 服务器地址
    'port'          => env('RABBITMQ_PORT', 5672),  // RabbitMQ 服务器端口
    'login'         => env('RABBITMQ_LOGIN', 'guest'),  // RabbitMQ 登录用户名
    'password'      => env('RABBITMQ_PASSWORD', 'guest'),  // RabbitMQ 登录密码
    'vhost'         => env('RABBITMQ_VHOST', '/'),  // RabbitMQ 虚拟主机
    'queue'         => env('RABBITMQ_QUEUE', 'default'),  // 默认队列名称
    'exchange'      => env('RABBITMQ_EXCHANGE', 'default'),  // 交换机名称
    'exchange_type' => env('RABBITMQ_EXCHANGE_TYPE', 'direct'),  // 交换机类型
    'passive'       => false,  // 是否检查队列/交换机是否存在而不创建
    'durable'       => true,   // 是否持久化
    'exclusive'     => false,  // 是否排他
    'auto_delete'   => false,  // 是否自动删除
    'prefetch_count'=> env('RABBITMQ_PREFETCH_COUNT', 1),  // 预取数量
    'prefetch_size' => 0,  // 预取大小
    'global_qos'    => false,  // 是否全局QoS
    'delayed_exchange' => env('RABBITMQ_DELAYED_EXCHANGE', 'delayed'),  // 延迟交换机名称
    'queue_arguments' => [],  // 队列参数
],
```

### 3. 注册服务提供者

在 `config/provider.php` 中注册 RabbitMQ 服务提供者：

```php
return [
    // ...
    app\provider\RabbitMQServiceProvider::class,
];
```

## 使用方法

### 1. 创建任务类

创建一个任务处理类，例如：

```php
<?php
namespace app\job;

use think\facade\Log;
use think\queue\Job;

class RabbitMQJob
{
    public function fire(Job $job, $data): void
    {
        try {
            // 记录任务开始处理
            Log::info('RabbitMQJob开始处理', [
                'job_id' => $job->getJobId(),
                'data' => $data
            ]);

            // 处理任务逻辑
            // ...

            // 标记任务为已完成
            $job->delete();

            // 记录任务完成
            Log::info('RabbitMQJob处理完成', [
                'job_id' => $job->getJobId(),
                'execution_time' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            // 记录异常
            Log::error('RabbitMQJob处理异常', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'job_id' => $job->getJobId(),
                'data' => $data
            ]);

            // 如果有尝试次数，延迟重试
            $attempts = $job->attempts();
            if ($attempts < 3) {
                // 重试间隔成倍增加
                $delay = pow(2, $attempts);
                $job->release($delay);
            } else {
                // 尝试次数过多，删除任务
                $job->delete();
            }
        }
    }

    // 任务失败处理
    public function failed($data): void
    {
        // 记录任务失败
        Log::error('RabbitMQJob任务最终失败', [
            'data' => $data,
            'failure_time' => date('Y-m-d H:i:s')
        ]);
    }
}
```

### 2. 发送消息到队列

#### 使用队列门面

```php
use think\facade\Queue;

// 发送消息到队列
$data = ['id' => 1, 'name' => 'test'];
Queue::connection('rabbitmq')->push('app\job\RabbitMQJob', $data);

// 发送延迟消息到队列（延迟10秒）
Queue::connection('rabbitmq')->later(10, 'app\job\RabbitMQJob', $data);
```

#### 使用生产者服务

```php
use app\service\queue\RabbitMQProducer;

// 创建生产者实例
$producer = new RabbitMQProducer();

// 发送消息到队列
$data = ['id' => 1, 'name' => 'test'];
$producer->send('app\job\RabbitMQJob', $data);

// 发送延迟消息到队列（延迟10秒）
$producer->sendLater(10, 'app\job\RabbitMQJob', $data);

// 批量发送消息
$messages = [
    ['job' => 'app\job\RabbitMQJob', 'data' => ['id' => 1]],
    ['job' => 'app\job\RabbitMQJob', 'data' => ['id' => 2]],
];
$producer->batchSend($messages);
```

### 3. 消费队列消息

#### 使用命令行工具

```bash
# 启动消费者处理默认队列
php think rabbitmq:consume

# 指定队列名称
php think rabbitmq:consume --queue=my_queue

# 指定连接名称
php think rabbitmq:consume --connection=rabbitmq

# 设置内存限制（MB）
php think rabbitmq:consume --memory=256
```

#### 使用消费者服务

```php
use app\service\queue\RabbitMQConsumer;

// 创建消费者实例
$consumer = new RabbitMQConsumer([
    'host' => 'localhost',
    'port' => 5672,
    'login' => 'guest',
    'password' => 'guest',
    'vhost' => '/',
    'queue' => 'default',
    'exchange' => 'default',
    'exchange_type' => 'direct',
    'prefetch_count' => 1,
]);

// 消费消息
$consumer->consume(function ($body, $message) {
    // 处理消息
    $job = $body['job'] ?? null;
    $data = $body['data'] ?? [];

    if (empty($job)) {
        return false;
    }

    try {
        // 创建任务处理类实例
        $instance = app()->make($job);

        // 模拟Job对象
        $mockJob = new class($body, $message) {
            // ...
        };

        // 调用任务处理方法
        $instance->fire($mockJob, $data);

        return true;
    } catch (\Exception $e) {
        return false;
    }
});
```

## 高级特性

### 1. 延迟队列

RabbitMQ 驱动支持延迟队列，可以通过 `later` 方法发送延迟消息：

```php
// 延迟10秒执行
Queue::connection('rabbitmq')->later(10, 'app\job\RabbitMQJob', $data);
```

### 2. 消息确认

RabbitMQ 驱动支持消息确认机制，确保消息被正确处理：

- 当任务处理成功时，调用 `$job->delete()` 确认消息
- 当任务需要重试时，调用 `$job->release($delay)` 将消息重新放回队列

### 3. 消息持久化

RabbitMQ 驱动默认启用消息持久化，确保消息在 RabbitMQ 服务器重启后不会丢失。

### 4. 预取数量

可以通过 `prefetch_count` 配置项设置预取数量，控制消费者一次获取的消息数量，避免消费者过载。

## 故障排除

### 1. 连接问题

如果遇到连接问题，请检查：

- RabbitMQ 服务器是否正在运行
- 连接配置（主机、端口、用户名、密码、虚拟主机）是否正确
- 网络连接是否正常

### 2. 队列或交换机不存在

如果遇到队列或交换机不存在的错误，请检查：

- 队列和交换机名称是否正确
- 是否启用了 `passive` 选项（该选项会检查队列/交换机是否存在而不创建）

### 3. 消息未被处理

如果消息未被处理，请检查：

- 消费者是否正在运行
- 消息是否发送到了正确的队列
- 任务处理类是否存在并正确实现

## 参考资料

- [RabbitMQ 官方文档](https://www.rabbitmq.com/documentation.html)
- [php-amqplib 文档](https://github.com/php-amqplib/php-amqplib)
- [ThinkPHP 队列文档](https://www.kancloud.cn/manual/thinkphp6_0/1037616)
