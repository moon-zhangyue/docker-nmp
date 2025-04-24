# ES商品名称自动补全接口文档

## 接口描述
本接口用于根据前端输入的关键词，自动补全并返回匹配的商品名称列表，便于实现商品搜索框的智能提示功能。

## 请求方式
- 方法：GET
- 路径：/goods/autocomplete

## 请求参数
| 参数名   | 类型   | 是否必填 | 说明         |
|----------|--------|----------|--------------|
| keyword  | string | 是       | 待补全关键词 |

## 响应格式
- Content-Type: application/json

### 成功响应
```
{
  "status": "success",
  "data": [
    "商品名称1",
    "商品名称2",
    ...
  ]
}
```

### 失败响应
```
{
  "status": "error",
  "message": "错误信息"
}
```

## 示例
### 请求示例
```
GET /goods/autocomplete?keyword=苹果
```

### 响应示例
```
{
  "status": "success",
  "data": [
    "苹果手机",
    "苹果平板",
    "苹果耳机"
  ]
}
```

## 备注
- 若keyword参数为空，接口会返回错误提示。
- 默认最多返回10条匹配结果。
- 匹配方式为商品名称前缀匹配，如需更复杂的补全可扩展为completion suggester。