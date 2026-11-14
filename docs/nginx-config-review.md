# Nginx 配置审查：问题与优化空间

- 审查日期：2026-11-13
- 审查范围：`services/nginx/`（`nginx.conf`、`conf.d/*.conf`、`fastcgi-php.conf`、`fastcgi_params`、`Dockerfile`、`ssl/`）、`services/openresty/openresty.conf`、`docker-compose.yml` 与 `.env` 中 nginx 相关段
- 版本与容器名：审查时为 `.env:59` `NGINX_VERSION=1.20.2-alpine`；**现已升级为 `NGINX_VERSION=1.28-alpine`（nginx/1.28.3）**，nginx 服务**未设置 `container_name`**（Compose 自动命名 `docker-nmp-nginx-1`），发布 80 / 443 / 8142
- 说明：**审查时未修改任何配置文件**，所有改动以"建议片段"形式给出；后续已落地的升级、改名、401 修复与 **P0 全量修复（含补充项 1.7）** 见文末「附：后续变更记录」

---

## 0. 结论摘要

共 **24 项**问题：P0（会直接导致站点异常或安全风险）6 项、P1（明确的性能损失）8 项、P2（可观测性 / 健壮性 / 可维护性）10 项。

> **状态更新**：P0（6 项 + 补充 1.7）与 P1（8 项）已于 2026-11-13 / 11-14 **全部处理并实测通过**（第 10、11 项）；P2 的 3.1–3.14、3.16 已于 2026-11-14 **全部改完**（第 12 项），3.15 此前已处理、3.17 为基准项。**本报告的 24 项问题至此全部有结论**，P2 的运行时验证待 Docker Desktop 启动后补做。

| 优先级 | 一句话结论 |
|---|---|
| P0（**已全部处理**） | 原问题：`tp6.local` 指向不存在的目录；workerman/webman 的 `upstream` 是死配置且强制给 HTTP 站点的 Cookie 加 `Secure`；8142 端口无鉴权暴露 XHGui；`stub_status` 白名单 `172.0.0.0/8` 过宽；nginx 1.20.2 已停止维护 |
| P1（**已全部处理**） | 原问题：单 worker + 1024 连接数、`gzip` 关闭、FastCGI 缓冲默认 2×8k、反向代理无 keepalive、TLS 无会话复用 —— 五项都是"改一行就有收益" |
| P2（**已全部处理**） | 原问题：全站 access_log 写进 `/dev/null`、`log_format` 定义了却没用、缺 `$request_time`，出问题时几乎无法定位慢在 nginx 还是 PHP |

**审查时投入产出比最高的三件事**（第 1、2 项已完成，第 3 项属 P2 仍未应用）：

1. ~~把 `worker_processes` / `worker_connections` 调大并打开 gzip~~ —— **已处理**：`worker_processes 2` / `worker_connections 8192` / `multi_accept on` / `worker_rlimit_nofile 65535`，gzip 已开并对 JSON/JS/CSS/SVG 等生效（见文末第 11 项）。
2. ~~修 `workerman.conf` 的 `proxy_pass` 与 `proxy_cookie_path`~~ —— **已处理**：upstream 已启用、`keepalive 32` + HTTP/1.1 生效，Cookie 不再加 `Secure`（见文末第 7、10 项）。
3. ~~把 `access_log` 从 `/dev/null` 改成带缓冲的真实文件，并在 `log_format` 里补 `$request_time` / `$upstream_response_time`~~ —— **已处理**：`access_log /var/log/nginx/access.log main buffer=32k flush=5s`，格式补了 `rt/uct/uht/urt/ua`（见文末第 12 项）。

---

## 1. P0：功能性错误与安全风险

### 1.1 `tp6.local` 的 root 指向不存在的目录

> **状态：已处理** —— 该冗余 server 块已从 `localhost.conf` 整体删除（用户自行修改），现仅保留 `tp8.local`。以下保留审查时的原始描述。

`services/nginx/conf.d/localhost.conf:100-104`

```nginx
server_name  tp6.local;
root   /www/tp8/public/;      # ← 目录不存在，且名字与 server_name 不符
```

已在宿主机确认：`www/tp8/` **不存在**，只有 `www/thinkphp8/`。访问 `tp6.local` 必然 404 / 403。这是复制粘贴遗留：第 127-130 行的 `tp8.local` 用的是正确的 `/www/thinkphp8/public`。

**修复**：把 `tp6.local` 的 root 改为实际存在的目录，或直接删掉这个冗余 server（与 `tp8.local` 指向同一应用时保留一个即可）。

---

### 1.2 `upstream` 声明了却完全没被使用

> **状态：已于 2026-11-13 处理**（见文末第 10 项）：两个 upstream 改指 `php85:2345` / `php85:8787`、`keepalive 10240 → 32`、`proxy_pass` 改用 upstream 名，并补齐 `proxy_http_version 1.1` / `Connection` / `X-Forwarded-Proto`，另加 `map` 兼容 WebSocket。以下保留审查时的原始描述。

`services/nginx/conf.d/workerman.conf:2-5, 27, 31-34, 57`

```nginx
upstream workerman {
    server 127.0.0.1:8181;      # ← 地址也不对
    keepalive 10240;
}
...
proxy_pass http://php85:2345;    # ← 直接写死主机名，绕过了 upstream
```

`webman` 那段同样：`upstream` 写 `127.0.0.1:8787`，`proxy_pass` 却写 `php85:8787`。

后果：

- 两个 `upstream` 块是**死配置**，负载均衡、`keepalive`、故障转移一个都没生效；
- `keepalive 10240` 即便生效也是灾难：单 worker 维持 10240 条空闲连接，每条占一个 fd 和缓冲区。合理值是 32–256；
- 容器内 `127.0.0.1:8181` / `8787` 并不监听（服务在 `php85` 容器里），若真改成 `proxy_pass http://workerman;` 会立刻 502。

**修复**：

```nginx
upstream workerman {
    server php85:2345;
    keepalive 32;
}
server {
    ...
    location / {
        proxy_http_version 1.1;                 # 必须，否则默认 HTTP/1.0
        proxy_set_header Connection "";          # 必须，否则每请求重建 TCP
        proxy_set_header Host $http_host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;   # 当前缺失
        proxy_pass http://workerman;
    }
}
```

> 缺 `proxy_http_version 1.1` + `Connection ""` 是这份配置里最隐蔽的性能损失：每次请求都要与 `php85` 重新三次握手，长连接应用（Workerman / webman）的性能优势基本被抵消。

若这些站点用于 WebSocket，还需要补：

```nginx
proxy_set_header Upgrade $http_upgrade;
proxy_set_header Connection "upgrade";
proxy_read_timeout 3600s;
```

---

### 1.3 在纯 HTTP 站点上强制给 Cookie 加 `Secure`，会话会静默失效

> **状态：已于 2026-11-13 处理**（见文末「附：后续变更记录」第 7 项）。以下保留审查时的原始描述。

`services/nginx/conf.d/workerman.conf:24, 54`

```nginx
proxy_cookie_path / "/; secure; HttpOnly; SameSite=strict";
```

两个 server 都是 `listen 80`，却把上游返回的所有 Cookie 改写成 `Secure`。浏览器在 http:// 下**不会回传** Secure Cookie，Workerman / webman 的登录态、Session 会直接失效，而且现象是"登录成功但一刷新就掉线"，排查成本高。

**修复**：开发环境去掉 `secure`；上 HTTPS 后再加，且建议让应用自己设置 Cookie 属性，nginx 不要越权改写。

---

### 1.4 XHGui 在 8142 端口裸奔

> **状态：已于 2026-11-13 处理**（见文末第 10 项）：8142 只绑定 `127.0.0.1`，并加来源白名单 + `auth_basic`；顺带删掉重复的 `SCRIPT_FILENAME`。以下保留审查时的原始描述。

`services/nginx/conf.d/xhgui.conf:4-10` + `docker-compose.yml:14` + `.env:328`

```nginx
listen 8142;
server_name localhost;
# 无任何 allow / deny、无 auth_basic
```

compose 把 `${XHGUI_HOST_PORT:-8142}:8142` 发布到宿主机。XHGui 默认没有鉴权，任何人访问即可查看（甚至删除）性能分析数据 —— 这些数据常包含完整请求参数、SQL、运行时环境变量。同时它与 `localhost` 共用 `server_name localhost`，仅靠端口区分。

**修复**：加内网白名单 + 基础认证，或至少限制来源：

```nginx
server {
    listen 8142;
    server_name localhost;
    allow 127.0.0.1;
    allow 172.16.0.0/12;
    deny all;
    auth_basic "XHGui";
    auth_basic_user_file /etc/nginx/.htpasswd;
    ...
}
```

顺带：`xhgui.conf:21` 的 `fastcgi_param SCRIPT_FILENAME` 与 `fastcgi_params:2` 完全重复，可删。

---

### 1.5 `stub_status` 的白名单网段过宽

> **状态：已于 2026-11-13 处理**（见文末第 10 项）：`allow 172.0.0.0/8` → `allow 172.16.0.0/12`。以下保留审查时的原始描述。

`services/nginx/conf.d/localhost.conf:34-36`

```nginx
allow 127.0.0.1;
allow 172.0.0.0/8;   # Docker 网络
deny all;
```

`172.0.0.0/8` 覆盖 172.0.0.0–172.255.255.255，而 Docker 默认网段是私有的 `172.16.0.0/12`。这个 `/8` 里包含大量**公网地址**，等于把 `nginx_status`（活跃连接数、请求总量）开放给了相当广的来源。配合 `server_tokens on`，连版本号一起送出去。

**修复**：`allow 172.16.0.0/12;`，或干脆 `allow 127.0.0.1;` + 从容器内 `curl localhost/nginx_status` 采集。

---

### 1.6 nginx 1.20.2 早已停止维护

> **状态：已于 2026-11-13 处理**（见文末第 1 项）：已升级为 `nginx:1.28-alpine`（nginx/1.28.3），并适配 `http2 on;`、重签 2048-bit 证书。以下保留审查时的原始描述。

`.env:59` → `services/nginx/Dockerfile:2` → `FROM nginx:1.20.2-alpine`。

1.20 稳定分支在 1.22 发布后即停止维护，1.20.2 是 2021 年的构建。对外部暴露 80/443/8142 的容器运行一个多年未打补丁的 nginx，风险不需要多说。

**升级注意事项**（重要，否则升级后配置报错/告警）：

- 升级到 **≥ 1.25.1** 时，`listen 443 default ssl http2;` 中的 `http2` 参数被废弃，需改成独立的 `http2 on;` 指令（`localhost.conf:56`）。
- 1.23.2 起 `ssl_protocols` 默认值收紧为 TLSv1.2/TLSv1.3；当前 1.20.2 的默认偏宽松，升级前后都应显式声明。
- `ssl` 指令（非 `listen` 参数）在 1.25 起也被移除，本配置未使用，无影响。

建议目标：`nginx:1.28-alpine`（或当前 stable），并在升级后用 `nginx -t` 验证。

---

### 1.7 （补充）Web 根目录没有任何敏感路径防护

> **状态：已于 2026-11-13 处理**（见文末第 10 项）：80/443 两个 server 均加隐藏文件、`vendor|composer.json|composer.lock`、备份/日志/SQL 后缀三组 deny 规则，并放行 `.well-known`。**注意（已实测）**：`addusers.php` / `add_user.php` 等 CLI 脚本仍可通过 Web 触发 —— 本次基线探测请求 `/addusers.php` 即触发脚本，21:48:44 生成了 111 MB `www/localhost/temp.csv`，随后因 `mysql5` 主机不存在（compose 中已注释）在 `addusers.php:61` 抛 `mysqli_sql_exception` 终止，**未写库、未执行 `LOAD DATA`**，临时 CSV 已清理。这属于代码审查报告 1.4 的 P0，本次按用户选定范围未额外拦截（避免改变现有 Web 调试方式）；若需 nginx 侧兜底，可在两个 server 的 deny 规则组追加 `location ~ ^/(addusers|add_user|add_goods|swoole|kafka_consumer)\.php$ { deny all; }`。以下保留审查时的原始描述。

`services/nginx/conf.d/localhost.conf:50-52, 95-97` 的 `.htaccess` 拒绝规则是注释状态，`root /www/localhost` 下存在 `composer.json`、`composer.lock`、`names.txt`、`vendor/`。结合上一轮代码审查的结论 —— 同目录下还有 `addusers.php`（触发 100 万行写入）、`add_user.php`（含 `DROP TABLE`）、`index.php`（`echo phpinfo()`）—— nginx 层面没有任何一道闸门。

**修复**（nginx 侧至少先兜住）：

```nginx
location ~ /\.                    { deny all; }   # .git / .env / .htaccess
location ~ ^/(vendor|composer\.(json|lock)) { deny all; }
location ~ \.(sql|log|bak|swp)$  { deny all; }
```

根本解法仍是把 CLI 脚本移出 Web 根目录，这不是 nginx 配置能单独解决的。

---

## 2. P1：性能瓶颈

### 2.1 单 worker + 1024 连接数，并发上限被硬锁死

> **状态：已于 2026-11-14 处理**（见文末第 11 项）：`worker_processes 2`、`worker_connections 8192`、`multi_accept on`、`worker_rlimit_nofile 65535`；`openresty.conf` 同步修改。**未用 `auto`** —— 容器内 nginx 读到的是宿主机 12 核、不感知 cgroup 配额。以下保留审查时的原始描述。

`services/nginx/nginx.conf:3, 9`；`services/openresty/openresty.conf` 同样问题

```nginx
worker_processes  1;
events { worker_connections  1024; }
```

- 只有一个进程，多核机器上其余 CPU 完全闲置；
- 1024 是**每 worker** 的连接数，且代理请求要占 2 个 fd（客户端 + 上游），实际可同时处理的代理请求约 512；
- 前面提到 PHP-FPM `pm.max_children = 10`，两者叠加后这套栈的并发上限在 10 左右。

**修复**：

```nginx
worker_processes  2;              # 开发机建议显式给小值
worker_rlimit_nofile 65535;
events {
    worker_connections  8192;
    multi_accept on;
    # use epoll;                # Linux 下 nginx 会自动选，可省略
}
```

> **Docker 陷阱**：不要无脑写 `worker_processes auto;`。nginx 1.20 用 `sysconf(_SC_NPROCESSORS_ONLN)` 读取的是**宿主机** CPU 数，不感知 cgroup 配额；compose 里 nginx 没有 `cpus` 限制，在 32 核开发机上会拉起 32 个 worker，反而因上下文切换和内存占用拖慢。生产环境用 `auto` 时请同时配 `cpus` 限制。

### 2.2 gzip 关闭、`tcp_nopush` 被注释

> **状态：已于 2026-11-14 处理**（见文末第 11 项）：`tcp_nopush on`；`gzip on` + `gzip_vary` / `gzip_comp_level 5` / `gzip_min_length 1024` / `gzip_proxied any` / `gzip_types` 覆盖 JSON、JS、CSS、XML、SVG。实测 PHP 响应返回 `Content-Encoding: gzip` 与 `Vary: Accept-Encoding`。以下保留审查时的原始描述。

`services/nginx/nginx.conf:27, 32`

```nginx
#tcp_nopush     on;
#gzip  on;
```

`sendfile on` 已开，但没配 `tcp_nopush` —— 少了"攒满一个 TCP 包再发"的优化；gzip 完全关闭，`www/` 下有大量 `.js` / `.css` / `.md` / JSON，全部原样传输。

**修复**：

```nginx
tcp_nopush  on;
gzip on;
gzip_vary on;
gzip_comp_level 5;
gzip_min_length 1024;
gzip_proxied any;
gzip_types
    text/plain text/css text/xml application/json application/javascript
    application/xml+rss application/xhtml+xml image/svg+xml;
```

注意 `gzip_types` 默认只含 `text/html`，不显式列 `application/javascript` / `application/json` 等于大部分静态资源没被压缩。

### 2.3 FastCGI 缓冲区停留在默认 2×8k，且读超时 1 小时

> **状态：已于 2026-11-14 处理**（见文末第 11 项）：`fastcgi_buffer_size 32k`、`fastcgi_buffers 8 16k`、`fastcgi_busy_buffers_size 64k`、`connect 5s` / `send 30s` / `read 60s`（原 3600）、`fastcgi_keep_conn on`。实测连续 10 次 PHP 请求全部 200，`nginx.error.log` 无 FastCGI 错误。以下保留审查时的原始描述。

`services/nginx/fastcgi-php.conf:12`

```nginx
fastcgi_read_timeout 3600;
```

两个方向的问题：

- **缓冲**：nginx 默认 `fastcgi_buffers 2 8k`（仅 16k）。任何超过 16k 的 PHP 响应（几乎是所有真实页面）都会边收边分批写回客户端，甚至落临时文件。这是 `c.JSON` 大响应、phpMyAdmin 页面慢的常见隐性原因。
- **超时**：3600 秒意味着一个卡死的 PHP-FPM 请求能把一个 nginx worker 和一个 FPM child 占用一小时。上一轮报告里 PHP-FPM 又没设 `request_terminate_timeout`，两者叠加，几个挂起请求就能让全站 502。

**修复**（放进 `fastcgi-php.conf`，全局生效）：

```nginx
fastcgi_buffer_size      32k;
fastcgi_buffers          8 16k;
fastcgi_busy_buffers_size 64k;
fastcgi_connect_timeout  5s;
fastcgi_send_timeout     30s;
fastcgi_read_timeout     60s;     # 从 3600 降到 60，长任务单独 location 覆盖
fastcgi_keep_conn        on;      # 配合 FPM 减少握手
```

> `fastcgi_keep_conn on` 需要 FPM 端支持长连接，先在测试环境验证；Alpine 官方 php-fpm 镜像可用。

### 2.4 TLS 没有会话复用，且未显式限制协议

> **状态：已于 2026-11-14 处理**（见文末第 11 项）：`ssl_protocols TLSv1.2 TLSv1.3`、`ssl_prefer_server_ciphers off`、`ssl_session_cache shared:SSL:10m`、`ssl_session_timeout 10m`。
>
> **两处有意偏离报告建议**：① `ssl_session_tickets` 定为 **`on`** 而非报告写的 `off` —— TLS1.3 的复用只能靠 ticket（无 session id），`off` 会让"减少握手"的目标在 TLS1.3 下完全失效（实测 `-reconnect` 全为 `New`）；改为 `on` 后 TLS1.2 出现 `Reused`、TLS1.3 `-sess_in` 也返回 `Reused`。② **未加 HSTS** —— 本机还有 `webman.local` / `workerman.local` / `tp8.local` 纯 HTTP 站点，加 HSTS 会让浏览器强制 HTTPS、这些站点直接不可用。以下保留审查时的原始描述。

`services/nginx/conf.d/localhost.conf:56, 68-69`

```nginx
listen 443 default ssl http2;
ssl_certificate     /ssl/localhost/localhost.crt;
ssl_certificate_key /ssl/localhost/localhost.key;
# 没有 ssl_session_cache / ssl_session_timeout / ssl_protocols / ssl_ciphers
```

`ssl_session_cache` 默认是 `none`，跨连接的会话复用能力受限；握手开销在压测里非常明显。此外 1.20.2 的默认 `ssl_protocols` 仍包含 TLSv1/TLSv1.1。

**修复**：

```nginx
ssl_protocols             TLSv1.2 TLSv1.3;
ssl_prefer_server_ciphers off;           # TLS1.3 下无须优先服务端套件
ssl_session_cache         shared:SSL:10m;
ssl_session_timeout       10m;
ssl_session_tickets       off;
add_header Strict-Transport-Security "max-age=31536000" always;   # 确认全站 HTTPS 后再加
```

`ssl/localhost/localhost.key` 是自签私钥且提交在仓库里，仅适用于本地开发；生产应换正式证书并把私钥移出版本库。

### 2.5 缺少静态资源缓存与 `open_file_cache`

> **状态：已于 2026-11-14 处理**（见文末第 11 项）：http 块加 `open_file_cache max=4096 inactive=30s` / `valid 60s` / `min_uses 2` / `errors on`；`localhost` 443 与 `tp8.local` 加静态资源 location（`expires 7d` + `Cache-Control: public, immutable`，实测 `favicon.ico` 返回 `max-age=604800` + `public, immutable`）。`webman` / `workerman` 两个纯反代站点**未加**，静态资源仍交上游处理，避免绕过应用。以下保留审查时的原始描述。
>
> 注意：`open_file_cache_errors on` 会把 404 缓存最长 60s，新建文件后可能短暂 404，需要即时可见就把 `open_file_cache_valid` 调小。

所有 server 都没有 `expires` / `Cache-Control`，也没有 `open_file_cache`。而 `fastcgi-php.conf:6` 的 `try_files $fastcgi_script_name =404` 会在**每个 PHP 请求**上对脚本做一次 stat —— 这是本配置里最容易被忽略的系统调用开销。

**修复**（`nginx.conf` 的 http 块）：

```nginx
open_file_cache          max=4096 inactive=30s;
open_file_cache_valid    60s;
open_file_cache_min_uses 2;
open_file_cache_errors   on;
```

各 server 内：

```nginx
location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff2?|ttf|map)$ {
    expires 7d;
    add_header Cache-Control "public, immutable";
    access_log off;
    try_files $uri =404;
}
```

### 2.6 无 HTTP → HTTPS 跳转

> **状态：已于 2026-11-14 处理**（见文末第 11 项）：`localhost` 的 80 server 改为 `return 301 https://$host$request_uri`，PHP 只在 443 处理；`/nginx_status`（供 exporter 采集）与 `/.well-known/acme-challenge/`（ACME 续签）保留 HTTP 例外。**行为变化**：`http://localhost/...` 现在会 301 到 HTTPS（自签证书会弹警告）；`webman.local` / `tp8.local` 是独立 server，仍走 HTTP、不受影响。以下保留审查时的原始描述。

`localhost.conf` 里 80 和 443 两个 server 内容几乎完全一致，各自独立提供 PHP。既然签了证书并启用 http2，80 端口却没有任何重定向，HTTPS 形同"可选"。

**修复**：把 80 的 server 改成重定向（保留 `/nginx_status` 例外），PHP 只在 443 处理：

```nginx
server {
    listen 80 default;
    server_name localhost;
    location /nginx_status { stub_status on; access_log off; allow 127.0.0.1; allow 172.16.0.0/12; deny all; }
    location / { return 301 https://$host$request_uri; }
}
```

### 2.7 `keepalive 10240` 与 upstream 死配置叠加

> **状态：已随 1.2 处理**：两个 upstream 接通的同时 `keepalive` 已降到 `32`（见文末第 10 项），实测连续请求后到 8787 仅保持 1 条 ESTABLISHED 连接。

见 1.2。这两个 upstream 一旦按建议接通，10240 的连接数会立刻变成真实的资源占用，务必同步降到 32–256。

### 2.8 `client_max_body_size 100M` 与缓冲未配

> **状态：已于 2026-11-14 处理**（见文末第 11 项）：`client_max_body_size` 100M → **128M**（与 php `post_max_size=100M` 留出 multipart 余量），并补 `client_body_buffer_size 256k`、`client_header_timeout 10s`、`client_body_timeout 30s`、`send_timeout 30s`。以下保留审查时的原始描述。

`nginx.conf:28` 允许 100M 请求体，但 `client_body_buffer_size` 用默认值（8k/16k），意味着几乎每个上传都会写临时文件。若确实需要大文件上传，建议：

```nginx
client_body_buffer_size 256k;
client_header_timeout   10s;
client_body_timeout     30s;
send_timeout            30s;
```

另外 `.env` 侧 php.ini 是 `post_max_size=100M` / `upload_max_filesize=50M`，与 nginx 的 100M 边界值建议统一留出余量。

---

## 3. P2：可观测性与健壮性

| # | 位置 | 问题 | 建议 |
|---|---|---|---|
| 3.1 | `nginx.conf:21`、`localhost.conf:11,62,111,138` | `access_log /dev/null` —— 全站零访问日志；`/dev/null` 仍会产生 write 系统调用与日志锁开销 | 改 `access_log /var/log/nginx/access.log main buffer=32k flush=5s;`；确实不需要时写 `access_log off;`（比 `/dev/null` 更省） |
| 3.2 | `nginx.conf:17-19` | `log_format main` **定义了却从未被使用**（所有 `access_log` 都指向 /dev/null，xhgui 用的是默认 `combined`） | 启用 3.1 后格式才生效 |
| 3.3 | `nginx.conf:17-19` | `main` 缺 `$request_time`、`$upstream_response_time`、`$upstream_addr` —— 无法判断慢在 nginx 还是 PHP-FPM | 补齐这三个字段；OpenResty 那份已有 `$request_time`，可借鉴 |
| 3.4 | `nginx.conf:25`、`localhost.conf:9` | `server_tokens on` 对外暴露精确版本号；且 `openresty.conf` 里是 `off`，两份配置自相矛盾 | 统一 `server_tokens off;`（http 块设一次即可，server 块那行删掉） |
| 3.5 | `nginx.conf` 全局 | 未显式设置 `client_header_timeout` / `client_body_timeout` / `send_timeout`，沿用 60s 默认值，Slowloris 防护偏松 | 收紧到 10–30s，见 2.8 |
| 3.6 | `fastcgi-php.conf:10-11` | `set $path_info $fastcgi_path_info;` 算出来了，但 `fastcgi_param PATH_INFO` 是注释 —— 死代码；依赖 PATH_INFO 的框架（含 ThinkPHP pathinfo 模式）拿不到值 | 要么启用 `fastcgi_param PATH_INFO $path_info;`，要么删掉 `set` 行 |
| 3.7 | `localhost.conf:107-109, 134-136` | `if (!-f $request_filename) { rewrite ... last; }` 是 "if is evil" 经典写法，且只判 `-f` 不判目录，带查询串时行为易错 | 改 `try_files $uri $uri/ /index.php?s=$uri&$args;`（需确认应用 URL 模式支持 `s` 参数） |
| 3.8 | `localhost.conf:41` vs `:120,:146` | PHP location 正则不统一：主站用 `[^/]\.php(/|$)`（支持 pathinfo），tp6/tp8 用 `\.php$`（不支持 `/index.php/foo`） | 抽成公共片段统一引用，避免逐个 server 复制 |
| 3.9 | `nginx.conf:6` | `error_log ... warn` 级别偏高，reload / resolver 类提示被吞掉 | 调试期用 `notice`，生产回到 `warn` |
| 3.10 | `openresty/openresty.conf:log_format` | 把 `$request_body` 写进访问日志 —— 会完整记录密码、Token、上传内容，日志体积暴涨且是敏感信息泄露 | 删除 `$request_body`；该服务当前在 compose 中是注释状态，启用前必须修 |
| 3.11 | `openresty/openresty.conf` | `access_log logs/access.log`（相对路径）、`include mime.types`（相对路径），与 nginx.conf 的绝对路径风格不一致；且同样 `worker_processes 1` / 1024 / 无 gzip | 统一成 `/usr/local/openresty/nginx/logs/...` 绝对路径，并同步 2.1/2.2 的优化 |
| 3.12 | `conf.d/.gitignore` | `*` 忽略全部，只放行 `localhost.conf` —— `workerman.conf`、`xhgui.conf`、`hyperf.conf`、`easyswoole.conf` 都未纳入版本控制，换机器/重建后这些站点会消失；`!certs/localhost/` 与实际的 `ssl/localhost/` 路径也对不上 | 把需要的 `.conf` 逐个加白名单 |
| 3.13 | `docker-compose.yml` nginx volumes | `${NGINX_CONFD_DIR}` 与 `${NGINX_SSL_CERTIFICATE_DIR}` 都是 `:rw`，nginx 只需读；私钥目录可写属于不必要的暴露面 | 改成 `:ro` |
| 3.14 | `docker-compose.yml` nginx | 无 `depends_on: php85`、`xhgui` 等。`fastcgi_pass php85:9000` 里的主机名在 **配置加载时**解析，若 PHP 容器尚未起来，nginx 会 `[emerg] host not found in upstream` 启动失败（靠 `restart: always` 反复重试才恢复） | 加 `depends_on` + healthcheck；或用 `resolver 127.0.0.11 valid=10s;` 配合变量做运行时解析 |
| 3.15 | `docker-compose.yml`（原 `container_name: nginx20`） | 固定容器名阻止 `--scale`，也不利于多实例 | 去掉 `container_name`，用服务名 `nginx` 访问（**已处理**：该行已删除，Compose 自动命名；README / alias 改用 `docker compose exec nginx ...`） |
| 3.16 | nginx 服务 | 无 healthcheck / 无 `limit_req` / 无 `limit_conn` | 加 `healthcheck: test: ["CMD", "nginx", "-t"]`；对外站点按需加限流 |
| 3.17 | `fastcgi_params` | 这份是最规范的文件：`if_not_empty` 的 `HTTPS`、完整 CGI 变量、`SCRIPT_FILENAME` 都在 | 保持不变，可作为其他片段的基准 |

**P2 落地情况（2026-11-14）**：

| # | 状态 | 改动 |
|---|---|---|
| 3.1 | 已处理 | `nginx.conf` 改为 `access_log /var/log/nginx/access.log main buffer=32k flush=5s;`；`localhost.conf` 里 80 / 443 / tp8 三处 `access_log /dev/null` 删除（继承 http 级）；`/nginx_status` 与静态资源仍 `access_log off` |
| 3.2 | 已处理 | `log_format main` 现在真正被使用；`xhgui.conf` 的 access_log 从默认 combined 改为 `main buffer=32k flush=5s` |
| 3.3 | 已处理 | `main` 补 `rt=$request_time uct=$upstream_connect_time uht=$upstream_header_time urt=$upstream_response_time ua=$upstream_addr` |
| 3.4 | 已处理 | http 级 `server_tokens off`（server 块里那行已在 P1 重写 80 server 时移除） |
| 3.5 | 已处理（随 2.8） | `client_header_timeout 10s` / `client_body_timeout 30s` / `send_timeout 30s` |
| 3.6 | 已处理 | `set $path_info` 移到 `try_files` **之前**（原顺序下 `$path_info` 恒为空），并启用 `fastcgi_param PATH_INFO $path_info;` |
| 3.7 | 已处理 | `tp8.local` 的 `if (!-f ...) rewrite` → `try_files $uri $uri/ /index.php?s=$uri&$args;`（ThinkPHP 8 `pathinfo_depr='/'`，`?s=` 模式受支持） |
| 3.8 | 已处理 | 新增 `conf.d/php-fcgi.inc`（`[^/]\.php(/|$)`），`localhost` 443 与 `tp8.local` 改为 `include /etc/nginx/conf.d/php-fcgi.inc;`；用 `.inc` 后缀避免被 http 级 `*.conf` 误加载 |
| 3.9 | 已处理 | `error_log ... warn` → `notice`（调试期，生产可改回） |
| 3.10 | 已处理 | openresty `log_format` 删除 `$request_body`，并修正原格式缺空格造成字段粘连的问题 |
| 3.11 | 已处理 | openresty `include mime.types` → `/usr/local/openresty/nginx/conf/mime.types`；`access_log logs/access.log` → `/var/log/nginx/openresty.access.log`（与 error_log 同一挂载卷）；同步 `tcp_nopush` + gzip |
| 3.12 | 已处理 | `conf.d/.gitignore` 放行 `localhost.conf` / `workerman.conf` / `xhgui.conf` / `easyswoole.conf` / `hyperf.conf` / `php-fcgi.inc`；`.htpasswd` 仍忽略；删除从未生效的 `!certs/localhost/` |
| 3.13 | 已处理 | compose nginx 的 `/ssl`、`/etc/nginx/conf.d` 由 `:rw` 改 `:ro`（`docker compose config` 已确认 `read_only: true`） |
| 3.14 | 已处理 | nginx 加 `depends_on: php85 / xhgui (service_started)`，避免上游主机名解析失败导致 `[emerg] host not found in upstream` |
| 3.15 | 已处理（此前） | 已删除 `container_name` |
| 3.16 | 已处理 | compose 加 `healthcheck: ["CMD","nginx","-t"]`（30s/5s/3/10s）；`nginx.conf` 定义 `limit_req_zone` / `limit_conn_zone`，XHGui 8142 启用 `limit_req burst=20 nodelay` |
| 3.17 | 无需改动 | `fastcgi_params` 作为基准保留 |

> **验证状态**：改动已完成静态校验 —— nginx 配置大括号配平、`docker compose config` 解析通过、`read_only` / `depends_on` / `healthcheck` 解析正确、`.gitignore` 放行结果符合预期。**运行时验证待 Docker Desktop 启动后补做**：`nginx -t`、`up -d --force-recreate nginx`（应用 `:ro` 与 healthcheck）、`access.log` 有输出且含 `rt=` / `urt=`、`Server` 头不含版本号、`tp8.local` 路由、healthcheck 转 healthy、8142 认证与限流、webman 不回归。

---

## 4. 可直接替换的优化版 `nginx.conf`

```nginx
user  nginx;
worker_processes  2;                    # 见 2.1 的 Docker 陷阱说明
worker_rlimit_nofile 65535;

pid        /var/run/nginx.pid;
error_log  /var/log/nginx/nginx.error.log notice;

events {
    worker_connections  8192;
    multi_accept on;
}

http {
    include       /etc/nginx/mime.types;
    default_type  application/octet-stream;

    log_format main '$remote_addr - $remote_user [$time_local] "$request" '
                    '$status $body_bytes_sent "$http_referer" '
                    '"$http_user_agent" "$http_x_forwarded_for" '
                    'rt=$request_time urt=$upstream_response_time '
                    'upstream=$upstream_addr';

    access_log /var/log/nginx/access.log main buffer=32k flush=5s;

    server_tokens off;

    sendfile      on;
    tcp_nopush    on;
    tcp_nodelay   on;

    client_max_body_size     100M;
    client_body_buffer_size  256k;
    client_header_timeout    10s;
    client_body_timeout      30s;
    send_timeout             30s;
    keepalive_timeout        65;
    keepalive_requests       1000;

    gzip on;
    gzip_vary on;
    gzip_comp_level 5;
    gzip_min_length 1024;
    gzip_proxied any;
    gzip_types text/plain text/css text/xml application/json
               application/javascript application/xml+rss
               application/xhtml+xml image/svg+xml;

    open_file_cache          max=4096 inactive=30s;
    open_file_cache_valid    60s;
    open_file_cache_min_uses 2;
    open_file_cache_errors   on;

    # TLS 全局参数（各 server 内仍需 ssl_certificate / key）
    ssl_protocols             TLSv1.2 TLSv1.3;
    ssl_session_cache         shared:SSL:10m;
    ssl_session_timeout       10m;
    ssl_session_tickets       off;
    ssl_prefer_server_ciphers off;

    include /etc/nginx/conf.d/*.conf;
}
```

> 应用前请先 `docker compose exec -T nginx nginx -t`（按服务名操作，不依赖容器名）。`keepalive_requests 1000` 在 1.20.2 / 1.28 均为默认值，显式写出仅为可读性。

---

## 5. 建议的落地顺序

**第一批（不改代码、风险最低）**

1. 备份 `services/nginx/nginx.conf` 后套用第 4 节优化版，`nginx -t` → `nginx -s reload`
2. 修 `fastcgi-php.conf` 的缓冲与超时（2.3）
3. `server_tokens off`、`access_log` 启用（3.1 / 3.4）

**第二批（修功能性错误）**

4. `tp6.local` 的 root（1.1）
5. `workerman.conf` 的 upstream / `proxy_http_version` / `proxy_cookie_path`（1.2 / 1.3）
6. `/nginx_status` 网段、xhgui 鉴权、dotfile 防护（1.5 / 1.4 / 1.7）

**第三批（结构性调整）**

7. 80 → 443 跳转（2.6）、静态资源缓存（2.5）
8. 抽取公共 PHP location 片段，统一 tp6/tp8 的 `try_files`（3.6 / 3.7 / 3.8）
9. nginx 升级到 1.28 + 同步修改 `listen ... http2` 语法（1.6）
10. compose 补 `depends_on` / healthcheck / `:ro` 挂载（3.13–3.16）

---

## 6. 本次审查的边界

- 审查时未修改任何配置文件，也未执行 `nginx -t`（宿主机无 nginx 可执行文件，需进 nginx 容器验证）；后续升级时已在容器内完成 `nginx -t` 验证。
- `services/nginx/conf.d` 下 `easyswoole.conf`、`hyperf.conf` 全文件均为注释（模板），仅提示其被 `.gitignore` 忽略（3.12），未做逐行评审。
- `services/openresty/conf.d/` 未展开（该服务在 compose 中为注释状态）。
- 未做实际压测，性能结论基于配置语义与 nginx 默认值推导；建议用 `wrk -t4 -c200 -d30s` 对优化前后做对比。

---

## 附：后续变更记录（2026-11-13）

本报告为审查快照，正文中「当前 1.20.2 / 容器名 nginx20」等描述均为审查时状态。审查后已落地以下变更：

1. **nginx 升级**（对应 1.6、落地顺序第 9 项）：`.env:59` 改为 `NGINX_VERSION=1.28-alpine`，实际运行 nginx/1.28.3（Alpine 3.23 / OpenSSL 3.5.5，含 `http_v2_module` / `http_v3_module`）。
2. **HTTP/2 语法适配**：`localhost.conf` 的 `listen 443 default ssl http2` 改为 `listen 443 default_server ssl;` + 独立 `http2 on;`；80/443 的 `default` 统一为 `default_server`。
3. **TLS 证书重签（升级硬阻断点）**：旧自签证书为 1024-bit RSA，OpenSSL 3.x 报 `SSL_CTX_use_certificate ... ee key too small`，`nginx -t` 直接失败；已替换为 2048-bit / SHA256 / SAN(`localhost`, `*.localhost`, `127.0.0.1`) 证书。
4. **容器名调整（两步）**：先把 `container_name: nginx20` 统一为 `nginx`，再按 3.15 的建议**彻底删除 `container_name`**，由 Compose 自动命名 `docker-nmp-nginx-1`。`nginx-exporter` 注释里的 `-nginx.scrape-uri` 改为 `http://nginx:80/nginx_status`（用服务名，删掉 container_name 后仍可解析）。`README.md:131/403` 与 `bash.alias.sample:2` 的 `docker exec -it nginx ...` 全部改为 `docker compose exec nginx ...` —— 固定容器名移除后，旧写法会失效。
5. **验证**：容器内 `nginx -t` 通过且无废弃指令警告；80 / 443 / 8142 全 200；TLSv1.3 + `ALPN protocol: h2` 确认 HTTP/2 生效；`logs/nginx/nginx.error.log` 无新增错误。
6. **去掉 container_name 后的复验**：`docker compose up -d --force-recreate nginx` 后仅存自动命名的 `docker-nmp-nginx-1`，旧容器 `nginx` / `nginx20` 均已移除；`docker compose exec nginx nginx -t` 可用，`docker exec nginx ...` 按预期失效（应改用服务名）；网络内 `http://nginx:80/nginx_status` 仍返回 stub_status；`--scale` 不再被固定名字阻挡（但 `ports: 80:80` / `443:443` 的固定宿主映射仍会限制多实例，真正横向扩容还需改为端口范围或前置负载均衡）。

7. **修复 1.3：HTTP 站点的 Cookie `Secure`**（用户确认后落地）：`services/nginx/conf.d/workerman.conf:24,54` 两处 `proxy_cookie_path / "/; secure; HttpOnly; SameSite=strict";` 改为 `proxy_cookie_path / "/; HttpOnly; SameSite=Lax";`，去掉 `Secure`，并把 `SameSite=strict` 放宽为 `Lax`（strict 在跨站跳转时同样会丢会话）。配合同步把 `www/webman/app/middleware/AuthCheck.php` 的 `$except` 加入 `'/'` 放行首页（`git diff` 显示该项由用户自行添加），解决 `http://webman.local/` 返回业务 JSON `{"code":401,"msg":"未登录或登录已过期"}` 的问题。备份：`backup/webman-auth-fix-2026-11-13/`。**验证（Docker Desktop 启动后已完成）**：`nginx -t` 通过且无废弃警告、`nginx -s reload` 成功；手动 `docker compose exec -d php85 sh -c 'cd /www/webman && php start.php start'` 后 8787 LISTEN、WorkerMan master/worker 齐全；`http://webman.local/` 返回 `Hello Webman!`（HTTP 200），`/favicon.ico` 200；`/user/info` 未登录仍返回业务 401（鉴权未被绕过），其 `Set-Cookie` 为 `PHPSID=...; Max-Age=31536000; Path=/; HttpOnly; SameSite=Lax; HttpOnly`，**已无 `secure`**；curl cookie jar 第 4 列为 `FALSE` 且第二次请求确实回传 `Cookie: PHPSID=...`；往 Redis db1 注入一条 `redis_session_<sid> = a:1:{s:7:"user_id";i:1;}` 后带该 Cookie 请求 `/user/info`，返回控制器自己的 `{"code":401,"msg":"请传递用户ID"}`（不再是中间件的 `未登录或登录已过期`），证明「Cookie 回传 → session 命中 → AuthCheck 放行」链路打通，临时 key 已删除。16:33:57 之后 `nginx.webman.error.log` 无新增错误。

8. **鉴权从全局移到路由组（用户要求，webman PHP 层）**：`www/webman/config/middleware.php` 的 `'' => [AuthCheck::class]` 清空；`www/webman/config/route.php` 按「公开 / 需登录」重组，用 `Route::group('/user', ...)->middleware(AuthCheck::class)` 只保护 `/user/info`、`/user/get-by-id`、`/user/logout`；`www/webman/app/middleware/AuthCheck.php` 删除 `$except` 白名单。**坑**：`/user/{id}` 动态路由必须注册在所有 `/user` 静态路由之后，否则 nikic/fast-route 抛 `BadRouteException: Static route "/user/info" is shadowed by previously defined variable route "/user/([^/]+)"`，worker 全部 `exit with status 64000`，nginx 表现为 504。实测 10 条路由：7 条公开返回各自内容（HTTP 200），3 条受保护未登录返回业务 401，注入登录态后三条均放行；WorkerMan 进程 27 个稳定，无新增 502/504。备份 `backup/webman-route-auth-2026-11-13/`。注意安全模型已从 default-deny 反转为 **default-allow**：以后新增敏感接口必须主动挂 `AuthCheck`。

9. **PHP 8.5 启动报错修复（webman 层，与 nginx 配置无关）**：用户报 `ErrorException: Method ReflectionProperty::setAccessible() is deprecated since 8.5`（`vendor/webman/log/src/Middleware.php:263`）。触发链是 `config/app.php` 的 `error_reporting => E_ALL` + `support/bootstrap.php:31` 的 `set_error_handler` 把 `E_DEPRECATED` 转成 `ErrorException`；`webman/log` v2.1.3 的 `getCapsule()` 调用了 PHP 8.1 起无效果、8.5 起被弃用的 `setAccessible(true)`，且 `initDbListen()` 的 `catch { echo $e; }` 会把堆栈打到 stdout，看起来像启动失败（服务其实可用）。官方 **v2.1.4 已删除该调用**，因此用 `composer update webman/log --no-scripts --no-interaction` 升级（仅 1 update / 0 install，`composer.json` 未改），**没有手工 patch vendor**。重启后又发现第二类同类报错：`plugin/admin/app/controller/Crud.php:129` 的 `string $field = null` 和 `PluginController.php:512` 的 `string $default = null` 是 PHP 8.4+ 弃用的隐式可空参数，导致 worker `exit with status 64000`；改为显式 `?string $x = null`（语义不变）。全项目 `php -d error_reporting=E_ALL -l` 扫描已无编译期 deprecation；重启后新增日志仅 `stop` / `start in DEBUG mode`，0 个 ErrorException、0 个异常退出，WorkerMan 27 个进程稳定，首页/`/api/test`/`/user/info`/`/user/123` 全部正常。备份 `backup/webman-log-upgrade-2026-11-13/`、`backup/webman-php85-deprecations-2026-11-13/`。附带发现 `composer audit` 报 23 个安全公告 / 7 个包（guzzlehttp/guzzle、guzzlehttp/psr7、symfony/cache、symfony/http-foundation、symfony/http-kernel、symfony/mime、symfony/polyfill-intl-idn），**未处理**。

10. **P0 全量修复（用户要求「先将 P0 修复」）**：本次针对本报告 P0 6 项 + 补充项 1.7。其中 1.1（`tp6.local` 冗余 server 已删除）、1.3（Cookie `Secure`，见第 7 项）、1.6（nginx 1.28，见第 1 项）此前已处理，本次实际修改 1.2 / 1.4 / 1.5 / 1.7，并把全部 P0 在正文标注为已处理。

    - **1.2 `workerman.conf`**：`upstream workerman` / `upstream webman` 由 `127.0.0.1:8181` / `127.0.0.1:8787` 改为 `php85:2345` / `php85:8787`，`keepalive 10240 → 32`；两处 `proxy_pass` 由直写 `http://php85:...` 改为 `http://workerman` / `http://webman`；补 `proxy_http_version 1.1` 与 `proxy_set_header X-Forwarded-Proto $scheme`。Connection 头用 `map $http_upgrade $workerman_connection_upgrade { default upgrade; '' ''; }` 处理：带 Upgrade 头时透传 `upgrade`（兼容 WebSocket），否则为空字符串让 nginx 不发送该头，从而启用 upstream keepalive。保留上一轮的 `proxy_cookie_path / "/; HttpOnly; SameSite=Lax";`。
    - **1.4 `xhgui.conf` + `.env`**：`.env:328` 的 `XHGUI_HOST_PORT` 改为 `127.0.0.1:8142`（compose 的 `${XHGUI_HOST_PORT:-8142}:8142` 展开为 `127.0.0.1:8142:8142`，`docker compose config` 确认 `host_ip: 127.0.0.1`，**未改 compose**）；`xhgui.conf` 加 `allow 127.0.0.1; allow 172.16.0.0/12; deny all;` 与 `auth_basic` + `auth_basic_user_file /etc/nginx/conf.d/.htpasswd;`，并删掉与 `fastcgi_params:2` 重复的 `SCRIPT_FILENAME`。凭据文件 `services/nginx/conf.d/.htpasswd`（apr1 哈希、用户名 `admin`、一次性随机密码）被 `conf.d/.gitignore` 的 `*` 忽略、不入库；新环境需重新生成，否则 `nginx -t` 会因找不到文件而失败：
      ```bash
      printf 'admin:%s\n' "$(openssl passwd -apr1 '你的密码')" > services/nginx/conf.d/.htpasswd
      ```
    - **1.5 `localhost.conf`**：`allow 172.0.0.0/8` → `allow 172.16.0.0/12`（Docker 私有网段），容器来源 172.18.x 仍可访问 `/nginx_status`。
    - **1.7 `localhost.conf`（80 + 443 两个 server）**：在 PHP 正则 location **之前**加 `location ~ /\.(?!well-known) { deny all; }`、`location ~ ^/(vendor|composer\.(json|lock))(/|$) { deny all; }`、`location ~ \.(?:sql|log|bak|swp)$ { deny all; }`（正则 location 按出现顺序匹配，放后面会被 PHP 转发规则抢先）。CLI 脚本 `addusers.php` 等**未**额外拦截，属代码审查报告 1.4 范畴。**基线探测副作用（已处理）**：请求 `/addusers.php` 会真的执行脚本，21:48:44 生成 111 MB `www/localhost/temp.csv`，随后因 `mysql5` 主机不存在于 `addusers.php:61` 抛 `mysqli_sql_exception` 终止，未写库、未执行 `LOAD DATA`；临时 CSV 已删除，`logs/php85/php-fpm.log` 有对应 Fatal error 记录。这实证了「CLI 脚本可被 Web 任意触发」的风险，后续若允许可加一行 deny 兜底。

    **验证**：`nginx -t` 通过；`docker compose up -d --force-recreate nginx` 后 8142 仅监听 `127.0.0.1:8142`（首次 recreate 因旧端口未释放报 `bind: Only one usage of each socket address`，`docker compose up -d nginx` 重试即成功）。实测：8142 匿名 / 错误密码 401、正确凭据 200；`nginx_status` 200；`/composer.json`、`/composer.lock`、`/vendor/autoload.php`、`/.env`、`/test.sql`、`/backup.log` 全部 403，`/.well-known/...` 放行（404 仅因文件不存在），`/`、`/index.php` 200，HTTPS 站点同样生效；`http://webman.local/` → `Hello Webman!`（200），`/user/info` 仍业务 401 且 `Set-Cookie` 无 `secure`，`favicon.ico` 200，连续 12 次请求后 php85 侧到 8787 仅 1 条 ESTABLISHED（keepalive 生效）；HTTPS 200 且 `ALPN protocol: h2`；`nginx.webman.error.log` 最后错误仍停在 19:46 的历史记录，无新增 502；php85 内 WorkerMan 27 个进程未受 nginx 重建影响；`docker-compose.yml` 与 `backup/webman-autostart-2026-11-13/docker-compose.yml.bak` 仍逐字节一致（webman 依旧手动启停）。`workerman.local` 返回 502 是**预期**：php85 的 2345 端口本来就没有服务监听，本次为确认 upstream 解析生效主动探测过一次，error.log 中 upstream 显示为 `http://172.18.0.13:2345/`，非本次改动引入的回归。

    **备份**：`backup/nginx-p0-fix-2026-11-13/`（localhost.conf / workerman.conf / xhgui.conf / .env / 本文档）。**注意**：`services/nginx/conf.d/.gitignore` 为 `*` + 仅放行 `localhost.conf`，因此 `workerman.conf`、`xhgui.conf`、`.htpasswd` 都是**未跟踪文件**，改动只存在于本地工作区、不会进版本库；需要纳入版本控制请自行调整该 `.gitignore`。

11. **P1 全量修复（用户要求「把 P1 的 8 项也全都修改完全」）**：2026-11-14 完成 2.1–2.8，改动落在 `nginx.conf` / `fastcgi-php.conf` / `localhost.conf` / `openresty.conf`。

    - **2.1** `nginx.conf`：`worker_processes 1 → 2`（**不用 `auto`** —— 容器内 nginx 读到宿主机 12 核、不感知 cgroup）、`worker_connections 1024 → 8192`、`multi_accept on`、`worker_rlimit_nofile 65535`；`openresty.conf` 同步（该服务在 compose 中仍为注释状态）。实测 worker 进程数 = 2；容器 `ulimit -n = 1048576`，足以支撑 8192。
    - **2.2** `nginx.conf`：`tcp_nopush on`；`gzip on` + `gzip_vary on` / `gzip_comp_level 5` / `gzip_min_length 1024` / `gzip_proxied any` / `gzip_types`（text/plain text/css text/xml application/json application/javascript application/xml+rss application/xhtml+xml image/svg+xml）。实测 `https://localhost/index.php` 返回 `Content-Encoding: gzip` + `Vary: Accept-Encoding`。
    - **2.3** `fastcgi-php.conf`：`fastcgi_buffer_size 32k`、`fastcgi_buffers 8 16k`、`fastcgi_busy_buffers_size 64k`、`fastcgi_connect_timeout 5s`、`fastcgi_send_timeout 30s`、`fastcgi_read_timeout 3600 → 60s`、`fastcgi_keep_conn on`。实测连续 10 次 PHP 请求全 200，`nginx.error.log` 无 FastCGI/upstream 错误，PHP 8.5.12 FPM 与 `keep_conn` 兼容。
    - **2.4** `nginx.conf`：`ssl_protocols TLSv1.2 TLSv1.3`、`ssl_prefer_server_ciphers off`、`ssl_session_cache shared:SSL:10m`、`ssl_session_timeout 10m`。**两处有意偏离报告**：① `ssl_session_tickets` 取 **`on`**（报告写 `off`）—— TLS1.3 的复用只能靠 ticket，`off` 时实测 `-reconnect` 全为 `New`，等于把 2.4 的目标抵消；改 `on` 后 TLS1.2 出现 `Reused`（shared cache 生效），TLS1.3 用 `-sess_out/-sess_in` 也返回 `Reused`。② **未加 HSTS**（报告本身标注"确认全站 HTTPS 后再加"）：`webman.local` / `workerman.local` / `tp8.local` 仍是纯 HTTP，加 HSTS 会让这些站点不可用。实测 TLS1.2 / TLS1.3 握手成功、TLS1.1 被拒、`ALPN protocol: h2` 保持。
    - **2.5** `nginx.conf` + `localhost.conf`（443）+ `tp8.local`：http 块加 `open_file_cache max=4096 inactive=30s` / `valid 60s` / `min_uses 2` / `errors on`；两处加静态资源 location（`expires 7d` + `Cache-Control: public, immutable` + `try_files $uri =404`）。实测 `http://tp8.local/favicon.ico` 返回 `Expires`、`Cache-Control: max-age=604800` 与 `public, immutable`（出现两个 `Cache-Control` 是 `expires` 与 `add_header` 叠加的正常行为，值兼容）。`webman` / `workerman` 两个纯反代站点未加，静态资源仍交上游，避免绕过应用。
    - **2.6** `localhost.conf`：80 的 `localhost` server 改为纯跳转（`return 301 https://$host$request_uri`），PHP 只在 443 处理；保留两个 HTTP 例外 —— `location = /nginx_status`（exporter / 宿主采集）与 `location ^~ /.well-known/acme-challenge/`（ACME 续签）。实测 `http://localhost/` → 301、`/nginx_status` → 200。**行为变化**：`http://localhost/...` 现在会跳到 HTTPS（自签证书会弹警告）；`webman.local` / `tp8.local` 是独立 server，仍走 HTTP、不受影响。
    - **2.7** 已随 1.2 完成（`keepalive 10240 → 32`），实测连续请求后到 8787 仅 1 条 ESTABLISHED。
    - **2.8** `nginx.conf`：`client_max_body_size 100M → 128M`（与 php `post_max_size=100M` 留出 multipart 余量）、`client_body_buffer_size 256k`、`client_header_timeout 10s`、`client_body_timeout 30s`、`send_timeout 30s`。

    **不回归验证**：`nginx -t` 通过、`nginx -s reload` 成功；`https://localhost/` 200、`https://localhost/composer.json` 仍 403、`webman.local/` → `Hello Webman!`、`/user/info` 业务 401 且 `Set-Cookie` 无 `secure`、8142 匿名 401 / 认证 200、`ALPN protocol: h2` 保持、`docker-compose.yml` 未改。**过程中发现 webman 未运行**：php85 容器状态是 `Up About an hour`，11-13 启动的 WorkerMan 在容器重启后丢失（符合"手动启停、不写 compose"的约定，与本次改动无关），已按运行态方式 `docker compose exec -d php85 sh -c 'cd /www/webman && php start.php start'` 拉起，27 个进程、8787 LISTEN；拉起前的 502 记录停在 16:59:08，之后无新增。

    **备份**：`backup/nginx-p1-2026-11-14/`（nginx.conf / fastcgi-php.conf / localhost.conf / openresty.conf / 本文档）。`nginx.conf`、`fastcgi-php.conf` 是单文件 bind mount，编辑后容器内已确认读到新内容（`nginx -T` 可查），无需重建容器。

12. **P2 全量修复（用户要求「把 P2 也修改完」，2026-11-14）**：3.1–3.14、3.16 全部落地，3.15 此前已处理、3.17 为基准项。逐条改动见第 3 节末尾的「P2 落地情况」表。

    - **日志与可观测性（3.1–3.3、3.9）**：`nginx.conf` 的 `access_log /dev/null` → `access_log /var/log/nginx/access.log main buffer=32k flush=5s;`；`log_format main` 补 `rt=$request_time uct=$upstream_connect_time uht=$upstream_header_time urt=$upstream_response_time ua=$upstream_addr`；`xhgui.conf` 的 access_log 从默认 combined 改为 main；`error_log` 级别 `warn → notice`。`localhost.conf` 中 80 / 443 / tp8 三处 `access_log /dev/null` 删除，改为继承 http 级（`/nginx_status` 与静态资源仍 `access_log off`）。
    - **版本号（3.4）**：http 级 `server_tokens off`（server 块里那行已在 P1 重写 80 server 时移除）。
    - **PHP 转发（3.6–3.8）**：`fastcgi-php.conf` 把 `set $path_info` 移到 `try_files` 之前（原顺序下 `$path_info` 恒空，PATH_INFO 是死代码）并启用 `fastcgi_param PATH_INFO $path_info;`；新增 `conf.d/php-fcgi.inc` 统一 `location ~ [^/]\.php(/|$)`，`localhost` 443 与 `tp8.local` 改为 `include`；`tp8.local` 的 `if (!-f ...) rewrite` 换成 `try_files $uri $uri/ /index.php?s=$uri&$args`（ThinkPHP 8 `pathinfo_depr='/'`，`?s=` 受支持）。
    - **openresty（3.10–3.11）**：`log_format` 删除 `$request_body`（会记录密码/Token/上传内容）并修正字段粘连；`include mime.types` 改绝对路径 `/usr/local/openresty/nginx/conf/mime.types`；`access_log logs/access.log` 改 `/var/log/nginx/openresty.access.log`（与 error_log 同一挂载卷）；同步 `tcp_nopush` 与 gzip。该服务在 compose 中仍是注释状态。
    - **版本控制（3.12）**：`conf.d/.gitignore` 放行 `localhost.conf` / `workerman.conf` / `xhgui.conf` / `easyswoole.conf` / `hyperf.conf` / `php-fcgi.inc`；`.htpasswd` 保持忽略；删除从未生效的 `!certs/localhost/`。
    - **compose（3.13、3.14、3.16）**：`/ssl` 与 `/etc/nginx/conf.d` 由 `:rw` 改 `:ro`；加 `depends_on: php85 / xhgui (service_started)`；加 `healthcheck: ["CMD","nginx","-t"]`（30s/5s/3 次/10s）。`nginx.conf` 定义 `limit_req_zone` / `limit_conn_zone`，XHGui 8142 启用 `limit_req burst=20 nodelay`。

    **静态校验（Docker Desktop 未运行，运行时验证待补）**：7 个 nginx 配置大括号配平；`docker compose config` 解析通过，确认 `/ssl`、`/etc/nginx/conf.d` 为 `read_only: true`，`depends_on` / `healthcheck` 结构正确，8142 仍为 `host_ip: 127.0.0.1`；`.gitignore` 用 `git check-ignore` 逐项验证（5 个 `.conf` + `.inc` 可提交，`.htpasswd` 仍忽略）。**未执行** `nginx -t` / recreate / 运行时请求验证 —— `docker info` 报 `npipe:////./pipe/dockerDesktopLinuxEngine` 连接失败，daemon 未启动。

    **备份**：`backup/nginx-p2-2026-11-14/`（nginx.conf / fastcgi-php.conf / localhost.conf / xhgui.conf / .gitignore / openresty.conf / docker-compose.yml / 本文档）。

**至此本报告的 24 项问题全部处理完毕**：P0 6 项 + 补充 1.7（第 10 项）、P1 8 项（第 11 项）、P2 的 3.1–3.14 与 3.16（第 12 项）；3.15 此前已处理、3.17 为基准项。历次原文件备份在 `backup/nginx-upgrade-2026-11-13/`、`backup/nginx-rename-2026-11-13/`、`backup/nginx-remove-container-name-2026-11-13/`、`backup/webman-auth-fix-2026-11-13/`、`backup/nginx-p0-fix-2026-11-13/`、`backup/nginx-p1-2026-11-14/`、`backup/nginx-p2-2026-11-14/`。
