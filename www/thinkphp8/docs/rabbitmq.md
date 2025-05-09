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
    'publisher_confirms' => env('RABBITMQ_PUBLISHER_CONFIRMS', false),  // 是否启用发布者确认模式
    'wait_for_confirm'   => env('RABBITMQ_WAIT_FOR_CONFIRM', false),    // 是否等待发布者确认
    'confirm_timeout'    => env('RABBITMQ_CONFIRM_TIMEOUT', 5.0),       // 发布者确认超时时间（秒）
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

### 3. 发布者确认机制

RabbitMQ 驱动支持发布者确认机制，确保消息成功发送到 RabbitMQ 服务器：

```php
// 在配置文件中启用发布者确认
// config/queue.php
'rabbitmq' => [
    // ...其他配置...
    'publisher_confirms' => true,  // 启用发布者确认模式
    'wait_for_confirm'   => true,  // 是否等待确认（同步模式）
    'confirm_timeout'    => 5.0,   // 等待确认的超时时间（秒）
],

// 使用生产者服务时启用发布者确认
$producer = new RabbitMQProducer(
    'default',    // 队列名称
    'rabbitmq',   // 连接名称
    true,         // 启用发布者确认
    true,         // 等待确认
    5.0           // 确认超时时间
);

// 发送消息
$producer->send('app\job\RabbitMQJob', $data);
```

发布者确认模式有两种工作方式：

1. **同步模式**：设置 `wait_for_confirm` 为 `true`，发送消息后会等待服务器确认，直到收到确认或超时。
2. **异步模式**：设置 `wait_for_confirm` 为 `false`，发送消息后立即返回，通过回调函数处理确认结果。

发布者确认机制可以大幅提高消息可靠性，确保消息不会在发送过程中丢失。

### 4. 消息持久化

RabbitMQ 驱动默认启用消息持久化，确保消息在 RabbitMQ 服务器重启后不会丢失。

### 5. 预取数量

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

# RabbitMQ工作模式详解

本文档详细介绍了RabbitMQ的六种工作模式，包括每种模式的原理、适用场景、示例代码和使用方法。

## 目录

1. [Simple简单模式](https://www.rabbitmq.com/tutorials/tutorial-one-php.html)
2. [Work工作模式](https://www.rabbitmq.com/tutorials/tutorial-two-php.html)
3. [Publish/Subscribe发布订阅模式](https://www.rabbitmq.com/tutorials/tutorial-three-php)
4. [Routing路由模式](https://www.rabbitmq.com/tutorials/tutorial-four-php)
5. [Topic主题模式](https://www.rabbitmq.com/tutorials/tutorial-five-php)
6. [RPC远程过程调用模式](https://www.rabbitmq.com/tutorials/tutorial-six-php)
7. [如何运行示例](https://www.rabbitmq.com/tutorials/tutorial-seven-php)

## 1. Simple简单模式

### 1.1 原理

简单模式是RabbitMQ最基础的消息模式，一个生产者对应一个消费者，生产者发送消息到队列，消费者从队列中获取消息并处理。

![Simple模式](https://www.rabbitmq.com/tutorials/tutorial-one-python.html)

### 1.2 适用场景

- 简单的消息传递场景
- 单一生产者和单一消费者
- 不需要消息路由或广播
- 适合简单的异步任务处理

### 1.3 示例代码

#### 生产者

```php
// 创建Simple模式实例
$simpleMode = new SimpleMode();

// 发送消息
$message = "这是一条测试消息 - " . date('Y-m-d H:i:s');
$result = $simpleMode->send($message);

if ($result) {
    echo "消息发送成功\n";
} else {
    echo "消息发送失败\n";
}
```

#### 消费者

```php
// 创建Simple模式实例
$simpleMode = new SimpleMode();

// 接收并处理消息
$simpleMode->receive(function ($message) {
    echo "收到消息: $message\n";

    // 处理消息...

    return true; // 返回true表示处理成功
});
```

### 1.4 关键特点

- 一个生产者，一个消费者
- 消息只会被消费一次
- 队列持久化可以防止消息丢失
- 简单直接，无需复杂配置

## 2. Work工作模式

### 2.1 原理

工作模式（Work Queues）是一个生产者对应多个消费者，每个消费者获取到的消息是唯一的，多个消费者共同消费同一个队列中的消息，实现任务的分发和负载均衡。

![Work模式](https://www.rabbitmq.com/tutorials/tutorial-two-python.html)

### 2.2 适用场景

- 需要处理耗时任务
- 需要在多个工作者之间分配任务
- 负载均衡场景
- 处理资源密集型操作

### 2.3 示例代码

#### 生产者

```php
// 创建Work模式实例
$workMode = new WorkMode();

// 发送任务
$message = "Task #1 - " . date('Y-m-d H:i:s');
$complexity = 5; // 任务复杂度（模拟处理时间）
$result = $workMode->sendTask($message, $complexity);

if ($result) {
    echo "任务发送成功\n";
} else {
    echo "任务发送失败\n";
}

// 批量发送任务
$results = $workMode->batchSendTasks(10);
echo "批量发送结果：" . json_encode($results) . "\n";
```

#### 消费者（工作者）

```php
// 创建Work模式实例
$workMode = new WorkMode();

// 启动工作者处理任务
$workMode->worker('worker-1', function ($data, $workerName) {
    $message = $data['message'] ?? 'unknown';
    $complexity = $data['complexity'] ?? 1;

    echo "[$workerName] 处理任务: $message, 复杂度: $complexity\n";

    // 模拟任务处理时间，复杂度越高耗时越长
    sleep($complexity);

    echo "[$workerName] 完成任务: $message\n";

    return true; // 返回true表示处理成功
});
```

### 2.4 关键特点

- 一个生产者，多个消费者
- 消息只会被一个消费者处理
- 通过设置prefetch_count控制消息分发
- 实现任务的公平分发
- 通过确认机制确保任务不会丢失

## 3. Publish/Subscribe发布订阅模式

### 3.1 原理

发布/订阅模式下，一个生产者发送的消息会被多个消费者接收。每个消费者都会收到完全相同的消息副本。这种模式使用fanout类型的交换机将消息广播到所有绑定的队列中。

![Publish/Subscribe模式](https://www.rabbitmq.com/tutorials/tutorial-three-python.html)

### 3.2 适用场景

- 广播消息到多个接收者
- 事件通知系统
- 日志收集与分发
- 实时数据更新推送

### 3.3 示例代码

#### 生产者

```php
// 创建发布/订阅模式实例
$pubSubMode = new PublishSubscribeMode();

// 发布消息
$message = "广播消息 - " . date('Y-m-d H:i:s');
$result = $pubSubMode->publish($message);

if ($result) {
    echo "消息发布成功\n";
} else {
    echo "消息发布失败\n";
}

// 批量发布消息
$results = $pubSubMode->batchPublish(3);
echo "批量发布结果：" . json_encode($results) . "\n";
```

#### 消费者（订阅者）

```php
// 创建发布/订阅模式实例
$pubSubMode = new PublishSubscribeMode();

// 订阅者1 - 记录所有日志
$pubSubMode->subscribe('logger', function ($message, $subscriberName) {
    echo "[$subscriberName] 接收到消息: $message\n";
    file_put_contents('./logs/all_logs.log', "[$subscriberName] $message\n", FILE_APPEND);
    return true;
});

// 订阅者2 - 记录重要日志
$pubSubMode->subscribe('important-logger', function ($message, $subscriberName) {
    echo "[$subscriberName] 接收到消息: $message\n";
    if (strpos($message, 'important') !== false) {
        file_put_contents('./logs/important_logs.log', "[$subscriberName] $message\n", FILE_APPEND);
    }
    return true;
});
```

### 3.4 关键特点

- 一个生产者，多个消费者
- 每个消费者都会收到相同的消息
- 使用fanout类型交换机
- 临时队列自动创建和删除
- 消费者断开连接后队列自动删除

## 4. Routing路由模式

### 4.1 原理

路由模式允许生产者通过路由键（routing key）将消息发送到特定的队列。消费者可以选择性地接收自己感兴趣的消息。这种模式使用direct类型的交换机，根据路由键将消息路由到对应的队列。

![Routing模式](https://www.rabbitmq.com/tutorials/tutorial-four-python.html)

### 4.2 适用场景

- 根据消息类型选择性接收
- 日志级别过滤
- 按照特定条件分发消息
- 多环境部署中的消息路由

### 4.3 示例代码

#### 生产者

```php
// 创建路由模式实例
$routingMode = new RoutingMode();

// 发布不同级别的日志消息
$routingMode->publish("普通信息日志", "info");
$routingMode->publish("警告日志", "warning");
$routingMode->publish("错误日志", "error");
$routingMode->publish("严重错误日志", "critical");

// 批量发布日志
$results = $routingMode->publishLogs();
echo "批量发布结果：" . json_encode($results) . "\n";
```

#### 消费者

```php
// 创建路由模式实例
$routingMode = new RoutingMode();

// 消费者1 - 只处理错误和严重错误日志
$routingMode->subscribe('error-handler', ['error', 'critical'], function ($message, $routingKey, $consumerName) {
    echo "[$consumerName] 接收到[$routingKey]级别消息: $message\n";
    file_put_contents('./logs/errors.log', "[$routingKey] $message\n", FILE_APPEND);
    return true;
});

// 消费者2 - 处理所有日志
$routingMode->subscribe('all-logs-handler', ['info', 'warning', 'error', 'critical'], function ($message, $routingKey, $consumerName) {
    echo "[$consumerName] 接收到[$routingKey]级别消息: $message\n";
    file_put_contents('./logs/all.log', "[$routingKey] $message\n", FILE_APPEND);
    return true;
});

// 消费者3 - 只处理警告日志
$routingMode->subscribe('warning-handler', ['warning'], function ($message, $routingKey, $consumerName) {
    echo "[$consumerName] 接收到[$routingKey]级别消息: $message\n";
    file_put_contents('./logs/warnings.log', "[$routingKey] $message\n", FILE_APPEND);
    return true;
});
```

### 4.4 关键特点

- 使用direct类型交换机
- 通过路由键进行消息过滤
- 消费者可以订阅多个路由键
- 精确匹配路由键
- 可以实现消息的选择性接收

## 5. Topic主题模式

### 5.1 原理

主题模式是路由模式的扩展，允许使用通配符进行更灵活的路由匹配。路由键必须是由点分隔的单词列表，如"stock.usd.nyse"。支持两种通配符：
- `*` 表示匹配一个单词
- `#` 表示匹配零个或多个单词

![Topic模式](https://www.rabbitmq.com/tutorials/tutorial-five-python.html)

### 5.2 适用场景

- 复杂的消息路由需求
- 多条件、多维度的消息过滤
- 层次化的消息分类
- 灵活的消息订阅机制

### 5.3 示例代码

#### 生产者

```php
// 创建主题模式实例
$topicMode = new TopicMode();

// 发布不同主题的消息
$topicMode->publish("应用严重错误", "app.error.critical");
$topicMode->publish("应用警告错误", "app.error.warning");
$topicMode->publish("内核致命错误", "kernel.error.fatal");
$topicMode->publish("MySQL数据库错误", "database.mysql.error");

// 批量发布主题消息
$results = $topicMode->publishTopicMessages();
echo "批量发布结果：" . json_encode($results) . "\n";
```

#### 消费者

```php
// 创建主题模式实例
$topicMode = new TopicMode();

// 消费者1 - 处理所有错误消息（使用#通配符）
$topicMode->subscribe('error-handler', ['*.error.#'], function ($message, $routingKey, $consumerName) {
    echo "[$consumerName] 接收到[$routingKey]消息: $message\n";
    file_put_contents('./logs/all_errors.log', "[$routingKey] $message\n", FILE_APPEND);
    return true;
});

// 消费者2 - 只处理应用相关消息
$topicMode->subscribe('app-monitor', ['app.#'], function ($message, $routingKey, $consumerName) {
    echo "[$consumerName] 接收到[$routingKey]消息: $message\n";
    file_put_contents('./logs/app_logs.log', "[$routingKey] $message\n", FILE_APPEND);
    return true;
});

// 消费者3 - 处理所有严重错误（使用*通配符）
$topicMode->subscribe('critical-monitor', ['*.*.critical'], function ($message, $routingKey, $consumerName) {
    echo "[$consumerName] 接收到[$routingKey]消息: $message\n";
    file_put_contents('./logs/critical.log', "[$routingKey] $message\n", FILE_APPEND);
    return true;
});

// 消费者4 - 处理所有数据库相关消息
$topicMode->subscribe('db-monitor', ['database.#'], function ($message, $routingKey, $consumerName) {
    echo "[$consumerName] 接收到[$routingKey]消息: $message\n";
    file_put_contents('./logs/database.log', "[$routingKey] $message\n", FILE_APPEND);
    return true;
});
```

### 5.4 关键特点

- 使用topic类型交换机
- 支持通配符匹配路由键
- 路由键必须是点分隔的单词列表
- 比direct交换机更灵活
- 可以模拟fanout和direct交换机的行为

## 6. RPC远程过程调用模式

### 6.1 原理

RPC（远程过程调用）模式允许客户端发送请求并等待响应。客户端发送带有回调队列和关联ID的消息，服务端处理后将结果发送回回调队列。客户端通过关联ID将响应与请求匹配。

![RPC模式](https://www.rabbitmq.com/tutorials/tutorial-six-python.html)

### 6.2 适用场景

- 需要请求-响应模式的场景
- 分布式计算
- 微服务间的同步调用
- 远程函数调用

### 6.3 示例代码

#### 服务端

```php
// 创建RPC模式实例
$rpcMode = new RPCMode();

// 启动RPC服务端
$rpcMode->serve(function ($data) {
    echo "收到RPC请求: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n";

    // 模拟处理请求
    if (isset($data['operation']) && isset($data['numbers']) && is_array($data['numbers'])) {
        switch ($data['operation']) {
            case 'sum':
                return array_sum($data['numbers']);
            case 'multiply':
                return array_product($data['numbers']);
            case 'max':
                return max($data['numbers']);
            case 'min':
                return min($data['numbers']);
            default:
                throw new \Exception("不支持的操作: " . $data['operation']);
        }
    }

    throw new \Exception("无效的请求数据");
});
```

#### 客户端

```php
// 创建RPC模式实例
$rpcMode = new RPCMode();

try {
    // 求和操作
    $sumResult = $rpcMode->call([
        'operation' => 'sum',
        'numbers'   => [1, 2, 3, 4, 5]
    ]);

    echo "求和结果: " . ($sumResult['result'] ?? 'N/A') . "\n";

    // 乘积操作
    $multiplyResult = $rpcMode->call([
        'operation' => 'multiply',
        'numbers'   => [2, 3, 4]
    ]);

    echo "乘积结果: " . ($multiplyResult['result'] ?? 'N/A') . "\n";

    // 最大值操作
    $maxResult = $rpcMode->call([
        'operation' => 'max',
        'numbers'   => [10, 5, 8, 15, 3]
    ]);

    echo "最大值结果: " . ($maxResult['result'] ?? 'N/A') . "\n";
} catch (\Exception $e) {
    echo "RPC调用失败: " . $e->getMessage() . "\n";
}
```

### 6.4 关键特点

- 请求-响应模式
- 使用关联ID匹配请求和响应
- 使用临时回调队列接收响应
- 支持超时机制
- 可以实现同步调用

## 7. 如何运行示例

### 7.1 前提条件

- 安装并配置好RabbitMQ服务器
- 确保项目中已安装php-amqplib库：`composer require php-amqplib/php-amqplib`
- 配置好RabbitMQ连接信息（在config/queue.php中）

### 7.2 通过HTTP接口测试

项目中已配置了HTTP接口，可以通过以下URL测试各种工作模式：

- 简单模式: `/rabbitmq-examples/simple`
- 工作模式: `/rabbitmq-examples/work?count=5`
- 发布/订阅模式: `/rabbitmq-examples/publish-subscribe?count=3`
- 路由模式: `/rabbitmq-examples/routing`
- 主题模式: `/rabbitmq-examples/topic`
- RPC模式: `/rabbitmq-examples/rpc?operation=sum&numbers[]=1&numbers[]=2&numbers[]=3`

### 7.3 启动消费者

使用命令行工具启动各种模式的消费者：

```bash
# 启动简单模式消费者
php think rabbitmq:consumer simple

# 启动工作模式消费者
php think rabbitmq:consumer work worker-1

# 启动发布/订阅模式消费者
php think rabbitmq:consumer pubsub logger

# 启动路由模式消费者（指定路由键）
php think rabbitmq:consumer routing error-handler --routing-keys=error,critical

# 启动主题模式消费者（指定主题模式）
php think rabbitmq:consumer topic app-monitor --routing-keys=app.#,*.error.critical

# 启动RPC服务端
php think rabbitmq:consumer rpc
```

### 7.4 示例代码位置

所有示例代码都位于以下目录：

- 简单模式: `app/examples/rabbitmq/SimpleMode.php`
- 工作模式: `app/examples/rabbitmq/WorkMode.php`
- 发布/订阅模式: `app/examples/rabbitmq/PublishSubscribeMode.php`
- 路由模式: `app/examples/rabbitmq/RoutingMode.php`
- 主题模式: `app/examples/rabbitmq/TopicMode.php`
- RPC模式: `app/examples/rabbitmq/RPCMode.php`

控制器和命令行工具：

- HTTP控制器: `app/controller/RabbitMQExamplesController.php`
- 命令行工具: `app/command/RabbitMQConsumers.php`

## 总结

RabbitMQ提供了多种工作模式，可以满足不同场景下的消息传递需求：

1. **Simple模式**：最基础的一对一消息传递
2. **Work模式**：任务分发和负载均衡
3. **Publish/Subscribe模式**：消息广播到多个消费者
4. **Routing模式**：基于路由键的消息过滤
5. **Topic模式**：基于通配符的灵活路由
6. **RPC模式**：请求-响应模式的远程调用

根据实际业务需求选择合适的模式，可以构建出高效、可靠的消息传递系统。
