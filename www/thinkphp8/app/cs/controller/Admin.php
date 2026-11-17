<?php
declare(strict_types=1);

namespace app\cs\controller;

use think\Request;
use think\Response;
use app\cs\service\AdminService;
use app\cs\service\Config;
use app\cs\service\Kv;

/**
 * 管理后台 API
 * 鉴权方式：账号密码登录 + 服务端签名的 HttpOnly Cookie，前端不再持有任何令牌
 */
class Admin extends Base
{
    private const COOKIE = 'cs_admin';

    /** 生成会话签名 */
    private function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, Config::adminSecret());
    }

    /** 校验签名 Cookie */
    private function auth(): bool
    {
        $token = $_COOKIE[self::COOKIE] ?? '';
        if (!is_string($token) || substr_count($token, '.') !== 1) {
            return false;
        }
        [$payload, $sig] = explode('.', $token, 2);
        if (!hash_equals($this->sign($payload), $sig)) {
            return false;
        }
        $data = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
        return is_array($data) && (int)($data['exp'] ?? 0) > time();
    }

    private function guard(): ?Response
    {
        return $this->auth() ? null : $this->fail('未授权', 401);
    }

    /** POST /cs/admin/login —— 账号密码登录 */
    public function login(Request $request)
    {
        $ip = $request->ip() ?: 'unknown';
        if (!Kv::rateLimit('admin_login:' . $ip, 10, 60)) {
            return $this->fail('尝试过于频繁，请稍后再试', 429);
        }
        $body = $this->body($request);
        $user = (string)($body['user'] ?? '');
        $pass = (string)($body['pass'] ?? '');
        if (!AdminService::verifyCredential($user, $pass)) {
            return $this->fail('账号或密码错误', 401);
        }
        $payload = rtrim(strtr(base64_encode((string) json_encode([
            'u'   => $user,
            'exp' => time() + Config::adminSessionTtl(),
        ])), '+/', '-_'), '=');
        $token = $payload . '.' . $this->sign($payload);
        setcookie(self::COOKIE, $token, [
            'expires'  => time() + Config::adminSessionTtl(),
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        return $this->ok();
    }

    /** POST /cs/admin/logout */
    public function logout()
    {
        setcookie(self::COOKIE, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        return $this->ok();
    }

    /** GET /cs/admin/check —— 登录状态探测 */
    public function check()
    {
        return $this->guard() ?? $this->ok();
    }

    /** GET /cs/admin/overview */
    public function overview(Request $request)
    {
        if ($e = $this->guard()) return $e;
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
        if ($e = $this->guard()) return $e;
        $page = max(1, (int)$request->get('page', 1));
        $size = max(1, min(50, (int)$request->get('size', 15)));
        $status = (string)$request->get('status', '');
        $keyword = (string)$request->get('keyword', '');
        return $this->ok(['data' => AdminService::conversations($page, $size, $status, $keyword)]);
    }

    /** GET /cs/admin/conversation?session_id=xxx */
    public function conversation(Request $request)
    {
        if ($e = $this->guard()) return $e;
        $sid = (string)$request->get('session_id', '');
        if ($sid === '') return $this->fail('缺少 session_id');
        return $this->ok(['data' => AdminService::conversationDetail($sid)]);
    }

    /** GET /cs/admin/tickets */
    public function tickets(Request $request)
    {
        if ($e = $this->guard()) return $e;
        $page = max(1, (int)$request->get('page', 1));
        $size = max(1, min(50, (int)$request->get('size', 15)));
        $status = (string)$request->get('status', '');
        return $this->ok(['data' => AdminService::tickets($page, $size, $status)]);
    }

    /** POST /cs/admin/ticket/reply */
    public function replyTicket(Request $request)
    {
        if ($e = $this->guard()) return $e;
        $body = $this->body($request);
        $id = (int)($body['id'] ?? 0);
        $reply = trim((string)($body['reply'] ?? ''));
        if ($id <= 0 || $reply === '') return $this->fail('参数错误');
        if (!AdminService::replyTicket($id, $reply, (string)($body['assignee'] ?? '客服'))) {
            return $this->fail('工单不存在', 404);
        }
        return $this->ok();
    }

    /** GET /cs/admin/faqs */
    public function faqs(Request $request)
    {
        if ($e = $this->guard()) return $e;
        return $this->ok(['data' => AdminService::faqs()]);
    }

    /** GET /cs/admin/config —— 返回前端需要的非敏感配置 */
    public function config(Request $request)
    {
        if ($e = $this->guard()) return $e;
        $llm = Config::llm();
        return $this->ok(['llm_mode' => $llm['mode'], 'llm_model' => $llm['model']]);
    }
}
