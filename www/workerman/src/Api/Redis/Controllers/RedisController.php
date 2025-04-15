<?php

namespace App\Api\Redis\Controllers;

use App\Api\Redis\Interfaces\RedisServiceInterface;
use App\Core\Http\JsonResponse;
use Throwable;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Worker;
use Workerman\Redis\Client;

/**
 * Redis控制器
 */
class RedisController
{
    private ?Client $redis = null;

    public function __construct(RedisServiceInterface $redis)
    {
        $this->redis = $redis->getRedis();
    }

    /**
     * 记录Redis操作日志
     */
    private function logRedisOperation(string $operation, array $data, ?string $error = null): void
    {
        $message = "Redis {$operation} " . ($error ? "失败" : "成功");
        $context = implode(", ", array_map(fn($k, $v) => "{$k}:{$v}", array_keys($data), $data));
        if ($error) {
            $message .= " - {$context} - {$error}";
        } else {
            $message .= " - {$context}";
        }
        Worker::log($message);
    }

    /**
     * 验证必需参数
     */
    private function validateRequiredParams(array $required, array $data): ?string
    {
        foreach ($required as $param) {
            if (!isset($data[$param])) {
                return "Missing required parameter: {$param}";
            }
        }
        return null;
    }

    /**
     * 处理Redis异步操作
     */
    private function handleRedisOperation(string $operation, array $data, callable $redisCall, TcpConnection $connection): void
    {
        try {
            $result = $redisCall();
            if ($result === false || ($operation === 'SET' && $result !== 'OK')) {
                $this->logRedisOperation($operation, $data, "操作失败");
                JsonResponse::error($connection, "Redis operation failed");
                return;
            }
            $this->logRedisOperation($operation, $data);
            JsonResponse::success($connection, ['result' => $result]);
        } catch (Throwable $e) {
            $this->logRedisOperation($operation, $data, $e->getMessage());
            JsonResponse::error($connection, $e->getMessage());
        }
    }

    /**
     * 设置字符串值
     * @param TcpConnection $connection
     * @param Request $request
     */
    public function set(TcpConnection $connection, Request $request): void
    {
        $data = $request->post();
        if ($error = $this->validateRequiredParams(['key', 'value'], $data)) {
            JsonResponse::error($connection, $error);
            return;
        }

        if (isset($data['ttl'])) {
            $this->handleRedisOperation('SETEX', $data, 
                fn() => $this->redis->aSetex($data['key'], $data['ttl'], $data['value']),
                $connection
            );
        } else {
            $this->handleRedisOperation('SET', $data,
                fn() => $this->redis->aSet($data['key'], $data['value']),
                $connection
            );
        }
    }

    /**
     * 获取字符串值
     * @param TcpConnection $connection
     * @param Request $request
     */
    public function get(TcpConnection $connection, Request $request): void
    {
        $key = $request->get('key');
        if (!$key) {
            JsonResponse::error($connection, 'Missing key parameter');
            return;
        }

        $this->handleRedisOperation('GET', ['key' => $key],
            fn() => $this->redis->aGet($key),
            $connection
        );
    }

    /**
     * 设置哈希表字段
     * @param TcpConnection $connection
     * @param Request $request
     */
    public function hSet(TcpConnection $connection, Request $request): void
    {
        $data = $request->post();
        if ($error = $this->validateRequiredParams(['key', 'field', 'value'], $data)) {
            JsonResponse::error($connection, $error);
            return;
        }

        $this->handleRedisOperation('HSET', $data,
            fn() => $this->redis->aHset($data['key'], $data['field'], $data['value']),
            $connection
        );
    }

    /**
     * 从左侧推入列表
     * @param TcpConnection $connection
     * @param Request $request
     */
    public function lPush(TcpConnection $connection, Request $request): void
    {
        $data = $request->post();
        if ($error = $this->validateRequiredParams(['key', 'value'], $data)) {
            JsonResponse::error($connection, $error);
            return;
        }

        $this->handleRedisOperation('LPUSH', $data,
            fn() => $this->redis->aLpush($data['key'], $data['value']),
            $connection
        );
    }

    /**
     * 添加集合成员
     * @param TcpConnection $connection
     * @param Request $request
     */
    public function sAdd(TcpConnection $connection, Request $request): void
    {
        $data = $request->post();
        if ($error = $this->validateRequiredParams(['key', 'member'], $data)) {
            JsonResponse::error($connection, $error);
            return;
        }

        $this->handleRedisOperation('SADD', $data,
            fn() => $this->redis->aSadd($data['key'], $data['member']),
            $connection
        );
    }

    /**
     * 添加有序集合成员
     * @param TcpConnection $connection
     * @param Request $request
     */
    public function zAdd(TcpConnection $connection, Request $request): void
    {
        $data = $request->post();
        if ($error = $this->validateRequiredParams(['key', 'score', 'member'], $data)) {
            JsonResponse::error($connection, $error);
            return;
        }

        $this->handleRedisOperation('ZADD', $data,
            fn() => $this->redis->aZadd($data['key'], (float)$data['score'], $data['member']),
            $connection
        );
    }

    /**
     * 删除键
     * @param TcpConnection $connection
     * @param Request $request
     */
    public function del(TcpConnection $connection, Request $request): void
    {
        $key = $request->get('key');
        if (!$key) {
            JsonResponse::error($connection, 'Missing key parameter');
            return;
        }

        $this->handleRedisOperation('DEL', ['key' => $key],
            fn() => $this->redis->aDel($key),
            $connection
        );
    }

    /**
     * 设置过期时间
     * @param TcpConnection $connection
     * @param Request $request
     */
    public function expire(TcpConnection $connection, Request $request): void
    {
        $data = $request->post();
        if ($error = $this->validateRequiredParams(['key', 'ttl'], $data)) {
            JsonResponse::error($connection, $error);
            return;
        }

        $this->handleRedisOperation('EXPIRE', $data,
            fn() => $this->redis->aExpire($data['key'], (int)$data['ttl']),
            $connection
        );
    }
} 