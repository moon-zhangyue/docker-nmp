<?php
declare(strict_types=1);

namespace app\cs\controller;

use think\Request;
use think\Response;

/**
 * 客服模块基础控制器：统一 JSON 响应
 * 前后端同源部署，不输出 Access-Control-Allow-Origin: *，避免任意站点跨域调用
 */
abstract class Base
{
    public const ADMIN_COOKIE = 'cs_admin';

    /** 后台会话 Cookie 签名 */
    protected function adminSign(string $payload): string
    {
        return hash_hmac('sha256', $payload, \app\cs\service\Config::adminSecret());
    }

    /** 校验后台签名 Cookie 是否有效 */
    protected function adminAuthed(): bool
    {
        $token = $_COOKIE[self::ADMIN_COOKIE] ?? '';
        if (!is_string($token) || substr_count($token, '.') !== 1) {
            return false;
        }
        [$payload, $sig] = explode('.', $token, 2);
        if (!hash_equals($this->adminSign($payload), $sig)) {
            return false;
        }
        $data = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
        return is_array($data) && (int)($data['exp'] ?? 0) > time();
    }

    protected function json(array $data, int $code = 200): Response
    {
        // 全局 Cors 中间件会给所有响应加跨域头；客服模块前后端同源部署，
        // 显式移除，避免任何跨站页面读取本模块接口数据
        header_remove('Access-Control-Allow-Origin');
        header_remove('Access-Control-Allow-Credentials');
        header_remove('Access-Control-Allow-Headers');
        header_remove('Access-Control-Allow-Methods');
        return json($data, $code);
    }

    protected function ok(array $data = []): Response
    {
        return $this->json(array_merge(['code' => 0, 'msg' => 'ok'], $data));
    }

    protected function fail(string $msg, int $code = 400): Response
    {
        // 业务码与 HTTP 状态码保持一致，便于网关/监控识别 401/429 等
        $httpCode = ($code >= 100 && $code <= 599) ? $code : 400;
        return $this->json(['code' => $code, 'msg' => $msg], $httpCode);
    }

    /** 获取 JSON 请求体 */
    protected function body(Request $request): array
    {
        $data = $request->post();
        if (empty($data)) {
            $raw = file_get_contents('php://input');
            $parsed = json_decode($raw ?: '', true);
            if (is_array($parsed)) $data = $parsed;
        }
        return is_array($data) ? $data : [];
    }
}
