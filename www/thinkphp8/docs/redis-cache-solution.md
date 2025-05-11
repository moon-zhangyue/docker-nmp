# ThinkPHP 8 Redis缓存解决方案

## 概述

本文档详细介绍了基于ThinkPHP 8框架的Redis 7.2缓存解决方案。该解决方案提供了一套完整的Redis缓存操作封装，支持所有Redis数据类型，并实现了防止缓存穿透和缓存雪崩的机制。

### 主要特性

- 支持Redis全部数据类型：String、Hash、List、Set、ZSet(有序集合)、Geo(地理位置)、Bitmap(位图)、HyperLogLog(基数统计)
- 完善的防缓存穿透、缓存击穿和缓存雪崩解决方案
- 基于ThinkPHP 8的Facade模式，使用简单直观
- 丰富的应用场景示例和最佳实践
- 完整的方法注释和类型提示，便于开发和维护

### 适用场景

- 需要高效缓存数据的Web应用
- 需要使用Redis特殊数据结构（如地理位置、位图等）的应用
- 高并发系统需要使用分布式锁、计数器等功能
- 需要实现排行榜、计数统计、用户签到等特定功能的应用

## 架构设计

整个Redis缓存解决方案采用分层设计：

1. **核心服务层**：RedisService类，负责Redis连接管理和提供基础功能
2. **数据类型服务层**：各种数据类型的专用服务类，如StringService、HashService等
3. **Facade层**：Redis门面类，提供静态调用方式
4. **应用层**：各种应用场景的Demo控制器和视图

### 目录结构

```
app/
  ├── service/
  │   ├── RedisService.php            # Redis核心服务类
  │   └── redis/
  │       ├── StringService.php       # 字符串操作服务
  │       ├── HashService.php         # 哈希表操作服务
  │       ├── ListService.php         # 列表操作服务
  │       ├── SetService.php          # 集合操作服务
  │       ├── ZSetService.php         # 有序集合操作服务
  │       ├── GeoService.php          # 地理位置操作服务
  │       ├── BitMapService.php       # 位图操作服务
  │       └── HyperLogLogService.php  # 基数统计操作服务
  ├── facade/
  │   └── Redis.php                   # Redis门面类
  └── controller/
      ├── RedisDemo.php               # Redis演示基础控制器
      └── redis/
          ├── StringDemo.php          # 字符串演示控制器
          ├── HashDemo.php            # 哈希表演示控制器
          ├── ListDemo.php            # 列表演示控制器
          ├── SetDemo.php             # 集合演示控制器
          ├── ZSetDemo.php            # 有序集合演示控制器
          ├── GeoDemo.php             # 地理位置演示控制器
          ├── BitMapDemo.php          # 位图演示控制器
          └── HyperLogLogDemo.php     # 基数统计演示控制器
```

## 配置说明

在ThinkPHP项目的配置文件`config/redis.php`中，需要进行Redis连接设置：

```php
<?php

return [
    'default' => [
        'host'       => env('REDIS_HOST', '127.0.0.1'),
        'port'       => env('REDIS_PORT', 6379),
        'password'   => env('REDIS_PASSWORD', ''),
        'select'     => env('REDIS_DB', 0),
        'timeout'    => env('REDIS_TIMEOUT', 0),
        'persistent' => env('REDIS_PERSISTENT', false),
        'options'    => [
            // Redis::OPT_SERIALIZER => Redis::SERIALIZER_PHP,
        ],
    ],
    
    'cache' => [
        'host'       => env('REDIS_HOST', '127.0.0.1'),
        'port'       => env('REDIS_PORT', 6379),
        'password'   => env('REDIS_PASSWORD', ''),
        'select'     => env('REDIS_CACHE_DB', 1),
        'timeout'    => env('REDIS_TIMEOUT', 0),
        'persistent' => env('REDIS_PERSISTENT', false),
    ],
    
    'session' => [
        'host'       => env('REDIS_HOST', '127.0.0.1'),
        'port'       => env('REDIS_PORT', 6379),
        'password'   => env('REDIS_PASSWORD', ''),
        'select'     => env('REDIS_SESSION_DB', 2),
        'timeout'    => env('REDIS_TIMEOUT', 0),
        'persistent' => env('REDIS_PERSISTENT', false),
    ],
];
```

可以根据实际需求配置多个Redis连接，实现不同业务数据的隔离。

## 核心服务类使用

RedisService是整个Redis缓存解决方案的核心，提供Redis连接管理和基础功能支持。

### 基本使用

```php
use app\service\RedisService;

// 使用默认连接创建Redis服务实例
$redis = new RedisService();

// 使用指定连接创建Redis服务实例
$redis = new RedisService('cache');

// 获取Redis原始实例，可以直接使用PHP Redis扩展提供的所有方法
$rawRedis = $redis->getRedis();
$rawRedis->set('key', 'value');
```

### 数据类型服务访问

RedisService提供了便捷的方法来访问各种数据类型服务：

```php
// 获取字符串操作服务
$stringService = $redis->string();

// 获取哈希表操作服务
$hashService = $redis->hash();

// 获取列表操作服务
$listService = $redis->list();

// 获取集合操作服务
$setService = $redis->set();

// 获取有序集合操作服务
$zsetService = $redis->zset();

// 获取地理位置操作服务
$geoService = $redis->geo();

// 获取位图操作服务
$bitmapService = $redis->bitmap();

// 获取基数统计操作服务
$hyperLogLogService = $redis->hyperLogLog();
```

### 分布式锁

RedisService提供了分布式锁的实现，可以用于并发控制：

```php
// 获取分布式锁，参数：锁的键名，锁的超时时间（秒）
$gotLock = $redis->acquireLock('lock:my_task', 10);

if ($gotLock) {
    try {
        // 执行需要加锁的操作
        // ...
    } finally {
        // 操作完成后释放锁
        $redis->releaseLock('lock:my_task');
    }
} else {
    // 获取锁失败的处理
    // ...
}
```

### 防止缓存雪崩

RedisService提供了过期时间随机化的方法，避免大量缓存同时过期：

```php
// 获取随机化的过期时间，默认在指定时间基础上增加0-10%的随机值
$expire = $redis->getRandomExpire(3600); // 返回3600-3960之间的随机值

// 自定义随机范围
$redis->setExpireRange([3600, 4200]); // 设置范围为1小时到1小时10分钟
```

## Facade使用

本解决方案提供了Redis Facade，可以更方便地使用静态方法调用各种Redis操作。

### 基本使用

```php
use app\facade\Redis;

// 使用字符串操作
Redis::string()->set('key', 'value', 3600);
$value = Redis::string()->get('key');

// 使用哈希表操作
Redis::hash()->hSet('hash', 'field', 'value');
$value = Redis::hash()->hGet('hash', 'field');

// 使用列表操作
Redis::list()->lPush('list', 'value');
$value = Redis::list()->lPop('list');

// 使用集合操作
Redis::set()->sAdd('set', 'member');
$members = Redis::set()->sMembers('set');

// 使用有序集合操作
Redis::zset()->zAdd('zset', 1, 'member');
$members = Redis::zset()->zRange('zset', 0, -1);

// 使用地理位置操作
Redis::geo()->geoAdd('geo', 116.405285, 39.904989, 'beijing');
$distance = Redis::geo()->geoDist('geo', 'beijing', 'shanghai', 'km');

// 使用位图操作
Redis::bitmap()->setBit('bitmap', 0, 1);
$bit = Redis::bitmap()->getBit('bitmap', 0);

// 使用基数统计操作
Redis::hyperLogLog()->pfAdd('hll', 'value');
$count = Redis::hyperLogLog()->pfCount('hll');
```

### 切换Redis连接

默认情况下，Facade使用default连接，可以通过connection方法切换连接：

```php
// 使用cache连接
Redis::connection('cache')->string()->set('key', 'value');

// 使用session连接
Redis::connection('session')->string()->set('key', 'value');
```

### 链式调用

可以使用链式调用简化代码：

```php
// 设置空值过期时间并缓存数据
Redis::setEmptyValueExpire(30)
      ->string()
      ->remember('key', function() {
          return fetchDataFromDatabase();
      }, 3600);
```

## 数据类型服务和应用场景

本解决方案为Redis的各种数据类型提供了专门的服务类，每个服务类都有其特定的应用场景。

### 字符串(String)服务

StringService提供了对Redis字符串类型的操作封装，支持自动序列化和反序列化，主要用于简单的键值对缓存。

#### 基本使用

```php
use app\facade\Redis;

// 设置值
Redis::string()->set('user:1', ['id' => 1, 'name' => '张三'], 3600);

// 获取值
$user = Redis::string()->get('user:1', true); // 第二个参数为true表示自动JSON解码

// 设置值（如果不存在）
Redis::string()->setNx('counter', 0);

// 自增
$count = Redis::string()->increment('counter');

// 自减
$count = Redis::string()->decrement('counter');

// 删除键
Redis::string()->delete('user:1');
```

#### 防止缓存穿透

remember方法提供了防止缓存穿透的机制：

```php
// 从缓存获取数据，如果缓存不存在则从回调函数获取并缓存
$data = Redis::string()->remember('key', function() {
    // 从数据库或其他地方获取数据
    return $data;
}, 3600);
```

#### 应用场景

- 缓存用户会话数据
- 实现访问计数器
- 限流控制(Rate Limiting)
- 缓存API响应
- 全局配置缓存

### 哈希表(Hash)服务

HashService提供了对Redis哈希表类型的操作封装，适合存储结构化对象的字段。

#### 基本使用

```php
use app\facade\Redis;

// 设置哈希表字段
Redis::hash()->hSet('user:profile:1', 'name', '张三');
Redis::hash()->hSet('user:profile:1', 'age', 25);

// 批量设置字段
Redis::hash()->hMSet('user:profile:1', [
    'email' => 'zhangsan@example.com',
    'phone' => '13800138000'
]);

// 获取字段值
$name = Redis::hash()->hGet('user:profile:1', 'name');

// 获取所有字段和值
$profile = Redis::hash()->hGetAll('user:profile:1');

// 字段自增
Redis::hash()->hIncrBy('user:stats:1', 'login_count', 1);

// 删除字段
Redis::hash()->hDel('user:profile:1', 'phone');

// 删除多个字段
Redis::hash()->hDel('user:profile:1', ['email', 'phone']);
```

#### 防止缓存穿透

哈希表同样提供了remember方法防止缓存穿透：

```php
// 从缓存获取哈希表字段值，如果不存在则从回调函数获取并缓存
$email = Redis::hash()->hRemember('user:profile:1', 'email', function() {
    // 从数据库获取用户邮箱
    return $userEmail;
});
```

#### 应用场景

- 用户配置存储
- 商品详情缓存
- 统计数据
- 缓存数据库行记录
- 会话存储

### 列表(List)服务

ListService提供了对Redis列表类型的操作封装，适合存储有序数据序列。

#### 基本使用

```php
use app\facade\Redis;

// 将元素添加到列表头部
Redis::list()->lPush('recent_news', 'news:1001');

// 将元素添加到列表尾部
Redis::list()->rPush('recent_news', 'news:1002');

// 批量添加元素
Redis::list()->lPush('recent_news', ['news:1003', 'news:1004']);

// 获取列表长度
$length = Redis::list()->lLen('recent_news');

// 获取列表范围内的元素
$news = Redis::list()->lRange('recent_news', 0, 9); // 获取前10条

// 从列表头部弹出元素
$news = Redis::list()->lPop('recent_news');

// 从列表尾部弹出元素
$news = Redis::list()->rPop('recent_news');

// 修剪列表
Redis::list()->lTrim('recent_news', 0, 99); // 只保留前100条
```

#### 应用场景

- 最新消息队列
- 社交网络的时间线功能
- 日志记录
- 任务队列
- 最近浏览历史

### 集合(Set)服务

SetService提供了对Redis集合类型的操作封装，适合存储唯一的无序元素集合。

#### 基本使用

```php
use app\facade\Redis;

// 添加元素到集合
Redis::set()->sAdd('user:1:follows', 'user:2');
Redis::set()->sAdd('user:1:follows', ['user:3', 'user:4']);

// 检查元素是否存在
$isFollowing = Redis::set()->sIsMember('user:1:follows', 'user:2');

// 获取集合中的所有元素
$allFollows = Redis::set()->sMembers('user:1:follows');

// 获取集合元素数量
$followCount = Redis::set()->sCard('user:1:follows');

// 随机获取元素
$randomFollow = Redis::set()->sRandMember('user:1:follows');

// 移除元素
Redis::set()->sRem('user:1:follows', 'user:3');

// 集合操作：交集
$commonFollows = Redis::set()->sInter(['user:1:follows', 'user:2:follows']);

// 集合操作：并集
$allUsers = Redis::set()->sUnion(['user:1:follows', 'user:2:follows']);

// 集合操作：差集
$uniqueFollows = Redis::set()->sDiff(['user:1:follows', 'user:2:follows']);
```

#### 应用场景

- 用户关注/粉丝关系
- 标签系统
- 唯一ID管理
- IP地址黑白名单
- 共同好友/共同兴趣发现

### 有序集合(ZSet)服务

ZSetService提供了对Redis有序集合类型的操作封装，适合需要按分数排序的数据集合。

#### 基本使用

```php
use app\facade\Redis;

// 添加成员和分数
Redis::zset()->zAdd('leaderboard', 100, 'user:1');
Redis::zset()->zAdd('leaderboard', 85, 'user:2');

// 批量添加成员
Redis::zset()->zMAdd('leaderboard', [
    'user:3' => 90,
    'user:4' => 95
]);

// 获取成员分数
$score = Redis::zset()->zScore('leaderboard', 'user:1');

// 增加成员分数
$newScore = Redis::zset()->zIncrBy('leaderboard', 5, 'user:1');

// 获取成员排名（从0开始）
$rank = Redis::zset()->zRank('leaderboard', 'user:1');
$revRank = Redis::zset()->zRevRank('leaderboard', 'user:1'); // 倒序排名

// 获取范围内的成员
$topUsers = Redis::zset()->zRevRange('leaderboard', 0, 9); // 获取前10名
$topUsersWithScores = Redis::zset()->zRevRange('leaderboard', 0, 9, true); // 包含分数

// 获取指定分数范围内的成员
$users = Redis::zset()->zRangeByScore('leaderboard', 90, 100);

// 获取集合成员数量
$count = Redis::zset()->zCard('leaderboard');

// 获取指定分数范围内的成员数量
$countInRange = Redis::zset()->zCount('leaderboard', 90, 100);

// 删除成员
Redis::zset()->zRem('leaderboard', 'user:2');

// 删除排名范围内的成员
Redis::zset()->zRemRangeByRank('leaderboard', 0, 0); // 删除排名第一的成员
```

#### 应用场景

- 排行榜系统
- 优先级队列
- 延迟队列
- 权重搜索
- 访问量统计
- 带权重的搜索建议

### 地理位置(Geo)服务

GeoService提供了对Redis地理位置类型的操作封装，适合存储位置数据和计算地理距离。

#### 基本使用

```php
use app\facade\Redis;

// 添加地理位置
Redis::geo()->geoAdd('stores', 116.405285, 39.904989, 'store:1');
Redis::geo()->geoAdd('stores', 116.418967, 39.914642, 'store:2');

// 获取位置坐标
$position = Redis::geo()->geoPos('stores', 'store:1');

// 计算两点距离
$distance = Redis::geo()->geoDist('stores', 'store:1', 'store:2', 'km');

// 获取指定范围内的位置
$nearbyStores = Redis::geo()->geoRadius('stores', 116.405285, 39.904989, 5, 'km', [
    'WITHDIST' => true, // 返回距离
    'WITHCOORD' => true, // 返回坐标
    'COUNT' => 10, // 限制返回数量
    'ASC' => true, // 按距离正序排列
]);

// 获取指定成员周围的位置
$neighborStores = Redis::geo()->geoRadiusByMember('stores', 'store:1', 5, 'km', [
    'WITHDIST' => true,
    'COUNT' => 5,
    'ASC' => true,
]);

// 获取地理位置的GeoHash值
$hash = Redis::geo()->geoHash('stores', 'store:1');
```

#### 应用场景

- "附近的人"功能
- 店铺/服务位置查找
- 司机派单系统
- 物流路径规划
- 地理围栏(Geo-fencing)

### 位图(Bitmap)服务

BitMapService提供了对Redis位图类型的操作封装，适合存储大量的布尔值信息。

#### 基本使用

```php
use app\facade\Redis;

// 设置位图中特定位置的值
Redis::bitmap()->setBit('user:online', 1001, 1); // 用户1001在线
Redis::bitmap()->setBit('user:online', 1002, 0); // 用户1002离线

// 获取位图中特定位置的值
$isOnline = Redis::bitmap()->getBit('user:online', 1001);

// 计算位图中值为1的位数
$onlineCount = Redis::bitmap()->bitCount('user:online');

// 查找第一个值为1或0的位置
$firstOnlineUser = Redis::bitmap()->bitPos('user:online', 1);

// 位操作：AND、OR、XOR、NOT
Redis::bitmap()->bitOp('AND', 'result', ['bitmap1', 'bitmap2']);

// 用户每日签到
$signed = Redis::bitmap()->setDailyActive('user:sign', 1001, '2023-07-01', 1);

// 检查用户某天是否签到
$isSignedToday = Redis::bitmap()->checkDailyActive('user:sign', 1001, '2023-07-01');

// 获取用户月度签到天数
$daysInMonth = Redis::bitmap()->getMonthlyActiveDays('user:sign', 1001, '2023-07');
```

#### 应用场景

- 用户在线状态管理
- 用户签到系统
- 活跃用户统计
- 用户行为分析
- 布隆过滤器实现

### 基数统计(HyperLogLog)服务

HyperLogLogService提供了对Redis HyperLogLog类型的操作封装，适合高效地统计元素的基数(不重复元素的数量)。

#### 基本使用

```php
use app\facade\Redis;

// 添加元素
Redis::hyperLogLog()->pfAdd('daily_visitors', 'user:1001');
Redis::hyperLogLog()->pfAdd('daily_visitors', ['user:1002', 'user:1003']);

// 统计基数(近似值)
$uniqueVisitors = Redis::hyperLogLog()->pfCount('daily_visitors');

// 合并多个HyperLogLog
Redis::hyperLogLog()->pfMerge('weekly_visitors', ['daily_visitors:1', 'daily_visitors:2']);

// 统计多个HyperLogLog的合并基数
$totalVisitors = Redis::hyperLogLog()->pfMergeCount(['daily_visitors:1', 'daily_visitors:2']);
```

#### 应用场景

- 网站UV(独立访客)统计
- 大规模系统的用户数统计
- 搜索词汇的统计分析
- 大数据集去重分析
- 广告曝光分析

## 实际应用示例

### 用户Token管理

```php
class UserService
{
    // 生成用户登录Token
    public function generateToken(int $userId): string
    {
        $token = md5(uniqid() . $userId . time());
        $expireTime = 86400; // 24小时
        
        // 存储Token
        Redis::string()->set("user:token:{$userId}", $token, $expireTime);
        // 反向映射，用于验证
        Redis::string()->set("token:{$token}", $userId, $expireTime);
        
        return $token;
    }
    
    // 验证Token
    public function verifyToken(string $token): ?int
    {
        $userId = Redis::string()->get("token:{$token}");
        if (!$userId) {
            return null;
        }
        
        $storedToken = Redis::string()->get("user:token:{$userId}");
        if ($token !== $storedToken) {
            return null;
        }
        
        return (int)$userId;
    }
    
    // 删除Token（退出登录）
    public function removeToken(int $userId): void
    {
        $token = Redis::string()->get("user:token:{$userId}");
        if ($token) {
            Redis::string()->delete("token:{$token}");
            Redis::string()->delete("user:token:{$userId}");
        }
    }
}
```

### 接口限流器

```php
class RateLimiter
{
    // 限制接口调用频率
    public function isAllowed(string $ip, string $api, int $limit, int $period = 60): bool
    {
        $key = "rate:limit:{$ip}:{$api}";
        $count = Redis::string()->increment($key);
        
        // 第一次访问，设置过期时间
        if ($count === 1) {
            Redis::string()->expire($key, $period);
        }
        
        return $count <= $limit;
    }
}

// 使用示例
$limiter = new RateLimiter();
$ip = $request->ip();
$api = 'user/login';

if (!$limiter->isAllowed($ip, $api, 5, 60)) {
    return json(['code' => 429, 'msg' => '请求过于频繁，请稍后再试']);
}
```

### 商品库存管理

```php
class StockService
{
    // 减少库存（原子操作）
    public function decreaseStock(int $productId, int $quantity = 1): bool
    {
        $key = "product:stock:{$productId}";
        
        // 先检查当前库存
        $currentStock = (int)Redis::string()->get($key);
        if ($currentStock < $quantity) {
            return false;
        }
        
        // 减少库存
        $newStock = Redis::string()->decrement($key, $quantity);
        
        // 如果库存不足，回滚
        if ($newStock < 0) {
            Redis::string()->increment($key, $quantity);
            return false;
        }
        
        return true;
    }
    
    // 增加库存
    public function increaseStock(int $productId, int $quantity = 1): int
    {
        $key = "product:stock:{$productId}";
        return Redis::string()->increment($key, $quantity);
    }
    
    // 设置库存
    public function setStock(int $productId, int $stock, int $expireTime = 86400): bool
    {
        $key = "product:stock:{$productId}";
        return Redis::string()->set($key, $stock, $expireTime);
    }
}
```

## 结语

ThinkPHP 8 Redis缓存解决方案提供了一套完整、易用的Redis操作封装，帮助开发者充分利用Redis的强大功能，并解决了缓存使用中的常见问题。通过使用本解决方案，可以显著提升应用性能，降低数据库压力，增强系统的扩展性和可靠性。

更多关于缓存问题处理和性能优化的详细内容，请参考[Redis缓存问题处理与性能优化](./redis-cache-problems.md)文档。

## 附录

- [Redis官方文档](https://redis.io/documentation)
- [ThinkPHP 8官方文档](https://doc.thinkphp.cn/v8_0)
- [PHP Redis扩展文档](https://github.com/phpredis/phpredis) 