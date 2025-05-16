# ThinkPHP 8 Swagger API文档使用指南

## 简介

本项目集成了Swagger (OpenAPI)接口文档功能，可以自动生成API文档，方便开发人员查看和测试API接口。

## 访问API文档

API文档可以通过以下URL访问：

- Swagger UI界面：`/swagger`
- OpenAPI规范JSON：`/swagger/json`
- OpenAPI规范YAML：`/swagger/yaml`

## 为控制器添加Swagger注解

### 基本注解

在控制器类上添加基本信息注解：

```php
/**
 * 控制器描述
 * 
 * @OA\Tag(
 *     name="标签名称",
 *     description="标签描述"
 * )
 */
class YourController
{
    // ...
}
```

### 为方法添加注解

在控制器方法上添加接口注解：

```php
/**
 * 方法描述
 * 
 * @OA\Get(
 *     path="/your/api/path",
 *     summary="接口摘要",
 *     description="接口详细描述",
 *     operationId="方法名",
 *     tags={"标签名称"},
 *     @OA\Parameter(
 *         name="参数名",
 *         in="query",
 *         description="参数描述",
 *         required=false,
 *         @OA\Schema(type="string", default="默认值")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="成功响应描述",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=1),
 *             @OA\Property(property="msg", type="string", example="操作成功"),
 *             @OA\Property(property="data", type="object")
 *         )
 *     )
 * )
 */
public function yourMethod()
{
    // ...
}
```

## 常用注解说明

### 请求方法注解

- `@OA\Get` - GET请求
- `@OA\Post` - POST请求
- `@OA\Put` - PUT请求
- `@OA\Delete` - DELETE请求

### 参数注解

- `@OA\Parameter` - 请求参数
  - `in="query"` - 查询参数
  - `in="path"` - 路径参数
  - `in="header"` - 请求头参数
  - `in="cookie"` - Cookie参数

### 请求体注解

```php
@OA\RequestBody(
    description="请求体描述",
    required=true,
    @OA\JsonContent(
        @OA\Property(property="name", type="string", example="张三"),
        @OA\Property(property="age", type="integer", example=18)
    )
)
```

### 响应注解

```php
@OA\Response(
    response=200,
    description="成功响应描述",
    @OA\JsonContent(
        @OA\Property(property="code", type="integer", example=1),
        @OA\Property(property="msg", type="string", example="操作成功"),
        @OA\Property(
            property="data",
            type="object",
            @OA\Property(property="id", type="integer", example=1),
            @OA\Property(property="name", type="string", example="张三")
        )
    )
)
```

## 示例：为"取消关注"功能添加API文档

以下是为SetDemo控制器中的"取消关注"功能添加API文档的示例：

```php
/**
 * 用户关注关系示例
 * 
 * @OA\Get(
 *     path="/redis/set/user-follows",
 *     summary="用户关注关系操作",
 *     description="管理用户之间的关注关系，包括关注、取消关注、查询关注状态等",
 *     operationId="userFollows",
 *     tags={"Redis Set"},
 *     @OA\Parameter(
 *         name="action",
 *         in="query",
 *         description="操作类型：follow(关注)、unfollow(取消关注)、is_following(检查关注状态)、get_following(获取关注列表)、get_followers(获取粉丝列表)、get_mutuals(获取互相关注)、stats(统计信息)",
 *         required=false,
 *         @OA\Schema(type="string", default="stats")
 *     ),
 *     @OA\Parameter(
 *         name="user_id",
 *         in="query",
 *         description="当前用户ID",
 *         required=false,
 *         @OA\Schema(type="integer", default=1)
 *     ),
 *     @OA\Parameter(
 *         name="target_id",
 *         in="query",
 *         description="目标用户ID（被关注/取消关注的用户）",
 *         required=false,
 *         @OA\Schema(type="integer", default=0)
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="操作成功",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=1),
 *             @OA\Property(property="msg", type="string", example="用户关注关系操作成功"),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(property="status", type="string", example="success"),
 *                 @OA\Property(property="message", type="string", example="用户 1 已取消关注用户 2")
 *             )
 *         )
 *     )
 * )
 */
```

## 注意事项

1. 确保安装了`zircote/swagger-php`依赖
2. 注解必须符合OpenAPI规范
3. 在生产环境中，可以考虑禁用Swagger文档或添加访问控制
4. 定期更新API文档，确保与实际代码保持一致
