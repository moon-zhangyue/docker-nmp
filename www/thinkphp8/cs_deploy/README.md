# 智能客服 Agent（ThinkPHP 8 + Docker NMP）

## 访问地址
- 用户聊天端：<http://127.0.0.1:8099/cs/>
- 管理后台：<http://127.0.0.1:8099/cs/admin.html>
- 管理后台：使用管理员账号密码登录，凭据仅保存在服务端签名的 HttpOnly Cookie 中，前端不持有任何令牌

## 技术栈
- 服务端：ThinkPHP 8（复用现有项目框架，独立命名空间 `app\cs\`，不污染现有业务）
- 存储：MySQL 9.3（容器 `mysql-9`，库 `cs_agent`）+ Redis 7.2（容器 `redis`）
- Web：Nginx（专用容器 `cs-nginx`，宿主端口 8099）+ PHP 8.5-FPM（容器 `php85`）
- 大模型：适配层支持演示模式 / 任意 OpenAI 兼容接口

## 已实现功能
- 多轮对话（Redis 维护上下文，MySQL 持久化消息）
- FAQ 知识库检索（关键词 + 二元组相似度，Redis 缓存）
- 意图识别（退款 / 查询订单 / 技术支持 / 其他，关键词加权打分）
- 无法解决自动转人工（用户主动 / 负面表达 / 多轮未识别 → 生成工单）
- 对话历史持久化、满意度评价（1-5 星）
- 管理后台：数据总览、意图分布、满意度分布、近7天趋势、对话记录、工单处理、FAQ 列表

## 目录结构
```
app/cs/
  controller/  Base.php / Chat.php / Admin.php     API 控制器
  service/     Config / Store / Kv / IntentService / FaqService
               LlmService / ChatService / AdminService
route/cs.php                                        路由（前缀 cs/）
public/cs/       index.html / admin.html / config.js 前端页面
cs_deploy/sql/cs_agent.sql                           建库建表 + FAQ 种子数据
```

## 评价与历史记录逻辑
- 提交评价仅记录打分，不再关闭会话、不清除对话上下文；评价后聊天记录保留，可继续聊天。
- 同一会话重复评价为更新操作，不会重复计入满意度统计。
- 顶部「历史」按钮可翻看本机保存过的历史会话，点击任一会话可查看完整记录并继续对话。
- 会话 id 与签名访问令牌保存在浏览器 localStorage（key `cs_sessions`），历史入口按 `session_id + access_token` 拉取，服务端逐项校验归属；上下文若过期会从 MySQL 消息表自动重建。令牌过期/不匹配时接口返回 403，前端自动新建会话。

## 切换真实大模型
编辑 `thinkphp8/.env` 的 `[CS]` 分节：
```
CS_LLM_MODE = openai
CS_LLM_BASE_URL = https://api.deepseek.com/v1
CS_LLM_API_KEY = sk-xxxxxx
CS_LLM_MODEL = deepseek-chat
```
支持任意 OpenAI 兼容接口（DeepSeek / 通义千问 / OpenAI / 本地 vLLM/Ollama 兼容层）。
接口不可用时自动降级为演示模式，保证服务不中断。改完无需重启 PHP-FPM。

## 数据重置
```bash
docker exec -i mysql-9 mysql -uroot -p123456 --default-character-set=utf8mb4 \
  < cs_deploy/sql/cs_agent.sql
docker exec redis redis-cli DEL cs:faq:all
```
注意：必须带 `--default-character-set=utf8mb4`，否则中文乱码。

## 管理账号与安全配置
在 `thinkphp8/.env` 的 `[CS]` 分节配置（缺省值仅用于本地演示，部署到公网前务必修改）：

| 配置项 | 默认值 | 说明 |
|---|---|---|
| `CS_ADMIN_USER` | `admin` | 管理后台账号 |
| `CS_ADMIN_PASS` | `admin123` | 管理后台密码，支持 `password_hash` 生成的哈希 |
| `CS_ADMIN_SECRET` | 由密码派生 | 管理会话签名密钥，留空则改密码后旧会话自动失效 |
| `CS_SESSION_SECRET` | 由 `APP_KEY` 派生 | 用户会话令牌签名密钥 |
| `CS_LLM_MODE` | `demo` | `demo`=规则回复；`openai`=调用真实模型 |

安全机制：管理端为账号密码登录 + 服务端签名 HttpOnly Cookie；用户端会话绑定签名访问令牌；所有接口同源访问，未配置通配 CORS。

## 限流
按客户端 IP 做固定窗口限流：`chat/new` 20 次/分钟、`chat/send` 30 次/分钟、`chat/rate` 10 次/分钟、`chat/sessions` 30 次/分钟、管理登录 10 次/分钟。LLM 调用上限 500 次/天，超出后自动降级为规则回复并标记 `source=rule`。

## 服务运维
```bash
docker ps --filter name=cs-nginx      # 查看 Web 容器
docker logs cs-nginx                  # 查看访问日志错误
docker exec php85 sh -c 'chmod -R 777 /www/thinkphp8/runtime/log/audit/$(date +%Y%m)'  # 日志权限
```
