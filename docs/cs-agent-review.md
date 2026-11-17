# OCR 代码审查报告：智能客服 Agent 模块

- 审查日期：2026-11-17
- 审查方式：`ocr` 委托模式（原生 `ocr review` 因未配置 LLM endpoint 无法运行，改用 `ocr delegate preview` + `ocr delegate rule` 取得文件清单与规则，由宿主 agent 分析）
- 审查范围：工作区 246 个变更文件中排除 `.history/` 快照、`.workbuddy-ai/` 记忆、子模块及不支持扩展名等 228 个噪音文件，实际审查 18 个文件（新增的 ThinkPHP 8 智能客服 Agent 模块）
- 业务背景：`http://127.0.0.1:8099/cs/`，PHP 8.5 + MySQL 9 + Redis，含用户对话、意图识别、FAQ、转人工工单、满意度、管理后台

---

## 0. 结论摘要

共 **11 项**问题：P0 阻断 1 项、P1 高危 2 项、P2 改进 6 项、P3 轻微 2 项。

| 级别 | 数量 | 核心问题 |
|---|---|---|
| P0 | 1 | 管理接口鉴权形同虚设（令牌硬编码 + 前端明文 + CORS `*`） |
| P1 | 2 | 用户接口无会话归属校验；无任何限流，LLM 模式可被刷爆 |
| P2 | 6 | Redis 序列化与自增冲突、FAQ 缓存击穿、转人工误判、token 走 GET、空会话膨胀、LLM 降级统计失真 |
| P3 | 2 | 死配置 `Config::admin()`、错误响应恒 HTTP 200 |

**最优先 3 件事**：

1. 修掉公开的管理令牌。
2. 给用户端接口加会话归属校验。
3. 给 `chat/send` 加限流。

---

## 1. P0：阻断级问题

### 1.1 管理后台鉴权完全失效

`www/thinkphp8/app/cs/controller/Admin.php:16-20`

```php
    private function auth(Request $request): bool
    {
        $token = $request->header('X-CS-Token') ?: $request->get('token', '');
        return $token === 'cs-admin-token';
    }
```

`www/thinkphp8/public/cs/config.js:2-7`

```javascript
window.CS_CONFIG = {
    apiBase: '/cs',
    adminToken: 'cs-admin-token'
};
```

令牌 `cs-admin-token` 同时硬编码在服务端和**前端公开 JS** 里（`cs_deploy/README.md:6` 也直接写明），且 `Base.php:17` 对所有响应返回 `Access-Control-Allow-Origin: *`。任何人打开 `http://host:8099/cs/config.js` 拿到令牌后，即可跨域调用全部管理接口——读取所有用户对话记录、评价、工单，甚至回复/关闭工单（`Admin.php` 全部接口仅靠这个 guard）。

**修复**：令牌改为服务端 `.env` 配置且不可从前端静态文件获取；改为服务端会话登录（配合 `Config::admin()` 已有的 user/pass）；移除 `Access-Control-Allow-Origin: *`，改为白名单；删除 `?token=` 传参方式（见 3.4）。

---

## 2. P1：高危问题

### 2.1 用户端接口无会话归属校验，可越权读取任意对话

`Chat.php:43-48` 的 `history`、`ChatService.php:186-192` 的 `messages`、`sessionsByIds` 都只按 `session_id` 查询，没有验证调用者是否拥有该会话。`user_id` 由客户端自报（`Chat.php:20`），不参与任何鉴权。配合 CORS `*`，任意站点可跨域读取。

会话 ID 为 `S + YmdHis + 8 位 hex(random_bytes(4))`（`ChatService.php:222`），仅 32 位随机且时间可预测，一旦通过日志/Referer/分享泄露，对话内容完全暴露。

**修复**：服务端签发会话绑定用户身份（登录态或签名 token），所有用户接口校验 `session_id` 归属；移除 CORS 通配。

### 2.2 所有写接口无速率限制，LLM 模式可被刷爆账单

`chat/send`、`chat/new` 无任何限流。`Kv.php:10` 注释提到"限流"但实际没有实现。当 `CS_LLM_MODE=openai`（`cs_deploy/README.md:39-48`）时，任何人都可循环调用 `chat/send` 触发大量付费 API 请求，造成费用和资源耗尽。

**修复**：在 `Chat::send`/`Chat::new` 加基于 IP/用户的令牌桶限流（可复用 `Kv::incr`），并给 LLM 调用设每日配额上限。

---

## 3. P2：改进项

### 3.1 Redis 序列化与自增冲突，`incr()` 恒返回 0

`www/thinkphp8/app/cs/service/Kv.php:28`

```php
            $r->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
```

`www/thinkphp8/app/cs/service/Kv.php:60-67`

```php
    public static function incr(string $k, int $by = 1): int
    {
        try {
            $r = self::redis();
            $r->incrBy(self::key($k), $by);
            return (int) $r->get(self::key($k));
        } catch (RedisException $e) { return 0; }
    }
```

`incrBy` 写入的是 Redis 原始数字字符串（如 `"3"`），但读取走 `get()` 会被 phpredis 按 `SERIALIZER_PHP` 反序列化，`unserialize("3")` 返回 `false`，因此 `(int)false === 0`。当前调用方（`ChatService.php:92-94`）都忽略返回值，统计本身在 Redis 中仍正确累加，所以功能未受影响；但 `incr()` 返回值错误，且若同一 key 与 `set()` 混用会破坏序列化格式。

**修复**：统计计数改用独立的不启用序列化的连接，或 `incr()` 返回累加值 / 用 `get` 前临时关闭序列化。

### 3.2 FAQ 每次命中都删除全量缓存，缓存形同虚设

`www/thinkphp8/app/cs/service/FaqService.php:87-89`

```php
        // 命中次数 +1
        Store::execute("UPDATE cs_faq SET hit_count=hit_count+1 WHERE id=?", [(int)$best['id']]);
        Kv::del(self::CACHE_KEY);
```

每次命中都删 `faq:all`，下一次 `all()` 必然回源查库。客服对话属于高频命中场景，等于每次问答都打一次 MySQL。

**修复**：`hit_count` 不影响检索，无需删缓存；改为定时异步刷新计数，或仅在 FAQ 内容变更时清缓存。

### 3.3 转人工判定与注释不符，长会话易误转

`www/thinkphp8/app/cs/service/ChatService.php:121-124`

```php
        // 连续多轮低置信度且仍为 other
        if ($intent['intent'] === 'other' && count($history) >= 4) {
            return '多轮对话未能识别有效意图';
        }
```

注释要求"连续多轮低置信度"，实现只判断 `history >= 4` 且本轮为 `other`。一个正常进行了 4 轮以上的会话中，用户问一句无法识别的问题（如"你们地址在哪"）会立即被转人工。

**修复**：统计连续 `other` 的次数并检查 `confidence` 阈值，而非用历史总条数。

### 3.4 管理令牌支持 GET 参数传递，泄露到日志/Referer

`Admin.php:18` 中 `$request->get('token', '')` 允许 URL 携带令牌，会被写入 Nginx access log、浏览器历史和外链 Referer。

**修复**：仅接受 `X-CS-Token` 请求头，删除 GET 兜底。

### 3.5 页面每次加载都新建会话，空会话持续膨胀

`www/thinkphp8/public/cs/index.html:309-310`

```javascript
bindStars();
newSession();
```

脚本末尾无条件调用 `newSession()`，刷新页面即写库新建会话，不检查 `localStorage` 中已有的会话。`localStorage` 只保留最近 50 个 ID，数据库里却永久累积大量空会话。

**修复**：启动时优先恢复 `getIds()[0]`，仅当无历史会话时才 `newSession()`。

### 3.6 LLM 调用失败降级后 `source` 仍标记为 `llm`

`www/thinkphp8/app/cs/service/LlmService.php:37-43`

```php
            // 接口失败则降级到演示模式
        }
        return [
            'content' => self::demoReply($question, $context),
            'source'  => 'llm',
            'model'   => 'demo-llm',
        ];
```

降级返回的是规则回复，但 `source='llm'`，前端显示"AI生成"（`index.html:235`），`AdminService::sourceDistribution()` 也把它计入 LLM 来源，运营统计失真。

**修复**：降级时返回 `source='rule'` 并加 `degraded=true` 标志，前端和统计分别呈现。

---

## 4. P3：轻微问题

### 4.1 `Config::admin()` 是死配置

`Config.php:60-66` 定义了管理员 user/pass，但 `Admin::auth()` 用的是硬编码 token，该方法从未被调用，说明设计上的登录鉴权没接上。

### 4.2 所有错误响应恒为 HTTP 200

`Base.php:30` 的 `fail()` 把业务码放进 body、HTTP 状态固定 200，导致未授权（`code=401`）也返回 200，网关和监控无法识别异常。前端靠 `r.code` 判断所以功能正常，但语义不规范。

---

## 5. 未发现问题的维度

- **SQL 注入**：动态拼接处（`AdminService.php` 的 `WHERE`/`LIMIT`/`OFFSET`、`sessionsByIds` 的 `IN`）均使用占位符或已收敛为整数，未发现可注入点。
- **XSS**：前端 `escapeHtml`（`index.html:138`）/`esc`（`admin.html:272`）覆盖了主要用户可控字段（`content`、`first_question`、`user_name`、`question`），未发现可利用的 DOM XSS。
- **数据库脚本**：`cs_agent.sql` 结构、索引、utf8mb4 与服务端字段长度一致。
