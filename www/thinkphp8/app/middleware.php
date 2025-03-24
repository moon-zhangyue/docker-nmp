<?php
// 全局中间件定义文件
return [
    // 全局请求缓存
    // \think\middleware\CheckRequestCache::class,
    // 多语言加载
    // \think\middleware\LoadLangPack::class,
    // Session初始化
    // \think\middleware\SessionInit::class,
    // JWT认证中间件
    'jwt_auth'  => \app\middleware\JwtAuth::class,
    // 跨域请求中间件
    'cors'      => \app\middleware\Cors::class,
    // 操作日志记录中间件
    'audit_log' => \app\middleware\AuditLog::class,
];
