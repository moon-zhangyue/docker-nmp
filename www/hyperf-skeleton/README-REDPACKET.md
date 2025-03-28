# 抢红包功能实现

本项目基于Hyperf框架实现了高并发抢红包功能，包括红包创建、抢红包、红包详情等功能。

## 技术架构

- **框架**：Hyperf 3.1
- **数据库**：MySQL
- **缓存**：Redis
- **并发控制**：Redis分布式锁

## 数据库设计

### 用户表(users)

| 字段名 | 类型 | 说明 |
| --- | --- | --- |
| id | bigint | 主键ID |
| name | varchar(50) | 用户名 |
| avatar | varchar(255) | 头像 |
| balance | decimal(10,2) | 账户余额 |
| status | tinyint | 状态：1-正常，0-禁用 |
| created_at | timestamp | 创建时间 |
| updated_at | timestamp | 更新时间 |

### 红包表(red_packets)

| 字段名 | 类型 | 说明 |
| --- | --- | --- |
| id | bigint | 主键ID |
| packet_no | varchar(32) | 红包编号 |
| user_id | bigint | 发红包用户ID |
| total_amount | decimal(10,2) | 红包总金额 |
| total_num | int | 红包总数量 |
| remaining_num | int | 剩余红包数量 |
| remaining_amount | decimal(10,2) | 剩余红包金额 |
| status | tinyint | 状态：1-有效，0-无效 |
| type | tinyint | 红包类型：1-普通红包，2-拼手气红包 |
| blessing | varchar(255) | 祝福语 |
| expired_at | timestamp | 过期时间 |
| created_at | timestamp | 创建时间 |
| updated_at | timestamp | 更新时间 |

### 红包记录表(red_packet_records)

| 字段名 | 类型 | 说明 |
| --- | --- | --- |
| id | bigint | 主键ID |
| packet_no | varchar(32) | 红包编号 |
| packet_id | bigint | 红包ID |
| user_id | bigint | 抢红包用户ID |
| amount | decimal(10,2) | 抢到的红包金额 |
| status | tinyint | 状态：1-已领取，0-已退回 |
| created_at | timestamp | 创建时间 |
| updated_at | timestamp | 更新时间 |

## 功能实现

### 1. 创建红包

- **接口**：`POST /api/red-packet/create`
- **参数**：
  - user_id: 用户ID
  - amount: 红包金额
  - num: 红包数量
  - type: 红包类型（1-普通红包，2-拼手气红包）
  - blessing: 祝福语
- **处理流程**：
  1. 验证用户余额是否足够
  2. 使用Redis分布式锁确保并发安全
  3. 扣减用户余额
  4. 生成唯一的红包编号
  5. 根据红包类型预先生成红包金额列表：
     - 普通红包：每个红包金额相同
     - 拼手气红包：使用二倍均值法随机分配金额
  6. 将红包信息存入数据库
  7. 将红包金额列表存入Redis缓存
  8. 设置红包过期时间（默认24小时）
  9. 返回红包编号和创建结果

### 2. 抢红包

- **接口**：`POST /api/red-packet/grab`
- **参数**：
  - user_id: 用户ID
  - packet_no: 红包编号
- **处理流程**：
  1. 验证红包是否存在且有效
  2. 使用Redis分布式锁防止并发抢夺
  3. 检查用户是否已抢过该红包
  4. 检查红包是否已抢完
  5. 从Redis中原子性地获取一个红包金额
  6. 更新红包表中的剩余数量和金额
  7. 记录抢红包记录
  8. 增加用户余额
  9. 返回抢到的红包金额

### 3. 红包详情

- **接口**：`GET /api/red-packet/detail`
- **参数**：
  - packet_no: 红包编号
- **处理流程**：
  1. 查询红包基本信息
  2. 查询红包领取记录列表
  3. 计算红包领取统计信息（最大金额、最小金额、平均金额等）
  4. 返回红包详情和领取记录

## 并发控制

本系统使用Redis分布式锁实现并发控制，主要应用在以下场景：

1. **创建红包时**：防止用户余额被重复扣减
2. **抢红包时**：防止红包被重复领取，确保原子性操作

### Redis分布式锁实现

```php
public function lock(string $key, int $ttl = 5, int $timeout = 0): bool
{
    $startTime = microtime(true);
    $lockKey = "lock:{$key}";
    $lockValue = uniqid();
    
    do {
        // 尝试获取锁，使用NX选项确保原子性
        $acquired = Redis::set($lockKey, $lockValue, ['NX', 'EX' => $ttl]);
        
        if ($acquired) {
            // 成功获取锁，记录锁值用于解锁验证
            $this->lockValues[$key] = $lockValue;
            return true;
        }
        
        // 未获取到锁，等待一段时间后重试
        if ($timeout > 0) {
            usleep(10000); // 等待10毫秒
        }
        
    } while ($timeout > 0 && (microtime(true) - $startTime) < $timeout);
    
    return false;
}

public function unlock(string $key): bool
{
    $lockKey = "lock:{$key}";
    $lockValue = $this->lockValues[$key] ?? null;
    
    if (!$lockValue) {
        return false;
    }
    
    // 使用Lua脚本确保原子性解锁，只解锁自己的锁
    $script = <<<LUA
    if redis.call('get', KEYS[1]) == ARGV[1] then
        return redis.call('del', KEYS[1])
    else
        return 0
    end
    LUA;
    
    $result = Redis::eval($script, 1, $lockKey, $lockValue);
    unset($this->lockValues[$key]);
    
    return $result === 1;
}
```

## 红包金额分配算法

### 普通红包

普通红包的金额分配非常简单，总金额平均分配给每个红包：

```php
$amount = bcdiv($totalAmount, $totalNum, 2);
```

### 拼手气红包（二倍均值法）

拼手气红包使用二倍均值法进行随机分配，算法步骤如下：

1. 计算当前剩余平均值的2倍作为上限
2. 在0.01和上限之间随机选择一个金额
3. 更新剩余金额和数量
4. 重复以上步骤直到只剩最后一个红包

```php
// 伪代码
function divideRedPacket($totalAmount, $totalNum) {
    $amounts = [];
    $remainAmount = $totalAmount;
    $remainNum = $totalNum;
    
    for ($i = 0; $i < $totalNum - 1; $i++) {
        // 计算二倍均值
        $max = bcmul(bcdiv($remainAmount, $remainNum, 2), 2, 2);
        // 随机金额，最小0.01元
        $amount = mt_rand(1, intval($max * 100)) / 100;
        $amount = max(0.01, $amount);
        $amount = number_format($amount, 2, '.', '');
        
        $amounts[] = $amount;
        $remainAmount = bcsub($remainAmount, $amount, 2);
        $remainNum--;
    }
    
    // 最后一个红包，剩余的全部金额
    $amounts[] = number_format($remainAmount, 2, '.', '');
    
    return $amounts;
}
```

## 异常处理

系统对以下异常情况进行了处理：

1. **余额不足**：创建红包时检查用户余额
2. **红包已抢完**：使用Redis原子操作确保不会超发
3. **重复抢红包**：记录已抢用户ID防止重复领取
4. **红包过期**：设置过期时间，过期后自动失效
5. **并发冲突**：使用分布式锁和数据库事务保证数据一致性

### 红包过期处理机制

红包过期处理采用了双重机制确保系统的可靠性：

1. **Redis TTL机制**：创建红包时，同时在Redis中设置过期时间

```php
// 设置红包数据过期时间
Redis::expire("red_packet:{$packetNo}", 86400); // 24小时过期

// 设置红包状态标记
Redis::set("red_packet:status:{$packetNo}", 1, ['EX' => 86400]);
```

2. **定时任务检查**：系统定时扫描过期红包，处理未领取金额

```php
// 定时任务伪代码
public function handleExpiredRedPackets()
{
    // 查找已过期但未处理的红包
    $expiredPackets = RedPacket::where('expired_at', '<', now())
        ->where('status', 1)
        ->where('remaining_num', '>', 0)
        ->get();
    
    foreach ($expiredPackets as $packet) {
        DB::transaction(function () use ($packet) {
            // 将剩余金额退回给发红包用户
            $user = User::find($packet->user_id);
            $user->balance = bcadd($user->balance, $packet->remaining_amount, 2);
            $user->save();
            
            // 更新红包状态为已过期
            $packet->status = 0;
            $packet->save();
            
            // 记录退回日志
            Log::info("红包过期退回", [
                'packet_no' => $packet->packet_no,
                'user_id' => $packet->user_id,
                'amount' => $packet->remaining_amount
            ]);
        });
    }
}
```

## 性能优化

为了支持高并发抢红包场景，系统采用了以下性能优化措施：

1. **Redis预加载**：红包创建时，将红包金额列表预先加载到Redis中，减少数据库访问

```php
// 将红包金额列表存入Redis
foreach ($amounts as $index => $amount) {
    Redis::rpush("red_packet:amounts:{$packetNo}", $amount);
}
```

2. **原子操作**：使用Redis的LPOP命令原子性地获取红包金额，避免并发问题

```php
// 原子性地获取一个红包金额
$amount = Redis::lpop("red_packet:amounts:{$packetNo}");
```

3. **异步处理**：使用Hyperf的异步任务处理非关键路径操作，如日志记录、统计更新等

```php
// 异步记录抢红包日志
$this->asyncQueue->push(new RecordGrabLogJob($userId, $packetNo, $amount));
```

4. **缓存用户抢红包记录**：使用Redis集合记录已抢用户，快速判断重复抢红包

```php
// 检查用户是否已抢过该红包
$hasGrabbed = Redis::sismember("red_packet:grabbed_users:{$packetNo}", $userId);

// 记录用户已抢红包
Redis::sadd("red_packet:grabbed_users:{$packetNo}", $userId);
```

5. **数据库索引优化**：为红包表和记录表添加合适的索引，提高查询效率

```php
// 红包表索引
Schema::table('red_packets', function (Blueprint $table) {
    $table->index('packet_no');
    $table->index('user_id');
    $table->index('expired_at');
});

// 红包记录表索引
Schema::table('red_packet_records', function (Blueprint $table) {
    $table->index(['packet_no', 'user_id']);
    $table->index('packet_id');
});
```

## 安全措施

系统实施了多层次的安全措施，确保红包功能的安全可靠：

1. **接口限流**：对抢红包接口实施限流，防止恶意请求

```php
// 限流中间件配置
'rate_limit' => [
    'create' => ['capacity' => 5, 'seconds' => 60],  // 每分钟最多创建5个红包
    'grab' => ['capacity' => 20, 'seconds' => 60],    // 每分钟最多抢20个红包
]
```

2. **数据验证**：严格验证输入参数，防止非法数据

```php
// 创建红包参数验证
$validator = $this->validationFactory->make($request->all(), [
    'user_id' => 'required|integer|exists:users,id',
    'amount' => 'required|numeric|min:0.01',
    'num' => 'required|integer|min:1|max:100',
    'type' => 'required|in:1,2',
    'blessing' => 'nullable|string|max:255',
]);
```

3. **事务保证**：使用数据库事务确保数据一致性

```php
DB::transaction(function () use ($userId, $amount, $num, $type, $blessing) {
    // 扣减用户余额
    // 创建红包记录
    // ...
});
```

4. **日志记录**：详细记录关键操作，便于审计和问题排查

```php
Log::info('创建红包', [
    'user_id' => $userId,
    'amount' => $amount,
    'num' => $num,
    'type' => $type,
    'packet_no' => $packetNo
]);
```

5. **防重放攻击**：使用请求唯一标识，防止重放攻击

```php
// 生成请求唯一标识
$requestId = md5($userId . $packetNo . microtime(true));

// 检查是否重复请求
if (Redis::exists("request:grab:{$requestId}")) {
    return $this->error('重复请求');
}

// 标记请求已处理，有效期5分钟
Redis::setex("request:grab:{$requestId}", 300, 1);
```

## 监控与统计

系统实现了完善的监控与统计功能，便于运营分析和系统监控：

1. **实时统计**：记录红包创建和领取的实时数据

```php
// 增加红包创建计数
Redis::incr('stats:red_packet:created:' . date('Ymd'));
Redis::incrby('stats:red_packet:amount:' . date('Ymd'), $amount * 100);

// 增加红包领取计数
Redis::incr('stats:red_packet:grabbed:' . date('Ymd'));
Redis::incrby('stats:red_packet:grabbed_amount:' . date('Ymd'), $amount * 100);
```

2. **性能监控**：记录关键接口的响应时间和成功率

```php
// 记录接口响应时间
$startTime = microtime(true);
// 处理请求...
$endTime = microtime(true);
$responseTime = ($endTime - $startTime) * 1000; // 毫秒

Redis::lpush('monitor:api:response_time:grab', $responseTime);
Redis::ltrim('monitor:api:response_time:grab', 0, 999); // 保留最近1000条
```

3. **异常监控**：记录系统异常，及时发现问题

```php
try {
    // 业务逻辑
} catch (\Throwable $e) {
    // 记录异常
    Log::error('抢红包异常', [
        'packet_no' => $packetNo,
        'user_id' => $userId,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    // 增加异常计数
    Redis::incr('monitor:error:grab:' . date('Ymd'));
    
    // 发送告警（严重异常）
    if ($this->isSerious($e)) {
        $this->sendAlert('抢红包严重异常: ' . $e->getMessage());
    }
    
    throw $e;
}
```

## 总结

本项目实现了一个高性能、高可靠性的抢红包系统，主要特点包括：

1. 基于Hyperf框架，充分利用协程特性提高并发处理能力
2. 使用Redis作为缓存和分布式锁，保证高并发下的数据一致性
3. 实现了完善的异常处理和容错机制，提高系统稳定性
4. 采用多种性能优化手段，支持高并发抢红包场景
5. 实施了全面的安全措施，保障系统和数据安全
6. 提供了详细的监控和统计功能，便于运营分析和问题排查

通过以上设计和实现，系统能够稳定支持大规模用户同时抢红包的场景，为用户提供流畅的红包体验。