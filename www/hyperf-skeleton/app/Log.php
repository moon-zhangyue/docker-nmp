<?php

declare(strict_types=1);

namespace App;

use Hyperf\Logger\LoggerFactory;
use Hyperf\Di\Annotation\Inject;
use Psr\Log\LoggerInterface;

class Log
{
    /**
     * @var LoggerFactory
     */
    protected static ?LoggerFactory $loggerFactory = null;

    /**
     * @var LoggerInterface
     */
    protected static ?LoggerInterface $logger = null;

    /**
     * 获取日志实例
     */
    protected static function getLogger(): LoggerInterface
    {
        if (is_null(self::$logger)) {
            if (is_null(self::$loggerFactory)) {
                self::$loggerFactory = \Hyperf\Context\ApplicationContext::getContainer()->get(LoggerFactory::class);
            }
            self::$logger = self::$loggerFactory->get('default');
        }
        return self::$logger;
    }

    /**
     * 魔术方法，实现静态方法调用
     *
     * @param string $name 方法名
     * @param array $arguments 参数
     * @return mixed
     */
    public static function __callStatic($name, $arguments)
    {
        $logger = self::getLogger();
        return $logger->{$name}(...$arguments);
    }
}