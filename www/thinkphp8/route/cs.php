<?php
// +----------------------------------------------------------------------
// | 智能客服 Agent 路由（独立前缀 cs/）
// +----------------------------------------------------------------------
use think\facade\Route;

$ctrl = 'app\cs\controller\\';

// 用户端对话 API
Route::post('cs/chat/new',      $ctrl . 'Chat/new');
Route::post('cs/chat/send',     $ctrl . 'Chat/send');
Route::get ('cs/chat/history',  $ctrl . 'Chat/history');
Route::post('cs/chat/rate',     $ctrl . 'Chat/rate');
Route::get ('cs/chat/intents',  $ctrl . 'Chat/intents');
Route::post('cs/chat/sessions', $ctrl . 'Chat/sessions');

// 实时推送：WebSocket 一次性票据（WS 服务在 cs-ws，经 Nginx /cs/ws 同源反代）
Route::post('cs/ws/ticket',        $ctrl . 'Chat/wsTicket');
Route::post('cs/admin/ws/ticket',  $ctrl . 'Admin/wsTicket');

// 管理后台 API（登录/登出/状态探测无需会话，其余接口由签名 Cookie 鉴权）
Route::post('cs/admin/login',       $ctrl . 'Admin/login');
Route::post('cs/admin/logout',      $ctrl . 'Admin/logout');
Route::get ('cs/admin/check',       $ctrl . 'Admin/check');
Route::get ('cs/admin/overview',     $ctrl . 'Admin/overview');
Route::get ('cs/admin/conversations',$ctrl . 'Admin/conversations');
Route::get ('cs/admin/conversation', $ctrl . 'Admin/conversation');
Route::get ('cs/admin/tickets',      $ctrl . 'Admin/tickets');
Route::post('cs/admin/ticket/reply', $ctrl . 'Admin/replyTicket');
Route::get ('cs/admin/faqs',         $ctrl . 'Admin/faqs');
Route::get ('cs/admin/config',       $ctrl . 'Admin/config');
