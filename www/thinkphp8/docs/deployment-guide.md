# 停车场闸机系统 - 部署指南

## 环境要求

### 硬件要求

- **服务器**：
  - CPU: 4核心及以上
  - 内存: 8GB及以上
  - 硬盘: 100GB及以上SSD存储
  - 网络: 千兆网卡

- **闸机设备**：
  - 入场闸机设备
  - 出场闸机设备
  - 车牌识别摄像头
  - 收费终端设备

### 软件要求

- **操作系统**：
  - Linux (推荐 Ubuntu 20.04 LTS 或 CentOS 8)
  - Windows Server 2019 或更高版本

- **基础软件**：
  - Docker 20.10.x 或更高版本
  - Docker Compose 2.x 或更高版本
  - Git

- **网络要求**：
  - 固定IP地址
  - 开放以下端口：80, 443, 3306, 6379, 9092
  - 内网环境中各设备可互相访问

## 安装步骤

### 1. 安装Docker和Docker Compose

#### Ubuntu系统

```bash
# 更新软件包索引
sudo apt update

# 安装必要的依赖
sudo apt install -y apt-transport-https ca-certificates curl software-properties-common

# 添加Docker官方GPG密钥
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo apt-key add -

# 添加Docker软件源
sudo add-apt-repository "deb [arch=amd64] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable"

# 更新软件包索引
sudo apt update

# 安装Docker
sudo apt install -y docker-ce docker-ce-cli containerd.io

# 安装Docker Compose
sudo curl -L "https://github.com/docker/compose/releases/download/v2.18.1/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose

# 启动Docker服务
sudo systemctl enable docker
sudo systemctl start docker

# 将当前用户添加到docker组
sudo usermod -aG docker $USER
```

#### CentOS系统

```bash
# 安装必要的依赖
sudo yum install -y yum-utils device-mapper-persistent-data lvm2

# 添加Docker软件源
sudo yum-config-manager --add-repo https://download.docker.com/linux/centos/docker-ce.repo

# 安装Docker
sudo yum install -y docker-ce docker-ce-cli containerd.io

# 安装Docker Compose
sudo curl -L "https://github.com/docker/compose/releases/download/v2.18.1/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose

# 启动Docker服务
sudo systemctl enable docker
sudo systemctl start docker

# 将当前用户添加到docker组
sudo usermod -aG docker $USER
```

#### Windows系统

1. 从[Docker官网](https://www.docker.com/products/docker-desktop)下载Docker Desktop安装包
2. 双击安装包，按照向导完成安装
3. 安装完成后，启动Docker Desktop

### 2. 克隆项目代码

```bash
# 创建项目目录
mkdir -p /opt/parking-system
cd /opt/parking-system

# 克隆代码仓库
git clone https://github.com/your-organization/parking-system.git .
```

### 3. 配置环境变量

```bash
# 复制环境变量示例文件
cp .example.env .env

# 编辑环境变量文件
nano .env
```

修改以下关键配置：

```
# 数据库配置
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=parking_system
DB_USERNAME=parking_user
DB_PASSWORD=your_strong_password

# Redis配置
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=your_redis_password

# Kafka配置
KAFKA_BROKERS=kafka:9092
KAFKA_GROUP_ID=parking_system

# JWT配置
JWT_SECRET=your_jwt_secret_key
JWT_TTL=86400
```

### 4. 启动Docker容器

```bash
# 构建并启动容器
docker-compose up -d
```

### 5. 初始化数据库

```bash
# 进入Web应用容器
docker-compose exec app bash

# 执行数据库迁移
php think migrate:run

# 导入基础数据
php think seed:run

# 退出容器
exit
```

### 6. 配置Nginx（可选，用于反向代理）

如果需要通过域名访问系统，可以配置Nginx反向代理：

```bash
# 安装Nginx
sudo apt install -y nginx

# 创建站点配置文件
sudo nano /etc/nginx/sites-available/parking-system.conf
```

添加以下配置：

```nginx
server {
    listen 80;
    server_name parking.yourdomain.com;

    location / {
        proxy_pass http://localhost:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

启用站点配置：

```bash
sudo ln -s /etc/nginx/sites-available/parking-system.conf /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

## 配置说明

### 数据库配置

数据库配置位于`.env`文件中，主要包括：

- `DB_HOST`: 数据库主机地址
- `DB_PORT`: 数据库端口
- `DB_DATABASE`: 数据库名称
- `DB_USERNAME`: 数据库用户名
- `DB_PASSWORD`: 数据库密码

### Redis配置

Redis配置位于`.env`文件中，主要包括：

- `REDIS_HOST`: Redis主机地址
- `REDIS_PORT`: Redis端口
- `REDIS_PASSWORD`: Redis密码

### Kafka配置

Kafka配置位于`.env`文件中，主要包括：

- `KAFKA_BROKERS`: Kafka服务器地址
- `KAFKA_GROUP_ID`: 消费者组ID

### JWT配置

JWT配置位于`.env`文件中，主要包括：

- `JWT_SECRET`: JWT签名密钥
- `JWT_TTL`: Token有效期（秒）

### 闸机设备配置

闸机设备配置需要在系统管理后台进行，主要包括：

1. 登录管理后台
2. 进入「设备管理」-「闸机设备」
3. 点击「添加设备」
4. 填写设备信息，包括设备名称、序列号、IP地址等
5. 保存设备信息

### 收费规则配置

收费规则配置需要在系统管理后台进行，主要包括：

1. 登录管理后台
2. 进入「收费管理」-「收费规则」
3. 点击「添加规则」
4. 填写规则信息，包括规则名称、收费标准、免费时长等
5. 保存规则信息

## 常见问题

### 1. 容器启动失败

**问题**：执行`docker-compose up -d`后，部分容器启动失败。

**解决方案**：
- 检查日志：`docker-compose logs`
- 确保端口未被占用：`netstat -tulpn | grep <port>`
- 检查环境变量配置是否正确

### 2. 数据库连接失败

**问题**：系统无法连接到数据库。

**解决方案**：
- 检查数据库容器是否正常运行：`docker-compose ps mysql`
- 检查数据库配置是否正确：`.env`文件中的数据库配置
- 尝试手动连接数据库：`docker-compose exec mysql mysql -u root -p`

### 3. 闸机设备无法连接

**问题**：系统无法连接到闸机设备。

**解决方案**：
- 检查设备IP地址是否正确
- 确保设备和服务器在同一网络环境
- 检查防火墙设置，确保相关端口已开放
- 检查设备电源和网络连接

### 4. 车牌识别不准确

**问题**：车牌识别准确率低。

**解决方案**：
- 调整摄像头位置和角度
- 确保摄像头清洁，无遮挡
- 调整摄像头参数，如亮度、对比度等
- 更新车牌识别算法

### 5. 系统性能问题

**问题**：系统响应缓慢。

**解决方案**：
- 增加服务器资源（CPU、内存）
- 优化数据库索引
- 配置Redis缓存
- 定期清理历史数据

## 系统升级

### 1. 备份数据

```bash
# 备份数据库
docker-compose exec mysql mysqldump -u root -p parking_system > backup.sql

# 备份环境配置
cp .env .env.backup
```

### 2. 更新代码

```bash
# 拉取最新代码
git pull origin main

# 更新依赖
docker-compose exec app composer update
```

### 3. 执行数据库迁移

```bash
# 执行数据库迁移
docker-compose exec app php think migrate:run
```

### 4. 重启服务

```bash
# 重新构建并启动容器
docker-compose down
docker-compose up -d
```

## 安全建议

1. **定期更新密码**：定期更改数据库、Redis和管理员账户的密码
2. **启用HTTPS**：配置SSL证书，启用HTTPS加密通信
3. **限制访问IP**：限制管理后台的访问IP范围
4. **定期备份数据**：建立定时备份机制，确保数据安全
5. **监控系统日志**：定期检查系统日志，及时发现异常情况
6. **更新系统补丁**：定期更新操作系统和应用程序的安全补丁