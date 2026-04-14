# Gin + MySQL9 示例说明

这是当前 `go_projects` 目录中推荐使用的版本，已经和仓库里的 Docker 环境对齐。

## 技术栈

- Go `1.26`
- Gin `1.12.0`
- MySQL Driver `github.com/go-sql-driver/mysql`
- MySQL `9.x`

## 项目结构

```text
go_projects/
├── app_main.go
├── app_server.go
├── app_database.go
├── app_routes.go
├── app_test.go
├── integration_test.go
├── go.mod
├── go.sum
└── Dockerfile
```

## API

### 健康检查

```http
GET /health
```

### 数据库连通性

```http
GET /api/db/test
```

### 用户 CRUD

```http
GET    /api/users
GET    /api/users/:id
POST   /api/users
PUT    /api/users/:id
DELETE /api/users/:id
```

## 启动方式

### 方式 1：使用仓库现有容器

```bash
docker-compose up -d mysql9 golang
docker exec -it golang sh -lc "cd /go/src/app && /usr/local/go/bin/go run ."
```

此时服务可通过 `http://localhost:8081` 访问。

### 方式 2：本机直接运行

```bash
cd go_projects
go run .
```

此时默认端口为 `8080`。

## 环境变量示例

```bash
MYSQL9_HOST=mysql-9
MYSQL9_PORT=3306
MYSQL9_USER=root
MYSQL9_PASSWORD=123456
MYSQL9_DATABASE=test_db

MYSQL9_MAX_OPEN_CONNS=25
MYSQL9_MAX_IDLE_CONNS=10
MYSQL9_CONN_MAX_LIFETIME=5m
MYSQL9_CONN_MAX_IDLE_TIME=1m

GIN_MODE=debug
PORT=8080
```

## 示例请求

创建用户：

```bash
curl -X POST http://localhost:8081/api/users \
  -H "Content-Type: application/json" \
  -d "{\"username\":\"john\",\"email\":\"john@example.com\",\"age\":25}"
```

查询列表：

```bash
curl http://localhost:8081/api/users
```

更新用户：

```bash
curl -X PUT http://localhost:8081/api/users/1 \
  -H "Content-Type: application/json" \
  -d "{\"username\":\"john-updated\",\"email\":\"john-updated@example.com\",\"age\":26}"
```

删除用户：

```bash
curl -X DELETE http://localhost:8081/api/users/1
```

## 测试策略

`app_test.go`

- 使用内存 fake store
- 不依赖真实 MySQL
- 验证路由、校验、返回码和基本行为

`integration_test.go`

- 使用真实 MySQL9
- 通过 `MYSQL9_INTEGRATION=1` 显式开启
- 自动确保测试数据库存在

运行方式：

```bash
docker exec golang sh -lc "cd /go/src/app && /usr/local/go/bin/go test ./..."
docker exec golang sh -lc "cd /go/src/app && MYSQL9_INTEGRATION=1 /usr/local/go/bin/go test -run TestMySQLStoreIntegration ./..."
```

## 说明

旧版示例文件已经保留为 `*.disabled`，方便后续查阅，但不会再参与构建。
