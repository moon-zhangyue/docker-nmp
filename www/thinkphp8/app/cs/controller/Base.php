<?php
declare(strict_types=1);

namespace app\cs\controller;

use think\Request;
use think\Response;

/**
 * 客服模块基础控制器：统一 JSON 响应 + CORS
 */
abstract class Base
{
    protected function json(array $data, int $code = 200): Response
    {
        return json($data, $code)->header([
            'Access-Control-Allow-Origin'  => '*',
            'Access-Control-Allow-Methods' => 'GET,POST,PUT,DELETE,OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type,Authorization,X-Requested-With,X-CS-Token',
        ]);
    }

    protected function ok(array $data = []): Response
    {
        return $this->json(array_merge(['code' => 0, 'msg' => 'ok'], $data));
    }

    protected function fail(string $msg, int $code = 400): Response
    {
        return $this->json(['code' => $code, 'msg' => $msg], 200);
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
