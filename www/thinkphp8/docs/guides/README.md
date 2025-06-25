# 开发指南

本文档提供了电子商务应用的开发指南。

## 目录

- [项目架构](./architecture.md)
- [数据库设计](./database.md)
- [开发规范](#开发规范)
- [部署指南](#部署指南)

## 开发规范

### 代码风格

- 遵循PSR-12编码规范
- 使用驼峰命名法命名类和方法（类使用大驼峰，方法使用小驼峰）
- 使用下划线命名法命名变量和属性
- 所有代码文件顶部添加严格类型声明：`declare(strict_types=1);`

### 控制器规范

- 控制器类名以`Controller`结尾
- 控制器方法名使用驼峰命名法
- 控制器方法应尽量精简，复杂业务逻辑应放在服务层处理
- 控制器应统一使用`success`和`error`方法返回响应

```php
// 正确示例
public function register()
{
    try {
        $data = $this->request->post();
        validate(UserValidate::class)->scene('register')->check($data);
        $result = UserService::getInstance()->register($data);
        return $this->success('注册成功', $result);
    } catch (BusinessException $e) {
        return $this->error($e->getMessage());
    }
}
```

### 服务层规范

- 服务类名以`Service`结尾
- 服务类应使用单例模式
- 服务方法应返回具体的结果，而不是响应对象
- 服务方法应使用事务处理复杂业务逻辑

```php
// 正确示例
public function register(array $data)
{
    // 启动事务
    Db::startTrans();
    try {
        // 创建用户
        $user = User::create([
            'username' => $data['username'],
            'password' => password_hash($data['password'], PASSWORD_DEFAULT),
            'email' => $data['email'],
            'nickname' => $data['nickname'] ?? $data['username'],
        ]);
        
        // 提交事务
        Db::commit();
        return $user;
    } catch (\Exception $e) {
        // 回滚事务
        Db::rollback();
        throw new BusinessException('注册失败：' . $e->getMessage());
    }
}
```

### 模型规范

- 模型类名应与数据表名对应（单数形式）
- 模型应在类属性中定义表名、主键等基本信息
- 模型应使用`readonly`属性定义只读字段
- 使用`with`方法预加载关联数据，避免N+1查询问题

```php
// 正确示例
class User extends Model
{
    protected $table = 'pg_users';
    protected $pk = 'id';
    
    // 只读字段
    protected $readonly = ['username', 'create_time'];
    
    // 自动完成
    protected $auto = ['update_time'];
    
    // 关联定义
    public function addresses()
    {
        return $this->hasMany(UserAddress::class, 'user_id', 'id');
    }
}
```

### 验证器规范

- 验证器类名以`Validate`结尾
- 验证器应定义场景方法，避免在控制器中硬编码验证规则
- 验证器应使用自定义方法进行复杂验证

```php
// 正确示例
class UserValidate extends Validate
{
    protected $rule = [
        'username' => 'require|alphaDash|length:4,20|unique:pg_users',
        'password' => 'require|length:6,20',
        'email' => 'require|email|unique:pg_users',
        'mobile' => 'mobile|unique:pg_users',
    ];
    
    protected $message = [
        'username.require' => '用户名不能为空',
        'username.alphaDash' => '用户名只能包含字母、数字、下划线和破折号',
        'username.length' => '用户名长度必须在4-20个字符之间',
        'username.unique' => '用户名已存在',
        'password.require' => '密码不能为空',
        'password.length' => '密码长度必须在6-20个字符之间',
        'email.require' => '邮箱不能为空',
        'email.email' => '邮箱格式不正确',
        'email.unique' => '邮箱已存在',
        'mobile.mobile' => '手机号格式不正确',
        'mobile.unique' => '手机号已存在',
    ];
    
    // 注册场景
    public function sceneRegister()
    {
        return $this->only(['username', 'password', 'email', 'mobile'])
            ->append('password', 'confirm');
    }
}
```

## 部署指南

### 环境要求

- PHP >= 8.2
- PostgreSQL >= 14
- Composer >= 2.0
- Redis >= 6.0 (可选，用于缓存)

### 部署步骤

1. 克隆项目代码

```bash
git clone <repository-url> /path/to/project
cd /path/to/project
```

2. 安装依赖

```bash
composer install --no-dev --optimize-autoloader
```

3. 配置环境变量

复制`.env.example`文件为`.env`，并根据实际环境修改配置：

```bash
cp .env.example .env
```

4. 执行数据库迁移

```bash
php think migrate:run
```

5. 配置Web服务器

Apache配置示例：

```apache
<VirtualHost *:80>
    ServerName example.com
    DocumentRoot /path/to/project/public
    
    <Directory "/path/to/project/public">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Nginx配置示例：

```nginx
server {
    listen 80;
    server_name example.com;
    root /path/to/project/public;
    index index.php index.html;
    
    location / {
        if (!-e $request_filename) {
            rewrite ^(.*)$ /index.php?s=$1 last;
            break;
        }
    }
    
    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

6. 优化配置

```bash
# 生成路由缓存
php think optimize:route

# 生成配置缓存
php think optimize:config

# 生成数据库缓存
php think optimize:schema

# 清除运行时缓存
php think clear
```

7. 设置目录权限

```bash
chmod -R 755 .
chmod -R 777 runtime
chmod -R 777 public/uploads
```

8. 访问测试

访问网站首页，确认系统正常运行。 