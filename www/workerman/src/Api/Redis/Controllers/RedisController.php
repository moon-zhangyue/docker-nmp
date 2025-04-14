<?php

namespace App\Api\Redis\Controllers;

use App\Api\Redis\Interfaces\RedisServiceInterface;
use App\Core\Http\JsonResponse;
use Throwable;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Worker;

/**
 * Redis控制器
 */
class RedisController
{
    private RedisServiceInterface $redis;

    public function __construct(RedisServiceInterface $redis)
    {
        $this->redis = $redis;
    }

    /**
     * 设置字符串值
     * @param TcpConnection $connection
     * @param Request $request
     */
    public function set(TcpConnection $connection, Request $request): void
    {
        try {
            $data = json_decode($request->rawBody(), true);
            if (!isset($data['key']) || !isset($data['value'])) {
                JsonResponse::error($connection, 'Missing required parameters');
                return;
            }

            $this->redis->set($data['key'], $data['value'], $data['ttl'] ?? null);
            JsonResponse::success($connection);
        } catch (Throwable $e) {
            Worker::log("Redis SET请求处理失败: " . $e->getMessage());
            JsonResponse::error($connection, $e->getMessage());
        }
    }

    /**
     * 获取字符串值
     * @param TcpConnection $connection
     * @param Request $request
     */
    public function get(TcpConnection $connection, Request $request): void
    {
        try {
            $key = $request->get('key');
            if (!$key) {
                JsonResponse::error($connection, 'Missing key parameter');
                return;
            }

            $value = $this->redis->get($key);
            JsonResponse::success($connection, ['value' => $value]);
        } catch (Throwable $e) {
            Worker::log("Redis GET请求处理失败: " . $e->getMessage());
            JsonResponse::error($connection, $e->getMessage());
        }
    }

    /**
     * 设置哈希表字段
     * @param TcpConnection $connection
     * @param Request $request
     */
    public function hSet(TcpConnection $connection, Request $request): void
    {
        try {
            $data = json_decode($request->rawBody(), true);
            if (!isset($data['key']) || !isset($data['field']) || !isset($data['value'])) {
                JsonResponse::error($connection, 'Missing required parameters');
                return;
            }

            $this->redis->hSet($data['key'], $data['field'], $data['value']);
            JsonResponse::success($connection);
        } catch (Throwable $e) {
            Worker::log("Redis HSET请求处理失败: " . $e->getMessage());
            JsonResponse::error($connection, $e->getMessage());
        }
    }

    /**
     * 从左侧推入列表
     * @param TcpConnection $connection
     * @param Request $request
     */
    public function lPush(TcpConnection $connection, Request $request): void
    {
        try {
            $data = json_decode($request->rawBody(), true);
            if (!isset($data['key']) || !isset($data['value'])) {
                JsonResponse::error($connection, 'Missing required parameters');
                return;
            }

            $this->redis->lPush($data['key'], $data['value']);
            JsonResponse::success($connection);
        } catch (Throwable $e) {
            Worker::log("Redis LPUSH请求处理失败: " . $e->getMessage());
            JsonResponse::error($connection, $e->getMessage());
        }
    }

    /**
     * 添加集合成员
     * @param TcpConnection $connection
     * @param Request $request
     */
    public function sAdd(TcpConnection $connection, Request $request): void
    {
        try {
            $data = json_decode($request->rawBody(), true);
            if (!isset($data['key']) || !isset($data['member'])) {
                JsonResponse::error($connection, 'Missing required parameters');
                return;
            }

            $this->redis->sAdd($data['key'], $data['member']);
            JsonResponse::success($connection);
        } catch (Throwable $e) {
            Worker::log("Redis SADD请求处理失败: " . $e->getMessage());
            JsonResponse::error($connection, $e->getMessage());
        }
    }

    /**
     * 添加有序集合成员
     * @param TcpConnection $connection
     * @param Request $request
     */
    public function zAdd(TcpConnection $connection, Request $request): void
    {
        try {
            $data = json_decode($request->rawBody(), true);
            if (!isset($data['key']) || !isset($data['score']) || !isset($data['member'])) {
                JsonResponse::error($connection, 'Missing required parameters');
                return;
            }

            $this->redis->zAdd($data['key'], (float)$data['score'], $data['member']);
            JsonResponse::success($connection);
        } catch (Throwable $e) {
            Worker::log("Redis ZADD请求处理失败: " . $e->getMessage());
            JsonResponse::error($connection, $e->getMessage());
        }
    }

    /**
     * 删除键
     * @param TcpConnection $connection
     * @param Request $request
     */
    public function del(TcpConnection $connection, Request $request): void
    {
        try {
            $key = $request->get('key');
            if (!$key) {
                JsonResponse::error($connection, 'Missing key parameter');
                return;
            }

            $this->redis->del($key);
            JsonResponse::success($connection);
        } catch (Throwable $e) {
            Worker::log("Redis DEL请求处理失败: " . $e->getMessage());
            JsonResponse::error($connection, $e->getMessage());
        }
    }

    /**
     * 设置过期时间
     * @param TcpConnection $connection
     * @param Request $request
     */
    public function expire(TcpConnection $connection, Request $request): void
    {
        try {
            $data = json_decode($request->rawBody(), true);
            if (!isset($data['key']) || !isset($data['ttl'])) {
                JsonResponse::error($connection, 'Missing required parameters');
                return;
            }

            $this->redis->expire($data['key'], (int)$data['ttl']);
            JsonResponse::success($connection);
        } catch (Throwable $e) {
            Worker::log("Redis EXPIRE请求处理失败: " . $e->getMessage());
            JsonResponse::error($connection, $e->getMessage());
        }
    }
} 