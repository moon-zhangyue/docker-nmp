# 用户模块API文档

## 概述

本文档描述了用户模块的API接口，包括用户注册、登录、信息查询和退出等功能。

## 数据库设计

### 用户表 (users)

| 字段名 | 类型 | 说明 |
| --- | --- | --- |
| id | int(11) unsigned | 用户ID，自增主键 |
| username | varchar(50) | 用户名，唯一 |
| email | varchar(100) | 邮箱，唯一 |
| password | varchar(255) | 密码，加密存储 |
| phone | varchar(20) | 手机号 |
| avatar | varchar(255) | 头像URL |
| status | tinyint(1) | 状态：1-正常，0-禁用 |
| last_login_at | datetime | 最后登录时间 |
| last_login_ip | varchar(50) | 最后登录IP |
| remember_token | varchar(100) | 记住我令牌 |
| created_at | datetime | 创建时间 |
| updated_at | datetime | 更新时间 |

## Redis消息队列

### 用户注册通知队列

- 队列名称：`user-register-notify`
- 用途：处理用户注册后的通知，如发送欢迎邮件等
- 数据格式：
  ```json
  {
    "user_id": 1,
    "username": "用户名",
    "email": "用户邮箱",
    "time": "注册时间"
  }
  ```

## API接口

### 1. 用户注册

- **URL**: `/user/register`
- **方法**: POST
- **请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| username | string | 是 | 用户名 |
| password | string | 是 | 密码 |
| email | string | 是 | 邮箱 |
| phone | string | 否 | 手机号 |

- **响应示例**:

```json
{
  "code": 200,
  "msg": "注册成功",
  "data": {
    "user_id": 1
  }
}
```

### 2. 用户登录

- **URL**: `/user/login`
- **方法**: POST
- **请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| username | string | 是 | 用户名或邮箱 |
| password | string | 是 | 密码 |

- **响应示例**:

```json
{
  "code": 200,
  "msg": "登录成功",
  "data": {
    "user_id": 1,
    "username": "用户名",
    "email": "用户邮箱"
  }
}
```

### 3. 获取当前用户信息

- **URL**: `/user/info`
- **方法**: GET
- **请求参数**: 无（使用会话认证）

- **响应示例**:

```json
{
  "code": 200,
  "msg": "获取成功",
  "data": {
    "user_id": 1,
    "username": "用户名",
    "email": "用户邮箱",
    "phone": "手机号",
    "avatar": "头像URL",
    "status": 1,
    "created_at": "2023-01-01 00:00:00",
    "last_login_at": "2023-01-01 00:00:00"
  }
}
```

### 4. 根据ID获取用户信息

- **URL**: `/user/get-by-id`
- **方法**: GET
- **请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| id | int | 是 | 用户ID |

- **响应示例**:

```json
{
  "code": 200,
  "msg": "获取成功",
  "data": {
    "user_id": 1,
    "username": "用户名",
    "email": "用户邮箱",
    "phone": "手机号",
    "avatar": "头像URL",
    "status": 1,
    "created_at": "2023-01-01 00:00:00",
    "last_login_at": "2023-01-01 00:00:00"
  }
}
```

### 5. 用户退出登录

- **URL**: `/user/logout`
- **方法**: POST
- **请求参数**: 无（使用会话认证）

- **响应示例**:

```json
{
  "code": 200,
  "msg": "退出成功"
}
```

## 错误码说明

| 错误码 | 说明 |
| --- | --- |
| 200 | 成功 |
| 400 | 请求参数错误 |
| 401 | 未登录或登录已过期 |
| 404 | 资源不存在 |
| 500 | 服务器内部错误 |

## 日志记录

用户模块在以下关键操作中会记录日志：

1. 用户注册成功
2. 用户注册失败
3. 用户登录成功
4. 用户登录失败
5. 用户退出登录

日志存储在 `runtime/logs/webman.log` 文件中。

## 路由配置

在 `config/route.php` 中添加以下路由配置：

```php
// 用户模块路由
Route::post('/user/register', [app\controller\UserController::class, 'register']);
Route::post('/user/login', [app\controller\UserController::class, 'login']);
Route::get('/user/info', [app\controller\UserController::class, 'info']);
Route::get('/user/get-by-id', [app\controller\UserController::class, 'getUserById']);
Route::post('/user/logout', [app\controller\UserController::class, 'logout']);
```

## 安装说明

1. 导入数据库迁移文件 `database/migrations/create_users_table.sql`
2. 确保Redis服务已启动并配置正确
3. 重启Webman服务

## 使用示例

### 注册用户

```bash
curl -X POST http://localhost:8787/user/register \
  -H "Content-Type: application/json" \
  -d '{"username":"test_user","password":"123456","email":"test@example.com"}'
```

### 用户登录

```bash
curl -X POST http://localhost:8787/user/login \
  -H "Content-Type: application/json" \
  -d '{"username":"test_user","password":"123456"}'
```

### 获取用户信息

```bash
curl -X GET http://localhost:8787/user/info \
  -H "Cookie: PHPSESSID=your_session_id"
```