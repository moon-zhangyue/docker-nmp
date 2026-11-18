<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

// 注册 Apidoc 路由（配置见同目录 app.php，由 WebmanMiddleware 读取）
hg\apidoc\providers\WebmanService::register();

// Webman 静态文件服务不支持目录 index 回落，需显式将 /apidoc 指向文档前端页面
// （前端静态资源需下载后放置于 public/apidoc/ 目录，见 docs/INSTALL.md）
Webman\Route::get('/apidoc', fn() => redirect('/apidoc/index.html'));
