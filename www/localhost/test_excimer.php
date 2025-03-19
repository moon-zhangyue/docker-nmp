<?php
if (extension_loaded('excimer')) {
    echo "Excimer 扩展已成功加载\n";

    // 创建一个简单的性能分析示例
    $profiler = new ExcimerProfiler;
    $profiler->setEventType(EXCIMER_REAL);
    $profiler->setPeriod(0.1);
    $profiler->start();

    // 测试代码
    for ($i = 0; $i < 1000000; $i++) {
        $a = $i * $i;
    }

    $profiler->stop();
    $log = $profiler->getLog();
    var_dump($log->formatCallgrind());
} else {
    echo "Excimer 扩展未加载\n";
}
