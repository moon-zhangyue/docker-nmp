<?php
// 临时验证脚本：检查 xhprof / tideways_xhprof 扩展是否可用
$exts = ['xhprof', 'tideways_xhprof', 'tideways', 'mongodb'];
foreach ($exts as $ext) {
    echo str_pad($ext, 20) . (extension_loaded($ext) ? 'LOADED' : 'missing') . PHP_EOL;
}

if (extension_loaded('xhprof')) {
    xhprof_enable(XHPROF_FLAGS_CPU | XHPROF_FLAGS_MEMORY);
    $a = [];
    for ($i = 0; $i < 1000; $i++) {
        $a[] = md5((string)$i);
    }
    $data = xhprof_disable();
    echo 'xhprof profile entries: ' . count($data) . PHP_EOL;
}

if (extension_loaded('tideways_xhprof')) {
    tideways_xhprof_enable(TIDEWAYS_XHPROF_FLAGS_CPU | TIDEWAYS_XHPROF_FLAGS_MEMORY);
    $a = [];
    for ($i = 0; $i < 1000; $i++) {
        $a[] = md5((string)$i);
    }
    $data = tideways_xhprof_disable();
    echo 'tideways_xhprof profile entries: ' . count($data) . PHP_EOL;
}