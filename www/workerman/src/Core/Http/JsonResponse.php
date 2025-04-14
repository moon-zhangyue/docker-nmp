<?php

namespace App\Core\Http;

use Workerman\Connection\TcpConnection;

class JsonResponse
{
    /**
     * 成功响应
     * @param TcpConnection $connection
     * @param mixed $data
     * @param string $message
     */
    public static function success(TcpConnection $connection, mixed $data = [], string $message = 'success'): void
    {
        static::send($connection, true, $message, $data);
    }

    /**
     * 错误响应
     * @param TcpConnection $connection
     * @param string $message
     * @param mixed $data
     */
    public static function error(TcpConnection $connection, string $message = 'error', mixed $data = []): void
    {
        static::send($connection, false, $message, $data);
    }

    /**
     * 发送JSON响应
     * @param TcpConnection $connection
     * @param bool $success
     * @param string $message
     * @param mixed $data
     */
    private static function send(TcpConnection $connection, bool $success, string $message, mixed $data): void
    {
        $response = [
            'success' => $success,
            'message' => $message,
            'data' => $data
        ];

        $connection->send(json_encode($response, JSON_UNESCAPED_UNICODE));
    }
} 