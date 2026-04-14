# Quick Start

## 1. 启动依赖

在仓库根目录执行：

```bash
docker-compose up -d mysql9 golang
```

确认容器已启动：

```bash
docker ps
```

你应该能看到至少这两个容器：

- `mysql-9`
- `golang`

## 2. 运行 Gin + MySQL9 应用

在 `golang` 容器内运行：

```bash
docker exec -it golang sh -lc "cd /go/src/app && /usr/local/go/bin/go run ."
```

如果本机已经安装 Go，也可以直接运行：

```bash
cd go_projects
go run .
```

## 3. 验证接口

健康检查：

```bash
curl http://localhost:8081/health
```

数据库检查：

```bash
curl http://localhost:8081/api/db/test
```

创建用户：

```bash
curl -X POST http://localhost:8081/api/users \
  -H "Content-Type: application/json" \
  -d "{\"username\":\"tom\",\"email\":\"tom@example.com\",\"age\":26}"
```

查询用户列表：

```bash
curl http://localhost:8081/api/users
```

## 4. 运行测试

单元测试：

```bash
docker exec golang sh -lc "cd /go/src/app && /usr/local/go/bin/go test ./..."
```

集成测试：

```bash
docker exec golang sh -lc "cd /go/src/app && MYSQL9_INTEGRATION=1 /usr/local/go/bin/go test -run TestMySQLStoreIntegration ./..."
```

## 5. 常见问题

`/api/db/test` 失败：

- 先确认 `mysql-9` 容器正常运行
- 再确认 `.env` 中 `MYSQL9_*` 变量是否符合当前运行方式

容器内访问 MySQL9：

- 默认使用 `mysql-9:3306`

宿主机访问 MySQL9：

- 默认使用 `localhost:3308`
