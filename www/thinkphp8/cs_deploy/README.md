# 智能客服 Agent（ThinkPHP 8 + Docker NMP）

## 访问地址
- 用户聊天端：<http://127.0.0.1:8099/cs/>
- 管理后台：<http://127.0.0.1:8099/cs/admin.html>
- 管理接口令牌：`cs-admin-token`（前端已内置）

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
- 会话 id 保存在浏览器 localStorage，历史入口据此拉取；上下文若过期会从 MySQL 消息表自动重建。

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

## 管理令牌修改
`public/cs/config.js` 的 `adminToken` 与前端请求头一致即可（仅简单鉴权，生产建议接入正式登录）。

## 服务运维
```bash
docker ps --filter name=cs-nginx      # 查看 Web 容器
docker logs cs-nginx                  # 查看访问日志错误
docker exec php85 sh -c 'chmod -R 777 /www/thinkphp8/runtime/log/audit/$(date +%Y%m)'  # 日志权限
```
