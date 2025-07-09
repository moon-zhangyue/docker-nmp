<?php
declare(strict_types=1);

namespace app\exception;

use Exception;
use Throwable;

/**
 * 业务异常类
 */
class BusinessException extends Exception
{
    /**
     * 构造函数
     *
     * @param string $message 错误信息
     * @param int $code 错误码
     * @param Throwable|null $previous 上一个异常
     */
    public function __construct(string $message = '', int $code = 400, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
} 