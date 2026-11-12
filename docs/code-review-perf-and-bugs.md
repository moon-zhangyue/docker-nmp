# 代码审查报告：性能瓶颈与潜在 Bug

- 审查日期：2026-10-28
- 审查范围：`go_projects/`（Go + Gin + MySQL9 API）、`www/localhost/`（PHP 数据脚本与演示页）、`docker-compose.yml` 与 `services/`（Nginx / PHP-FPM / MySQL 配置）
- 审查方式：全量静态阅读 + 配置交叉比对（宿主机无 Go 工具链，未执行 `go vet` / 单元测试）

---

## 0. 结论摘要

共识别 **27 项**问题，其中会造成数据错误、进程卡死或服务不可用的有 **6 项**。

| 级别 | 数量 | 典型问题 |
|---|---|---|
| P0 阻断 | 6 | SKU 生成死循环、协程互斥锁死锁、`phpinfo()` 公网暴露、数据脚本可被任意触发、MySQL9 配置整体未生效、列表接口无分页 |
| P1 高危 | 11 | 500 万字符 CSV 缓冲、批量插入会话参数打错连接、`UPDATE` 先查后改、CORS 配置非法、无 HTTP 超时与优雅退出 |
| P2 改进 | 10 | 错误信息外泄、连接池参数、索引与 DSN 拼装、日志缺状态码等 |

**最高优先级 3 件事**（投入产出比最高）：

1. 把 `www/localhost/` 下的数据生成脚本移出 Web 根目录（或加 `PHP_SAPI === 'cli'` 拦截）——当前任何人访问 URL 就能向数据库写入 100 万行。
2. 修复 `add_goods.php:167` 的 `continue` 与 `add_user.php` 的互斥锁位置——这两处会直接把脚本挂死。
3. 在 `docker-compose.yml` 的 `mysql9` 服务补上 `mysql.cnf` 挂载——`.env` 里已经配了 `MYSQL9_CONF_FILE`，但从未被使用，导致字符集、时区、慢日志全部是默认值。

---

## 1. P0：阻断级问题

### 1.1 【Bug】SKU 生成可能死循环 + 预编译语句泄漏

`www/localhost/add_goods.php:162-182`

```php
if (!$stmt->execute()) {
    if ($stmt->errno == 1062) {
        $faker->unique(true);   // 重置唯一序列
        continue;               // ← 直接跳到下一轮
    }
}
$stmt->close();                 // ← 被 continue 跳过
...
if ($generatedSkus > $totalSkus + 50) { break; }  // ← 也被跳过，安全阀失效
```

三个连锁问题：

1. `continue` 跳过了 `$stmt->close()`，每次冲突泄漏一个 `mysqli_stmt`。MySQL 默认 `max_prepared_stmt_count = 16382`，泄漏到上限后整个连接报 *Can't create more than max_prepared_stmt_count statements*，脚本彻底失败。
2. `continue` 同时跳过了第 179 行的"安全退出"判断，注释里写的兜底逻辑实际永远不会执行。
3. `$faker->unique(true)` 把唯一序列**重置回起点**，下一次 `numerify('#####')` 会重新从已用过的号码段取值，与库中已有 `sku_code` 再次冲突的概率不降反升 —— 如果表里已有历史数据，这里极易进入"冲突→重置→再冲突"的无限循环。

**修复**：把 `close()` 与安全阀放进 `finally` 语义（PHP 无 finally 于此处，改为结构调整）：

```php
$stmt = prepareAndBind($mysqli, $skuSql, 'isdisi', $currentSpuId, $skuCode, $price, $stock, $attributesJson, $status);
$ok = $stmt->execute();
$errno = $stmt->errno;
$stmt->close();                       // 无论如何都关闭

if (!$ok && $errno === 1062) {
    $attempts++;                      // 用计数器兜底，而不是靠 unique 重置
    if ($attempts > $totalSkus * 3) {
        throw new RuntimeException('SKU 唯一键冲突过多，请检查表内历史数据');
    }
    continue;
}
```

同时 `$faker->unique(true)` 这行应删除 —— 重置只会制造重复，正确做法是让 Faker 的 unique 状态在整轮生成中保持连续。

---

### 1.2 【Bug】Swoole 协程互斥锁不在 `finally` 中，存在死锁

`www/localhost/add_user.php:210-243`

```php
try {
    ...
    $mutexChannel->pop();          // 加锁（Channel 容量 1）
    $insertedCount += $count;
    if (...) { echo "..."; }       // ← 若此处抛异常
    $mutexChannel->push(true);     // 解锁（不执行）
} catch (\Throwable $e) {
    $mutexChannel->pop();          // ← 再次 pop，通道已空，永久阻塞
    ...
}
```

`Swoole\Coroutine\Channel::pop()` 默认无超时。一旦 `echo` / `json_encode` / `errorInfo()` 在"已加锁未解锁"区间内抛出异常，`catch` 会再 `pop()` 一次，此时通道为空，该协程永久挂起；`Coroutine::join()` 于是永不返回，整个脚本卡死且无任何报错。

另外 `$pool->get()` 写在 `try` 之外，若取连接失败也不会执行 `finally` 里的 `$pool->put()`。

**修复**：锁的获取/释放必须成对出现在同一作用域，或用 `Swoole\Lock`：

```php
$pdo = $pool->get();          // 移入 try
try {
    $locked = false;
    ...
    if ($mutexChannel->pop(1.0)) {   // 带超时，避免永久阻塞
        $locked = true;
        $insertedCount += $count;
    }
    ...
} finally {
    if ($locked) { $mutexChannel->push(true); }
    $pool->put($pdo);
}
```

更简单的做法：Swoole 协程是单线程调度，`$insertedCount += $count` 这类非阻塞操作用普通变量即可，只在 `echo` 时串行化，或改用 `Swoole\Atomic`。

---

### 1.3 【安全/性能】`phpinfo()` 直接输出到公网页面

`www/localhost/index.php:148`

```php
echo phpinfo();
```

配合第 3 行的 `ini_set('display_errors', 1)`，该页面会完整暴露 PHP 版本、已编译模块、`php.ini` 路径、环境变量、数据库扩展配置。`localhost.conf` 中该站点 `root /www/localhost` 对 `server_name localhost` 开放，属于可直接访问的信息泄露面。

`getNginxVersion()` 里还有 5 层兜底探测（`$_SERVER` 遍历、`curl http://localhost`、`apache_response_headers`），为拿一个版本号做了不必要的探测。

**修复**：删除 `echo phpinfo()`；版本信息改用 `phpversion()` 按需输出；整个 `index.php` 仅限内网或加 IP 白名单。

---

### 1.4 【安全】数据生成脚本位于 Web 根目录，可被任意触发

`www/localhost/` 下 `addusers.php`、`add_user.php`、`add_goods.php`、`swoole.php`、`kafka_consumer.php` 全部是常驻/重载脚本，且硬编码了 `root/123456`：

```php
$user = 'root';   // addusers.php:6
$pass = '123456';
```

后果：

- 任何人 GET `addusers.php` 就会触发 100 万行 `LOAD DATA`，且脚本**没有 `TRUNCATE`**，每访问一次表就翻一倍。
- `add_user.php:50` 有 `DROP TABLE IF EXISTS \`user\`` —— 一次误访问即清库。
- `max_execution_time = 30`，脚本必然超时中断，留下半成品数据和 `temp.csv` 残file。
- `kafka_consumer.php`、`swoole.php` 是 `while(true)` / 监听 9501 的常驻进程，被 FPM 拉起后会一直占住 worker，`pm.max_children = 10` 被逐个耗尽即全站 502。

**修复**：

```php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script must be run from CLI.\n");
}
```

并把脚本迁到 `services/` 或独立 `scripts/` 目录，凭据改读环境变量。

---

### 1.5 【配置】MySQL9 的 `mysql.cnf` 从未被挂载，配置整体失效

`.env:9` 定义了：

```
MYSQL9_CONF_FILE=./services/mysql9/mysql.cnf
```

但 `docker-compose.yml:212-228` 的 `mysql9` 服务**没有对应的 volumes 挂载**（对比被注释掉的 `mysql5`，它是有的）。

因此 `services/mysql9/mysql.cnf` 里的全部内容都是死配置：

| 配置项 | 期望值 | 实际生效值 |
|---|---|---|
| `slow_query_log` | 开启，`long_query_time = 3` | 关闭，默认 10s |
| `character-set-server` | `utf8mb4` | 镜像默认 `utf8mb4`（碰巧一致） |
| `default-time-zone` | `+8:00` | 依赖容器 `TZ` |
| `disable-log-bin` | 关闭 binlog | 开启（写放大） |
| `sql_mode` | `STRICT_TRANS_TABLES` | 镜像默认（碰巧一致） |

**这是本仓库影响最大的性能问题**：MySQL 默认 `innodb_buffer_pool_size = 128M`，跑 100 万行数据集时几乎全部命中磁盘，`addusers.php` / `add_user.php` 的插入性能会被这个 128M 硬性卡住，再怎么调 `batch_size`、`concurrency` 都不会有质变。

**修复**：

```yaml
  mysql9:
    image: mysql:${MYSQL9_VERSION}
    container_name: mysql-9
    volumes:
      - ${MYSQL9_CONF_FILE}:/etc/mysql/conf.d/mysql.cnf:ro   # ← 补这行
      - ${DATA_DIR}/mysql9:/var/lib/mysql/:rw
```

并在 `mysql.cnf` 中补上关键性能项：

```ini
innodb_buffer_pool_size = 1G
innodb_log_file_size    = 256M
innodb_flush_log_at_trx_commit = 2
local_infile            = 1
```

> 顺带解决 `addusers.php:91` 需要 `SET GLOBAL local_infile = 1`（要求 SUPER 权限）的绕路问题。

---

### 1.6 【性能】`GET /api/users` 全表返回，无分页

`go_projects/app_routes.go:80-83`

```go
rows, err := s.db.QueryContext(ctx,
    `SELECT id, username, email, age, created_at, updated_at FROM users ORDER BY id DESC`)
```

`ORDER BY id DESC` 走主键反向扫描本身不慢，问题在于**没有 `LIMIT`**：

- 所有行一次性 `append` 进切片 → 内存随行数线性增长；
- 随后 `c.JSON` 序列化整份数据 → 峰值内存 ≈ 数据量 × 3（切片 + `[]byte` + 响应缓冲）；
- 叠加第 424 行 `context.WithTimeout(..., 3*time.Second)`，行数一多必然 3 秒超时，返回 500 而不是 504，前端无法区分"超时"和"数据库炸了"。

**修复**：强制分页 + 深翻页用游标（keyset）：

```go
func (s *MySQLUserStore) ListUsers(ctx context.Context, limit int, afterID int64) ([]User, error) {
    rows, err := s.db.QueryContext(ctx,
        `SELECT id, username, email, age, created_at, updated_at
           FROM users WHERE id < ? ORDER BY id DESC LIMIT ?`, afterID, limit)
    ...
}
```

Handler 侧解析 `?limit=`（默认 20，上限 100）与 `?after_id=`，并在响应里回传 `next_after_id`。

---

## 2. P1：高危问题

### 2.1 【性能】`addusers.php` 单字符串累积 50 万行 CSV，峰值内存约 45–90MB

`www/localhost/addusers.php:12,46,49`

```php
$bufferSize = 500000;                       // 12 行
$csvData .= '"' . $username . '","' . ...;  // 46 行：逐行拼接到同一个字符串
if (($i % $bufferSize == 0 && $i > 0) || $i == $totalRecords - 1) {
    fwrite($csv, $csvData);                 // 49 行：50 万行才落盘一次
}
```

每行约 90 字节 × 50 万 ≈ 45MB 的单一字符串，Zend 扩容时还会临时翻倍，峰值接近 90MB。在 `memory_limit = 256M` 下勉强能跑，但这是纯浪费 —— 写文件是顺序 I/O，缓冲区开 1 万行（约 900KB）就足够，完全不需要 50 万。

**修复**：改为每 10,000 行落盘，并用 `fputcsv` 保证转义正确：

```php
$buffer = [];
for ($i = 0; $i < $totalRecords; $i++) {
    $buffer[] = [$username, $email, $password];
    if (count($buffer) >= 10000) {
        foreach ($buffer as $row) { fputcsv($csv, $row, ',', '"', '\\'); }
        $buffer = [];
    }
}
```

### 2.2 【Bug】`addusers.php` 手拼 CSV 未转义，姓名含逗号/引号即数据错位

`www/localhost/addusers.php:46`

`$name` 来自 `names.txt`，若包含 `,` 或 `"`，手拼的 CSV 会破坏列结构，`LOAD DATA` 后 `username` 被截断、`email` 串到下一列 —— 而 `user_test` 表没有唯一约束，脏数据不会报错，只会在后续查询里表现为"查不到/字段串位"。第 49 行的 `ENCLOSED BY '"'` 也挡不住内嵌引号。

**修复**：同 2.1，改用 `fputcsv`，或入库前 `str_replace(['"', ',', "\n"], '', $name)`。

### 2.3 【Bug】`add_user.php` 的会话级优化参数打在了错误的连接上

`www/localhost/add_user.php:69-71`

```php
$pdo->exec("SET autocommit=0");          // $pdo 是第 53 行单独 new 的直连
$pdo->exec("SET unique_checks=0");
$pdo->exec("SET foreign_key_checks=0");
```

但真正的插入走的是第 105 行创建的 `PDOPool`，其连接由 `PDOConfig` 独立建立（第 98-112 行）。**会话变量不跨连接生效**，所以这三行优化对 100 万行插入完全没有作用，只是让 `$pdo` 这个连接自己进入了非自动提交状态。同理第 268-270 行的"恢复"操作也恢复的是错的对象。

连带问题：第 65 行 `ALTER TABLE user DISABLE KEYS` 对 **InnoDB 是空操作**（仅 MyISAM 支持），100 万行插入时索引该维护还是要维护。

**修复**：

```php
$pdoConfig = (new PDOConfig())
    ->withOptions([
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_INIT_COMMAND =>
            'SET SESSION unique_checks=0, foreign_key_checks=0, sql_log_bin=0',
    ]);
```

并把 `DISABLE KEYS` 相关代码删掉，改用"先删二级索引 → 灌数据 → 重建索引"（`ALTER TABLE ... DROP INDEX` / `ADD INDEX`），这才是 InnoDB 的正确加速方式。

### 2.4 【Bug】`add_user.php` 的 `INIT_COMMAND` 塞了两条语句

`www/localhost/add_user.php:111`

```php
PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION wait_timeout=28800; SET NAMES utf8mb4;",
```

PDO 的 init command 以单条查询下发，多语句需要 `CLIENT_MULTI_STATEMENTS`。多数构建下这里会直接抛 *You have an error in your SQL syntax*，导致**整个连接池创建失败**。即使侥幸通过，`SET NAMES` 也和 DSN 里的 `charset=utf8mb4` 重复。

**修复**：只保留一条，字符集交给 DSN：

```php
PDO::MYSQL_ATTR_INIT_COMMAND => 'SET SESSION wait_timeout=28800',
```

### 2.5 【性能】`add_user.php` 单条 SQL 6 万个占位符

`www/localhost/add_user.php:24`（`batch_size = 20000`）× 3 列 = 60,000 个 `?`。

MySQL 单语句占位符上限 65,535，这里已经贴着天花板。风险：

- 语句文本约 300KB，每次 `prepare` 的解析成本极高，且无法命中服务端 prepared statement 缓存；
- 单事务锁持有时间长，undo/redo 压力集中；
- 超过 `max_allowed_packet`（默认 64M）或占位符上限即整批失败。

实测经验上，单批 1,000–5,000 行是吞吐与稳定性的平衡点。**建议降到 2000**，用并发度（32）而不是批大小换吞吐。

### 2.6 【Bug】`UpdateUser` 先查后改，存在 TOCTOU 竞态且多一次往返

`go_projects/app_routes.go:169-186`

```go
exists, err := s.userExists(ctx, id)   // 第 1 次往返
...
if _, err := s.db.ExecContext(ctx, `UPDATE ... WHERE id = ?`, ...); err != nil {
```

两次网络往返，且两条语句之间没有事务：若另一请求在此期间 `DELETE` 了该行，`UPDATE` 影响 0 行，接口仍返回 `200 user updated`（假成功）。

**修复**：用 `RowsAffected` 判定，但必须注意 MySQL 的坑 —— 值未变化时 `RowsAffected` 返回 0，会被误判成 404。所以在 DSN 里加 `clientFoundRows=true`：

```go
res, err := s.db.ExecContext(ctx,
    `UPDATE users SET username=?, email=?, age=? WHERE id=?`,
    input.Username, input.Email, input.Age, id)
if err != nil { return fmt.Errorf("update user %d: %w", id, err) }
n, err := res.RowsAffected()
if err != nil { return err }
if n == 0 { return ErrUserNotFound }
```

### 2.7 【Bug】CORS 头组合非法，浏览器会拒绝

`go_projects/app_server.go:28-29`

```go
c.Writer.Header().Set("Access-Control-Allow-Origin", "*")
c.Writer.Header().Set("Access-Control-Allow-Credentials", "true")
```

按 Fetch 规范，`Allow-Origin: *` 与 `Allow-Credentials: true` 互斥。浏览器会直接拦截带凭证的响应，前端表现为"CORS 错误但服务端日志 200"，排查成本很高。同时缺少 `Vary: Origin`。

**修复**：从白名单反射 `Origin`：

```go
origin := c.GetHeader("Origin")
if allowed[origin] {
    c.Header("Access-Control-Allow-Origin", origin)
    c.Header("Access-Control-Allow-Credentials", "true")
    c.Header("Vary", "Origin")
}
```

### 2.8 【Bug】HTTP Server 无任何超时，存在 Slowloris 风险

`go_projects/app_server.go:71`

```go
if err := router.Run(":" + port); err != nil {
```

`router.Run` 内部用 `http.ListenAndServe`，等价于 `http.Server{}` 全零值 —— **没有 `ReadHeaderTimeout`**。攻击者开一个 TCP 连接、每秒发 1 个字节的请求头，即可长期占用一个 goroutine 与文件描述符，几百个连接就能打满。

**修复**：

```go
srv := &http.Server{
    Addr:              ":" + port,
    Handler:           router,
    ReadHeaderTimeout: 5 * time.Second,
    ReadTimeout:       15 * time.Second,
    WriteTimeout:      30 * time.Second,
    IdleTimeout:       60 * time.Second,
}
```

### 2.9 【Bug】无优雅退出，`defer CloseDB()` 永远不会执行

`go_projects/app_server.go:56-73`

`router.Run` 阻塞直到出错，进程收到 `SIGTERM` 时由内核直接终止 —— Go 的 defer 不会因信号执行。第 56 行的 `defer CloseDB()` 与 `defer cancel()` 在容器 `docker stop` 时都不会跑，正在处理的请求被硬切断（客户端看到连接重置）。

**修复**：

```go
ctx, stop := signal.NotifyContext(context.Background(), os.Interrupt, syscall.SIGTERM)
defer stop()

go func() {
    if err := srv.ListenAndServe(); err != nil && !errors.Is(err, http.ErrServerClosed) {
        log.Fatalf("run server: %v", err)
    }
}()

<-ctx.Done()
shutdownCtx, cancel := context.WithTimeout(context.Background(), 10*time.Second)
defer cancel()
_ = srv.Shutdown(shutdownCtx)
```

### 2.10 【性能】PHP-FPM 无 `request_terminate_timeout`，且 `max_children` 仅 10

`services/php/php-fpm.conf`

```ini
pm = dynamic
pm.max_children = 10
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
```

两个问题：

1. **缺 `request_terminate_timeout`**。`max_execution_time = 30` 只约束 PHP 用户态 CPU 时间，**不约束阻塞 I/O**（`sleep`、等 MySQL 锁、等 Kafka）。`kafka_consumer.php` 里 `consume(120000)` 一挂就是 120 秒，10 个 worker 被逐个吃掉，第 11 个请求开始全站 502。必须加 `request_terminate_timeout = 60s` 兜底。
2. `max_children = 10` 配合 Nginx `worker_processes 1`，整套栈的并发上限就是 10，`add_user.php` 自己就要 32 个连接 —— 这类脚本必须走 CLI，不能进 FPM。

### 2.11 【配置】PHP85 的 `listen` 与 Nginx 的 `fastcgi_pass` 自相矛盾

- `services/php85/php-fpm.conf:4`：`listen = 127.0.0.1:9000`
- `services/nginx/conf.d/localhost.conf:42,87,121,147`：`fastcgi_pass php85:9000`

容器之间无法通过 `127.0.0.1` 互访。当前能跑通，纯粹是因为官方 `php:*-fpm` 镜像自带的 `zz-docker.conf` 在 `www.conf` 之后加载、把 `listen` 又覆盖回 `9000`。这属于"靠镜像默认值兜底"，一旦基础镜像调整加载顺序，全站立刻 502。

**修复**：把 `php-fpm.conf` 的 `listen` 显式改成 `9000`（或 `0.0.0.0:9000`），不要依赖覆盖顺序。

---

## 3. P2：中低风险问题

| # | 位置 | 类型 | 问题 | 建议 |
|---|---|---|---|---|
| 3.1 | `app_routes.go:438-443` | 安全 | `respondError(c, 500, err.Error())` 把原始错误返回给客户端，会泄露内网 IP、DSN、SQL 片段 | 500 返回固定文案，详情只写日志并带 `request_id` |
| 3.2 | `app_routes.go:337-341` | Bug | `CreateUser` 遇 `username` 唯一键冲突（MySQL 1062）返回 500 | 识别 1062 返回 `409 Conflict` |
| 3.3 | `app_routes.go:30-38` | Bug | `Username` 无长度校验，表里是 `VARCHAR(50)`；超长在严格模式抛 1406 → 500 | 加 `binding:"required,max=50"`；`Email` 加 `max=100` |
| 3.4 | `app_routes.go:32,38` | Bug | `Age` 无范围校验，可传负数或 9999 | 加 `binding:"gte=0,lte=150"` |
| 3.5 | `app_routes.go:243-248` | Bug | `/health` 不探测数据库，DB 挂了仍返回 `ok`，容器健康检查形同虚设 | 加 `Ping` 并区分 liveness / readiness |
| 3.6 | `app_database.go:42-51` | Bug | DSN 用 `fmt.Sprintf` 拼装，密码含 `@ : /` 即解析失败 | 改用 `mysql.NewConfig()` + `FormatDSN()` |
| 3.7 | `app_database.go:33` | 安全 | 默认密码硬编码 `123456` | 无环境变量时直接报错退出，不给默认值 |
| 3.8 | `app_database.go:44` | Bug | `loc=Local` + `parseTime=True`，时区正确性完全依赖 Go 容器与 MySQL 容器 `TZ` 一致；`mysql.cnf` 里的 `default-time-zone` 又没挂载 | 统一 `loc=UTC`，展示层再转时区 |
| 3.9 | `app_server.go:42-47` | 性能 | 每请求一次 `log.Printf`（全局锁 + 同步写），且不记录状态码与耗时，压测时既拖慢又看不到瓶颈 | 换结构化日志，记录 `status` / `latency` / `request_id` |
| 3.10 | `app_server.go:44` | 安全 | `c.ClientIP()` 在未调用 `SetTrustedProxies` 时信任任意 `X-Forwarded-For`，客户端可伪造来源 IP | 显式 `router.SetTrustedProxies([]string{"172.16.0.0/12"})` 或 `nil` |
| 3.11 | `app_server.go:13-15` | 配置 | `GIN_MODE` 未设置时默认 debug，生产会打印路由表与警告 | compose 中显式 `GIN_MODE=release` |
| 3.12 | `app_routes.go:424-426` | 设计 | 所有接口统一 3 秒超时，列表接口和数据写入用同一阈值 | 按接口区分（列表 5s、写 3s、导出 30s） |
| 3.13 | `app_routes.go:428-436` | Bug | `parseIDParam` 接受 `0` 和负数 | 加 `id <= 0` 判断返回 400 |
| 3.14 | `app_routes.go:416-422` 及各方法 | 冗余 | `requireStore()` 与每个方法里的 `s == nil \|\| s.db == nil` 重复判断，且该分支在正常运行中不可达 | 启动时保证 store 非空，删掉防御代码 |
| 3.15 | `add_goods.php:87,160,201` | 性能 | 循环内每次 `prepareAndBind` 都重新 `prepare` 同一 SQL，共 2200 次编译 | 循环外 prepare 一次，循环内只 `bind_param` + `execute` |
| 3.16 | `add_goods.php` 全文 | 性能 | 无事务，2200 次独立提交 = 2200 次 `fsync`，比批量事务慢 10–50 倍 | 整体包在 `begin_transaction` / `commit` 中 |
| 3.17 | `addusers.php:69-75` | Bug | `CREATE TABLE IF NOT EXISTS` 无唯一索引，且每次运行都追加 100 万行，表无限膨胀 | 加 `TRUNCATE` 选项（默认关闭，显式 `?truncate=1` 才执行） |
| 3.18 | `addusers.php:33` | Bug | `ob_end_clean()` 在无输出缓冲时产生 Notice | 先判断 `ob_get_level() > 0` |
| 3.19 | `kafka_consumer.php:14-29` | Bug | 未设 `enable.auto.commit=false`，默认自动提交为 at-most-once，处理中崩溃即丢消息；broker 全挂时 `while(true)` 无退避，CPU 空转打满 | 关闭自动提交，处理完手动 `commit`；异常分支加 `usleep` 退避 |
| 3.20 | `kafka_consumer.php` | Bug | 无 `$consumer->close()`、无信号处理，`docker stop` 时 offset 可能未提交 | 注册 `SIGTERM` 处理器，退出前 `close()` |
| 3.21 | `integration_test.go:42` | 风险 | `DELETE FROM users` 清表，若 `MYSQL9_HOST` 误指向真实库则数据全丢 | 加库名白名单断言（如必须以 `_test` 结尾） |
| 3.22 | `integration_test.go:99-102` | 安全 | `fmt.Sprintf` 把环境变量拼进 `CREATE DATABASE` | 校验 `DBName` 只含 `[A-Za-z0-9_]` |
| 3.23 | `go_projects/Dockerfile` | 安全 | 最终镜像以 root 运行，未加 `USER`；也未加 `-ldflags="-s -w"` 瘦身 | 建非 root 用户；构建加 `-trimpath -ldflags="-s -w"` |
| 3.24 | `docker-compose.yml:443-456` | 部署 | `golang` 服务没有 `command`，只有 `tty: true`，容器空转不跑应用；也没有 `depends_on` / healthcheck | 补 `command: go run .` 或改为构建好的镜像 + `depends_on: mysql9: {condition: service_healthy}` |
| 3.25 | `services/nginx/nginx.conf` | 性能 | `worker_processes 1`、`access_log /dev/null`、`server_tokens on`、未开 gzip | 改 `auto`；access_log 保留但可 `buffer=32k flush=5s`；关 `server_tokens` |
| 3.26 | `add_goods.php:33` | 数据合规 | 示例数据把 `Taiwan` 与国家名并列 | 改为 `Taiwan, China`（中国台湾） |
| 3.27 | `add_user.php:98-112` | 风险 | `PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false` 叠加 `EMULATE_PREPARES => true`，同一连接上并发执行多条非缓冲查询会报 *Cannot execute queries while other unbuffered queries are active* | 保持默认缓冲模式，或改用原生 `mysqli` 批量插入 |

---

## 4. 建议的修复顺序

**第一批（本周，防事故）**

1. `www/localhost/` 数据脚本加 CLI 守卫 + 迁出 Web 根（1.4）
2. 修 `add_goods.php` 的 `continue` 与语句关闭（1.1）
3. 修 `add_user.php` 互斥锁作用域（1.2）
4. 删除 `phpinfo()` 与 `display_errors`（1.3）

**第二批（性能，立竿见影）**

5. 挂载 `mysql.cnf` 并调 `innodb_buffer_pool_size`（1.5）—— 预计插入吞吐提升数倍
6. `addusers.php` 缓冲降到 1 万行 + `fputcsv`（2.1 / 2.2）—— 内存降一个数量级
7. `add_user.php` 批量降到 2000、会话参数移到连接池配置、删除 `DISABLE KEYS`（2.3 / 2.5）
8. `ListUsers` 加游标分页（1.6）

**第三批（健壮性）**

9. `http.Server` 超时 + 优雅退出（2.8 / 2.9）
10. CORS 白名单（2.7）、错误信息脱敏（3.1）、输入校验（3.3 / 3.4）
11. `UpdateUser` 单语句 + `clientFoundRows`（2.6）
12. PHP-FPM `request_terminate_timeout`（2.10）、`listen` 显式化（2.11）

---

## 5. 未覆盖范围说明

- `www/laravel`、`www/thinkphp8`、`www/hyperf-skeleton`、`www/webman`、`www/workerman*` 为第三方框架骨架代码，不在本次审查范围。
- `www/localhost/vendor/` 为 Composer 依赖，未审查。
- 宿主机无 Go 工具链，`go vet`、`go test -race`、`staticcheck` 未执行。建议在 `golang` 容器内补跑：

```bash
docker exec -it golang sh -c "cd /go/src/app && go vet ./... && go test -race ./..."
```
