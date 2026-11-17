<?php
declare(strict_types=1);

namespace app\cs\service;

/**
 * 智能客服配置中心
 * 所有配置可通过 .env 覆盖，不改动现有项目配置，保持隔离。
 */
class Config
{
    /** MySQL 连接参数（docker 内部网络） */
    public static function db(): array
    {
        return [
            'host'    => env('CS.CS_DB_HOST', 'mysql-9'),
            'port'    => (int) env('CS.CS_DB_PORT', '3306'),
            'name'    => env('CS.CS_DB_NAME', 'cs_agent'),
            'user'    => env('CS.CS_DB_USER', 'root'),
            'pass'    => env('CS.CS_DB_PASS', '123456'),
            'charset' => 'utf8mb4',
        ];
    }

    /** Redis 连接参数 */
    public static function redis(): array
    {
        return [
            'host' => env('REDIS.REDIS_HOST', 'redis'),
            'port' => (int) env('REDIS.REDIS_PORT', 6379),
            'pass' => env('REDIS.REDIS_PASSWORD', ''),
            'select' => (int) env('REDIS.REDIS_SELECT', 0),
        ];
    }

    /** Redis key 前缀 */
    public static function redisPrefix(): string
    {
        return 'cs:';
    }

    /**
     * 大模型配置
     * mode = demo      : 内置演示模式，无需密钥
     * mode = openai    : OpenAI 兼容接口（DeepSeek / 通义 / OpenAI / 本地vLLM 等）
     */
    public static function llm(): array
    {
        return [
            'mode'        => env('CS.CS_LLM_MODE', 'demo'),
            'base_url'    => env('CS.CS_LLM_BASE_URL', 'https://api.openai.com/v1'),
            'api_key'     => env('CS.CS_LLM_API_KEY', ''),
            'model'       => env('CS.CS_LLM_MODEL', 'gpt-4o-mini'),
            'timeout'     => (int) env('CS.CS_LLM_TIMEOUT', 30),
            'max_history' => (int) env('CS.CS_LLM_MAX_HISTORY', 10),
        ];
    }

    /** 管理后台登录凭据 */
    public static function admin(): array
    {
        return [
            'user' => env('CS.CS_ADMIN_USER', 'admin'),
            'pass' => env('CS.CS_ADMIN_PASS', 'admin123'),
        ];
    }

    /** 管理会话签名密钥：优先 .env，缺省由管理员密码派生（改密码即让旧会话失效） */
    public static function adminSecret(): string
    {
        $secret = env('CS.CS_ADMIN_SECRET', '');
        return $secret !== '' ? $secret : hash('sha256', 'cs-admin:' . self::admin()['pass']);
    }

    /** 用户会话访问令牌的签名密钥：优先 .env，缺省从 .env 的应用密钥派生 */
    public static function sessionSecret(): string
    {
        $secret = env('CS.CS_SESSION_SECRET', '');
        return $secret !== '' ? $secret : hash('sha256', 'cs-session:' . env('APP_KEY', 'cs-agent-dev-key'));
    }

    /** 管理会话有效期（秒，8 小时） */
    public static function adminSessionTtl(): int
    {
        return 8 * 3600;
    }

    /** 会话上下文在 Redis 中的过期时间（秒） */
    public static function contextTtl(): int
    {
        return 3600 * 24 * 7;
    }
}
