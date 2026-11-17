<?php

declare(strict_types=1);

namespace app\cs\controller;

use think\Request;
use app\cs\service\ChatService;
use app\cs\service\IntentService;

/**
 * 用户端对话 API
 */
class Chat extends Base
{
    /** POST /cs/chat/new —— 新建会话 */
    public function new(Request $request)
    {
        $body = $this->body($request);
        $session = ChatService::newSession([
            'user_id'   => $body['user_id'] ?? '',
            'user_name' => $body['user_name'] ?? '访客',
        ]);
        return $this->ok($session);
    }

    /** POST /cs/chat/send —— 发送消息 */
    public function send(Request $request)
    {
        $body = $this->body($request);
        $sessionId = trim((string)($body['session_id'] ?? ''));
        $question  = trim((string)($body['message'] ?? ''));
        if ($sessionId === '') return $this->fail('缺少 session_id');
        if ($question === '') return $this->fail('消息内容不能为空');

        $result = ChatService::handle($sessionId, $question, [
            'user_name' => $body['user_name'] ?? '访客',
        ]);
        if (isset($result['error'])) return $this->fail($result['error']);
        return $this->ok($result);
    }

    /** GET /cs/chat/history?session_id=xxx —— 获取会话消息历史 */
    public function history(Request $request)
    {
        $sessionId = trim((string)$request->get('session_id', ''));
        if ($sessionId === '') return $this->fail('缺少 session_id');
        return $this->ok(['messages' => ChatService::messages($sessionId)]);
    }

    /** POST /cs/chat/rate —— 提交满意度 */
    public function rate(Request $request)
    {
        $body = $this->body($request);
        $sessionId = trim((string)($body['session_id'] ?? ''));
        $rating = (int)($body['rating'] ?? 0);
        if ($sessionId === '' || $rating < 1) return $this->fail('参数错误');
        $result = ChatService::rate($sessionId, $rating, (string)($body['comment'] ?? ''));
        return $this->ok($result);
    }

    /** GET /cs/chat/intents —— 意图列表（用于前端展示） */
    public function intents()
    {
        return $this->ok(['intents' => IntentService::INTENTS]);
    }

    /** POST /cs/chat/sessions —— 按 id 列表批量获取会话摘要（历史记录） */
    public function sessions(Request $request)
    {
        $body = $this->body($request);
        $ids = $body['session_ids'] ?? [];
        if (!is_array($ids)) $ids = [];
        return $this->ok(['sessions' => ChatService::sessionsByIds($ids)]);
    }
}
