<?php
/**
 * XHGui 采集演示页
 *
 * 访问 http://localhost/xhprof_demo.php?xhprof=1 触发性能采集，
 * 然后到 http://localhost:8142 查看分析结果。
 * 采集逻辑由 auto_prepend_file (/www/xhgui/profiler.php) 自动完成。
 */

// 模拟一些业务负载
function demo_work(int $rounds): array
{
    $out = [];
    for ($i = 0; $i < $rounds; $i++) {
        $out[] = strtoupper(md5((string)$i));
    }
    return $out;
}

function demo_fib(int $n): int
{
    return $n < 2 ? $n : demo_fib($n - 1) + demo_fib($n - 2);
}

$data = demo_work(2000);
$fib = demo_fib(20);

header('Content-Type: text/plain; charset=utf-8');
echo "demo work done: " . count($data) . " items, fib(20)={$fib}\n";
echo "若 URL 带 ?xhprof=1，本次请求的性能数据已写入 MongoDB。\n";
echo "打开 http://localhost:8142 查看 XHGui 分析结果。\n";