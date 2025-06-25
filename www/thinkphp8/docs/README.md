# 停车场闸机系统文档

## 文档概述

本文档提供了停车场闸机系统的完整技术文档，包括系统架构、数据库设计、API接口说明和部署指南等内容。文档旨在帮助开发团队、运维人员和使用者更好地理解和使用系统。

## 文档目录

1. [系统概述](./system-overview.md)
   - 系统简介
   - 功能特点
   - 系统架构图
   - 技术栈

2. [数据库设计](./database-design.md)
   - 数据库ER图
   - 表结构说明
   - 字段定义
   - 索引设计

3. [核心模块说明](./core-modules.md)
   - 停车场管理模块
   - 闸机控制模块
   - 车辆管理模块
   - 停车记录模块
   - 收费规则模块

4. [秒杀活动系统](./seckill-activity-guide.md)
   - 系统概述
   - 数据表设计
   - 活动状态管理
   - 使用指南
   - 性能优化
   - 常见问题

5. [API接口文档](./api-documentation.md)
   - 接口规范
   - 认证机制
   - 接口列表
   - 错误码说明

6. [部署指南](./deployment-guide.md)
   - 环境要求
   - 安装步骤
   - 配置说明
   - 常见问题

## 版本历史

| 版本号 | 日期 | 描述 | 作者 |
|--------|------|------|------|
| v1.0.0 | 2024-05-15 | 初始版本 | 系统开发团队 |

# ThinkPHP 8 电子商务应用

基于ThinkPHP 8和PostgreSQL构建的电子商务应用，包含用户管理、商品管理和购物流程的完整实现。

## 项目概述

本项目是一个使用ThinkPHP 8框架开发的电子商务应用程序，采用PostgreSQL作为数据库。项目实现了电子商务网站的核心功能，包括：

- 用户模块：注册、登录、个人信息管理、地址管理
- 商品模块：商品列表、商品详情、分类管理、品牌管理
- 购物模块：购物车管理、订单管理、支付流程

## 技术栈

- 框架：ThinkPHP 8
- 数据库：PostgreSQL 14+
- PHP版本：8.2+

## 项目结构

```
thinkphp8/
├── app/                        # 应用目录
│   ├── controller/pg/          # 控制器目录
│   ├── model/pg/               # 模型目录
│   ├── service/pg/             # 服务层目录
│   ├── validate/pg/            # 验证器目录
│   └── exception/              # 异常处理目录
├── config/                     # 配置目录
├── database/                   # 数据库目录
│   └── migrations/             # 数据库迁移文件目录
├── public/                     # 公共资源目录
├── route/                      # 路由配置目录
└── docs/                       # 文档目录
    ├── api/                    # API文档
    └── guides/                 # 开发指南
```

## 安装和配置

1. 克隆项目

```bash
git clone <repository-url>
cd thinkphp8
```

2. 安装依赖

```bash
composer install
```

3. 配置数据库连接

修改`.env`文件：

```
[DATABASE]
TYPE = pgsql
HOSTNAME = postgres
DATABASE = tp8
USERNAME = postgres
PASSWORD = 123456
HOSTPORT = 8432
CHARSET = utf8
PREFIX = 
```

4. 执行数据库迁移

```bash
php think migrate:run
```

5. 启动服务

```bash
php think run
```

## 文档索引

- [API文档](./api/README.md)
- [开发指南](./guides/README.md)