# Golang 开发环境使用指南

## 快速开始

### 1. 启动 Golang 容器

```bash
# 启动 golang 容器
docker-compose up -d golang
```

### 2. 进入容器开发

```bash
# 进入容器
docker exec -it golang sh

# 在容器中运行 Go 程序
cd /go/src/app
go run main.go
```

### 3. 访问应用

- 应用地址：http://localhost:8081
- 健康检查：http://localhost:8081/health

## 目录结构

```
go_projects/
├── main.go          # 示例代码
├── go.mod          # Go 模块定义
├── Dockerfile      # Docker 构建文件（可选）
└── .gitignore      # Git 忽略文件
```

## 常用命令

### 在容器中执行

```bash
# 查看 Go 版本
docker exec -it golang go version

# 运行程序
docker exec -it golang go run main.go

# 构建程序
docker exec -it golang go build -o app main.go

# 安装依赖
docker exec -it golang go mod tidy
```

### 在本地开发（推荐）

如果你在本地安装了 Go，也可以在本地开发，然后在容器中运行：

```bash
# 本地运行
cd go_projects
go run main.go

# 本地构建
go build -o app.exe main.go
```

## 配置说明

### 环境变量（.env）

- `GO_VERSION=1.22` - Go 语言版本
- `GO_HOST_PORT=8081` - 主机映射端口
- `GO_PROJECT_DIR=./go_projects` - 项目目录

### Docker Compose 配置

```yaml
golang:
  image: golang:1.22-alpine
  container_name: golang
  ports:
    - "8081:8080"           # 端口映射
  volumes:
    - ./go_projects:/go/src/app/:rw  # 代码挂载
    - ${DATA_DIR}/go_cache:/go/pkg/mod/cache  # 模块缓存
  environment:
    - GOPROXY=https://goproxy.cn,direct  # 国内代理
```

## 创建新项目

### 1. 在容器中初始化

```bash
docker exec -it golang sh
cd /go/src/app/myapp
go mod init myapp
```

### 2. 或在本地初始化

```bash
cd go_projects
mkdir myapp
cd myapp
go mod init myapp
```

## 使用 Dockerfile 部署

如果需要构建独立镜像部署：

```bash
# 构建镜像
docker build -t my-go-app .

# 运行容器
docker run -d -p 8080:8080 my-go-app
```

## 常见问题

### Q: 如何安装第三方包？

```bash
docker exec -it golang go get github.com/gin-gonic/gin
```

### Q: 如何运行测试？

```bash
docker exec -it golang go test ./...
```

### Q: 如何格式化代码？

```bash
docker exec -it golang go fmt ./...
```

### Q: 如何进行代码检查？

```bash
docker exec -it golang go vet ./...
```

## 推荐的 IDE 插件

- **VS Code**: Go (by Go Team at Google)
- **GoLand**: JetBrains 官方 Go IDE
- **Vim/Neovim**: vim-go 或 coc.nvim + gopls

## 性能优化

1. **使用 Go 模块代理**: 已配置 `GOPROXY=https://goproxy.cn,direct`
2. **缓存依赖**: 使用 volume 挂载 `/go/pkg/mod/cache`
3. **多阶段构建**: 参考 Dockerfile 中的多阶段构建示例

## 下一步

- 创建你的第一个 Go Web 应用
- 集成数据库（MySQL、PostgreSQL 等）
- 添加单元测试
- 配置 CI/CD 流程
