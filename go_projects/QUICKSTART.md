# Golang 快速开始 - 示例代码

## 示例 1: 基础 HTTP 服务器 (main.go)

最简单的 HTTP 服务器，使用标准库。

```bash
# 运行
docker exec -it golang go run main.go

# 访问
curl http://localhost:8081/
curl http://localhost:8081/health
```

## 示例 2: Gin Web 框架 (example_gin.go)

使用流行的 Gin 框架构建 RESTful API。

### 安装依赖

```bash
docker exec -it golang sh -c "cd /go/src/app && go get github.com/gin-gonic/gin"
```

### 运行

```bash
docker exec -it golang go run example_gin.go
```

### 测试 API

```bash
# GET 请求
curl http://localhost:8081/ping

# 带参数的 GET
curl http://localhost:8081/user/john

# JSON 响应
curl http://localhost:8081/api/info

# POST 请求
curl -X POST http://localhost:8081/login -d "username=admin&password=123456"
```

## 常用 Go 命令

```bash
# 查看版本
docker exec -it golang go version

# 初始化模块
docker exec -it golang go mod init myapp

# 下载依赖
docker exec -it golang go mod download

# 整理依赖
docker exec -it golang go mod tidy

# 构建程序
docker exec -it golang go build -o myapp main.go

# 运行程序
docker exec -it golang go run main.go

# 格式化代码
docker exec -it golang go fmt ./...

# 代码检查
docker exec -it golang go vet ./...

# 运行测试
docker exec -it golang go test ./...

# 安装第三方包
docker exec -it golang go get github.com/gin-gonic/gin
docker exec -it golang go get github.com/go-redis/redis/v8
docker exec -it golang go get gorm.io/gorm
docker exec -it golang go get gorm.io/driver/mysql
```

## 项目结构示例

```
go_projects/
├── cmd/
│   └── server/
│       └── main.go      # 主程序入口
├── internal/
│   ├── handler/         # HTTP 处理器
│   ├── service/         # 业务逻辑
│   └── model/           # 数据模型
├── pkg/
│   └── utils/           # 工具函数
├── api/                 # API 定义
├── configs/             # 配置文件
├── go.mod              # 模块定义
├── go.sum              # 依赖锁定
└── README.md           # 项目说明
```

## 连接数据库示例

### MySQL

```go
import (
    "gorm.io/driver/mysql"
    "gorm.io/gorm"
)

dsn := "root:123456@tcp(mysql-8:3307)/dbname?charset=utf8mb4&parseTime=True&loc=Local"
db, err := gorm.Open(mysql.Open(dsn), &gorm.Config{})
```

### Redis

```go
import (
    "github.com/go-redis/redis/v8"
    "context"
)

rdb := redis.NewClient(&redis.Options{
    Addr:     "redis:6379",
    Password: "",
    DB:       0,
})

val, err := rdb.Get(context.Background(), "key").Result()
```

## 热门 Go 框架和库

- **Web 框架**: 
  - [Gin](https://github.com/gin-gonic/gin) - 高性能 HTTP 框架
  - [Echo](https://github.com/labstack/echo) - 轻量级 Web 框架
  - [Fiber](https://github.com/gofiber/fiber) - 基于 Fasthttp 的框架

- **数据库 ORM**:
  - [GORM](https://github.com/go-gorm/gorm) - 功能丰富的 ORM
  - [Ent](https://github.com/facebook/ent) - Facebook 的实体框架

- **消息队列**:
  - [Shopify/sarama](https://github.com/Shopify/sarama) - Kafka 客户端
  - [RabbitMQ/amqp091-go](https://github.com/rabbitmq/amqp091-go) - RabbitMQ 客户端

- **工具库**:
  - [Viper](https://github.com/spf13/viper) - 配置管理
  - [Zap](https://github.com/uber-go/zap) - 高性能日志
  - [Testify](https://github.com/stretchr/testify) - 测试工具

## 调试技巧

### 使用 Delve 调试器

```bash
# 安装 Delve
docker exec -it golang go install github.com/go-delve/delve/cmd/dlv@latest

# 以调试模式运行
docker exec -it golang dlv debug --headless --listen=:2345 --api-version=2

# 在 VS Code 中配置 launch.json 连接调试器
```

### VS Code 调试配置

```json
{
    "version": "0.2.0",
    "configurations": [
        {
            "name": "Docker Attach",
            "type": "go",
            "request": "attach",
            "mode": "remote",
            "remotePath": "/go/src/app",
            "port": 2345,
            "host": "127.0.0.1"
        }
    ]
}
```

## 性能优化

1. **启用 Go Modules 缓存**
   ```bash
   docker exec -it golang go env -w GOMODCACHE=/go/pkg/mod
   ```

2. **使用构建缓存**
   ```bash
   docker exec -it golang go build -cache=$(pwd)/.cache main.go
   ```

3. **减少镜像大小** - 使用多阶段构建（参考 Dockerfile）

## 部署建议

1. **开发环境**: 直接使用 `go run` 热重载
2. **测试环境**: 使用 `go build` 编译后运行
3. **生产环境**: 使用 Docker 多阶段构建最小镜像

## 下一步学习

1. 学习 Go 语言基础语法
2. 理解 HTTP 和 RESTful API 设计
3. 掌握至少一个 Web 框架
4. 学习数据库操作（GORM）
5. 了解微服务架构
6. 实践单元测试和集成测试
