# Gin + MySQL9 示例项目

这是一个使用 Gin 框架连接 MySQL9 数据库的 Go 语言示例项目，包含完整的 RESTful API 和测试用例。

## 功能特性

- ✅ Gin Web 框架集成
- ✅ MySQL9 数据库连接池
- ✅ RESTful API 设计
- ✅ CORS 跨域支持
- ✅ 完整的 CRUD 操作
- ✅ 单元测试覆盖
- ✅ 环境变量配置
- ✅ 日志中间件

## API 接口

### 健康检查
- `GET /health` - 健康检查接口

### 数据库测试
- `GET /api/db/test` - 测试数据库连接状态

### 用户管理
- `GET /api/users` - 获取所有用户列表
- `GET /api/users/:id` - 根据 ID 获取单个用户
- `POST /api/users` - 创建新用户
- `PUT /api/users/:id` - 更新用户信息
- `DELETE /api/users/:id` - 删除用户

## 快速开始

### 1. 确保 MySQL9 服务运行

```bash
# 启动 MySQL9 服务
docker-compose up -d mysql9
```

### 2. 设置环境变量（可选）

在项目根目录创建 `.env` 文件或设置以下环境变量：

```bash
MYSQL9_HOST=mysql-9        # MySQL9 容器名称或主机地址
MYSQL9_PORT=3306           # MySQL9 端口
MYSQL9_USER=root           # 数据库用户名
MYSQL9_PASSWORD=123456     # 数据库密码
MYSQL9_DATABASE=test_db    # 数据库名称
GIN_MODE=debug             # Gin 模式 (debug/release/test)
PORT=8080                  # 应用端口
```

### 3. 安装依赖

```bash
cd go_projects
go mod download
```

### 4. 运行应用

```bash
# 直接运行
go run .

# 或者构建后运行
go build -o app
./app
```

### 5. 在 Docker 中运行

```bash
# 从项目根目录
docker-compose up -d golang
```

## 测试

### 运行所有测试

```bash
go test -v
```

### 运行特定测试

```bash
# 测试健康检查
go test -v -run TestHealthCheck

# 测试数据库连接
go test -v -run TestDatabaseConnection

# 测试 CRUD 操作
go test -v -run TestCRUDOperations
```

### 查看测试覆盖率

```bash
go test -cover
```

## 项目结构

```
go_projects/
├── main.go          # 应用入口
├── database.go      # 数据库连接和管理
├── routes.go        # API 路由和处理器
├── gin_app.go       # Gin 应用配置
├── main_test.go     # 测试用例
├── go.mod           # Go 模块依赖
└── go.sum           # 依赖校验文件
```

## 代码说明

### database.go
- `InitDB()` - 初始化数据库连接池
- `CloseDB()` - 关闭数据库连接
- `CreateTestTable()` - 创建测试表

### routes.go
- `SetupRoutes()` - 设置所有 API 路由
- `HealthCheck()` - 健康检查处理器
- `GetUsers()` - 获取用户列表
- `CreateUser()` - 创建用户
- `UpdateUser()` - 更新用户
- `DeleteUser()` - 删除用户

### gin_app.go
- `NewGinApp()` - 创建并配置 Gin 应用
- `StartServer()` - 启动服务器
- `CORSMiddleware()` - CORS 跨域中间件
- `LoggerMiddleware()` - 日志中间件

## 示例请求

### 创建用户

```bash
curl -X POST http://localhost:8080/api/users \
  -H "Content-Type: application/json" \
  -d '{
    "username": "john",
    "email": "john@example.com",
    "age": 25
  }'
```

### 获取所有用户

```bash
curl http://localhost:8080/api/users
```

### 获取单个用户

```bash
curl http://localhost:8080/api/users/1
```

### 更新用户

```bash
curl -X PUT http://localhost:8080/api/users/1 \
  -H "Content-Type: application/json" \
  -d '{
    "username": "john_updated",
    "email": "john_new@example.com",
    "age": 26
  }'
```

### 删除用户

```bash
curl -X DELETE http://localhost:8080/api/users/1
```

## 数据库表结构

```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL,
    age INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 常见问题

### 1. 无法连接到 MySQL9

确保 MySQL9 容器正在运行：
```bash
docker-compose ps mysql9
```

检查 MySQL9 日志：
```bash
docker-compose logs mysql9
```

### 2. 测试失败

确保设置了正确的环境变量，并且 MySQL9 服务可访问。

### 3. 端口冲突

如果 8080 端口被占用，可以设置 `PORT` 环境变量：
```bash
export PORT=8081
go run .
```

## 开发建议

1. **错误处理**: 所有数据库操作都包含错误处理
2. **输入验证**: 使用 Gin 的绑定验证功能
3. **SQL 注入防护**: 使用参数化查询
4. **连接池管理**: 合理配置连接池参数
5. **日志记录**: 使用中间件记录请求日志

## 许可证

MIT License
