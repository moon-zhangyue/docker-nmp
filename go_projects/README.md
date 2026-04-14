# Go Projects

这个目录现在提供一套可运行的 `Gin + MySQL9` 示例，包含：

- 基于 `gin` 的 REST API
- 基于 `database/sql` 的 MySQL9 连接
- `users` 表的基础 CRUD
- 不依赖真实数据库的接口测试
- 可选的 MySQL9 集成测试

主要代码文件：

- `app_main.go`：程序入口
- `app_server.go`：Gin 初始化与启动
- `app_database.go`：MySQL 连接与连接池配置
- `app_routes.go`：路由、处理器、存储接口与 MySQL 实现
- `app_test.go`：接口级单元测试
- `integration_test.go`：真实 MySQL9 集成测试

为了避免旧示例代码影响构建，历史文件已经保留为 `*.disabled`。

## 快速开始

1. 启动容器

```bash
docker-compose up -d mysql9 golang
```

2. 进入项目目录

```bash
cd go_projects
```

3. 运行应用

如果你本机安装了 Go：

```bash
go run .
```

如果你希望直接在 `golang` 容器内运行：

```bash
docker exec -it golang sh -lc "cd /go/src/app && /usr/local/go/bin/go run ."
```

4. 访问接口

- `GET http://localhost:8081/health`
- `GET http://localhost:8081/api/db/test`

如果你在本机直接运行，端口默认是 `8080`。

## 测试

运行全部测试：

```bash
docker exec golang sh -lc "cd /go/src/app && /usr/local/go/bin/go test ./..."
```

运行真实 MySQL9 集成测试：

```bash
docker exec golang sh -lc "cd /go/src/app && MYSQL9_INTEGRATION=1 /usr/local/go/bin/go test -run TestMySQLStoreIntegration ./..."
```

## 环境变量

- `MYSQL9_HOST`
- `MYSQL9_PORT`
- `MYSQL9_USER`
- `MYSQL9_PASSWORD`
- `MYSQL9_DATABASE`
- `MYSQL9_MAX_OPEN_CONNS`
- `MYSQL9_MAX_IDLE_CONNS`
- `MYSQL9_CONN_MAX_LIFETIME`
- `MYSQL9_CONN_MAX_IDLE_TIME`
- `GIN_MODE`
- `PORT`

默认情况下：

- 在容器网络中连接 MySQL9，使用 `mysql-9:3306`
- 在宿主机集成测试场景，可使用 `localhost:3308`

## 常用命令

格式化：

```bash
docker exec golang sh -lc "cd /go/src/app && /usr/local/go/bin/gofmt -w app_database.go app_routes.go app_server.go app_main.go app_test.go integration_test.go"
```

构建：

```bash
docker exec golang sh -lc "cd /go/src/app && /usr/local/go/bin/go build -o app ."
```
