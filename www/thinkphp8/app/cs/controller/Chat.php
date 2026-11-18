<?php
declare(strict_types=1);

namespace app\cs\controller;

use think\Request;
use app\cs\service\ChatService;
use app\cs\service\IntentService;
use app\cs\service\Kv;
use app\cs\service\WsTicket;

/**
 * 用户端对话 API
 */
class Chat extends Base
{
    /** 从请求体或 query 取会话访问令牌 */
    private function accessToken(Request $request, array $body = []): string
    {
        return trim((string)($body['access_token'] ?? $request->get('access_token', '')));
    }

    /** 是否触发限流 */
    private function rateLimited(Request $request, string $scope, int $limit, int $window): bool
    {
        return !Kv::rateLimit($scope . ':' . ($request->ip() ?: 'unknown'), $limit, $window);
    }

    /** POST /cs/chat/new —— 新建会话 */
    public function new(Request $request)
    {
        if ($this->rateLimited($request, 'chat_new', 20, 60)) {
            return $this->fail('操作过于频繁，请稍后再试', 429);
        }
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
        if ($this->rateLimited($request, 'chat_send', 30, 60)) {
            return $this->fail('消息过于频繁，请稍后再试', 429);
        }
        $body = $this->body($request);
        $sessionId = trim((string)($body['session_id'] ?? ''));
        $question  = trim((string)($body['message'] ?? ''));
        if ($sessionId === '') return $this->fail('缺少 session_id');
        if ($question === '') return $this->fail('消息内容不能为空');
        if (!ChatService::verifyAccess($sessionId, $this->accessToken($request, $body))) {
            return $this->fail('无权访问该会话', 403);
        }

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
        if (!ChatService::verifyAccess($sessionId, $this->accessToken($request))) {
            return $this->fail('无权访问该会话', 403);
        }
        return $this->ok(['messages' => ChatService::messages($sessionId)]);
    }

    /** POST /cs/chat/rate —— 提交满意度 */
    public function rate(Request $request)
    {
        if ($this->rateLimited($request, 'chat_rate', 10, 60)) {
            return $this->fail('操作过于频繁，请稍后再试', 429);
        }
        $body = $this->body($request);
        $sessionId = trim((string)($body['session_id'] ?? ''));
        $rating = (int)($body['rating'] ?? 0);
        if ($sessionId === '' || $rating < 1) return $this->fail('参数错误');
        if (!ChatService::verifyAccess($sessionId, $this->accessToken($request, $body))) {
            return $this->fail('无权访问该会话', 403);
        }
        $result = ChatService::rate($sessionId, $rating, (string)($body['comment'] ?? ''));
        return $this->ok($result);
    }

    /** GET /cs/chat/intents —— 意图列表（用于前端展示） */
    public function intents()
    {
        return $this->ok(['intents' => IntentService::INTENTS]);
    }

    /** POST /cs/chat/sessions —— 按 id+令牌列表批量获取会话摘要（历史记录） */
    public function sessions(Request $request)
    {
        if ($this->rateLimited($request, 'chat_sessions', 30, 60)) {
            return $this->fail('操作过于频繁，请稍后再试', 429);
        }
        $body = $this->body($request);
        $items = $body['sessions'] ?? [];
        if (!is_array($items)) $items = [];
        return $this->ok(['sessions' => ChatService::sessionsByIds($items)]);
    }

    /**
     * POST /cs/ws/ticket —— 签发 WebSocket 一次性票据
     * 复用会话签名令牌鉴权，签发的票据仅能订阅本会话的事件。
     */
    public function wsTicket(Request $request)
    {
        if ($this->rateLimited($request, 'chat_ws_ticket', 30, 60)) {
            return $this->fail('操作过于频繁，请稍后再试', 429);
        }
        $body = $this->body($request);
        $sessionId = trim((string)($body['session_id'] ?? ''));
        if ($sessionId === '') return $this->fail('缺少 session_id');
        if (!ChatService::verifyAccess($sessionId, $this->accessToken($request, $body))) {
            return $this->fail('无权访问该会话', 403);
        }
        $ticket = WsTicket::issueSession($sessionId);
        if ($ticket === '') return $this->fail('推送服务暂不可用', 503);
        return $this->ok(['ticket' => $ticket, 'ttl' => WsTicket::TTL, 'path' => '/cs/ws']);
    }
}
