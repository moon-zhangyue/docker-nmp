<?php
declare(strict_types=1);

namespace app\service;

/**
 * 轻量文件日志：按天切分，追加写，不记录票据/令牌等敏感内容。
 */
final class Logger
{
    public static function info(string $msg): void
    {
        self::write('INFO', $msg);
    }

    public static function warn(string $msg): void
    {
        self::write('WARN', $msg);
    }

    public static function error(string $msg): void
    {
        self::write('ERROR', $msg);
    }

    private static function write(string $level, string $msg): void
    {
        $dir = runtime_path() . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $line = sprintf("[%s] [%s] %s\n", date('Y-m-d H:i:s'), $level, $msg);
        @file_put_contents($dir . '/ws-' . date('Ymd') . '.log', $line, FILE_APPEND | LOCK_EX);
    }
}
