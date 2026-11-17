<?php
declare(strict_types=1);

namespace app\cs\controller;

use think\Request;
use app\cs\service\AdminService;
use app\cs\service\Config;

/**
 * 管理后台 API
 */
class Admin extends Base
{
    /** 校验管理令牌（简单鉴权） */
    private function auth(Request $request): bool
    {
        $token = $request->header('X-CS-Token') ?: $request->get('token', '');
        return $token === 'cs-admin-token';
    }

    private function guard(Request $request)
    {
        if (!$this->auth($request)) {
            return $this->fail('未授权', 401);
        }
        return null;
    }

    /** GET /cs/admin/overview */
    public function overview(Request $request)
    {
        if ($e = $this->guard($request)) return $e;
        return $this->ok([
            'overview'   => AdminService::overview(),
            'intents'    => AdminService::intentDistribution(),
            'ratings'    => AdminService::ratingDistribution(),
            'sources'    => AdminService::sourceDistribution(),
            'trend'      => AdminService::dailyTrend(7),
        ]);
    }

    /** GET /cs/admin/conversations */
    public function conversations(Request $request)
    {
        if ($e = $this->guard($request)) return $e;
        $page = max(1, (int)$request->get('page', 1));
        $size = max(1, min(50, (int)$request->get('size', 15)));
        $status = (string)$request->get('status', '');
        $keyword = (string)$request->get('keyword', '');
        return $this->ok(['data' => AdminService::conversations($page, $size, $status, $keyword)]);
    }

    /** GET /cs/admin/conversation?session_id=xxx */
    public function conversation(Request $request)
    {
        if ($e = $this->guard($request)) return $e;
        $sid = (string)$request->get('session_id', '');
        if ($sid === '') return $this->fail('缺少 session_id');
        return $this->ok(['data' => AdminService::conversationDetail($sid)]);
    }

    /** GET /cs/admin/tickets */
    public function tickets(Request $request)
    {
        if ($e = $this->guard($request)) return $e;
        $page = max(1, (int)$request->get('page', 1));
        $size = max(1, min(50, (int)$request->get('size', 15)));
        $status = (string)$request->get('status', '');
        return $this->ok(['data' => AdminService::tickets($page, $size, $status)]);
    }

    /** POST /cs/admin/ticket/reply */
    public function replyTicket(Request $request)
    {
        if ($e = $this->guard($request)) return $e;
        $body = $this->body($request);
        $id = (int)($body['id'] ?? 0);
        $reply = trim((string)($body['reply'] ?? ''));
        if ($id <= 0 || $reply === '') return $this->fail('参数错误');
        AdminService::replyTicket($id, $reply, (string)($body['assignee'] ?? '客服'));
        return $this->ok();
    }

    /** GET /cs/admin/faqs */
    public function faqs(Request $request)
    {
        if ($e = $this->guard($request)) return $e;
        return $this->ok(['data' => AdminService::faqs()]);
    }

    /** GET /cs/admin/config —— 返回前端需要的非敏感配置 */
    public function config(Request $request)
    {
        if ($e = $this->guard($request)) return $e;
        $llm = Config::llm();
        return $this->ok(['llm_mode' => $llm['mode'], 'llm_model' => $llm['model']]);
    }
}
