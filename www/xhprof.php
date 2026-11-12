<?php
/**
 * xhprof / tideways_xhprof 手动采集示例
 *
 * 推荐方式：php85 已配置 auto_prepend_file = /www/xhgui/profiler.php，
 * 任意页面 URL 加 ?xhprof=1 即可自动采集，无需修改业务代码。
 * 本文件演示如何在代码中手动采集并写入 XHGui。
 */

// 优先 tideways_xhprof，回退 xhprof
if (extension_loaded('tideways_xhprof')) {
    tideways_xhprof_enable(TIDEWAYS_XHPROF_FLAGS_CPU | TIDEWAYS_XHPROF_FLAGS_MEMORY);
    $profiler = 'tideways_xhprof';
} elseif (extension_loaded('xhprof')) {
    xhprof_enable(XHPROF_FLAGS_CPU | XHPROF_FLAGS_MEMORY);
    $profiler = 'xhprof';
} else {
    exit('xhprof / tideways_xhprof extension not loaded');
}

// Your application code here
$result = [];
for ($i = 0; $i < 1000; $i++) {
    $result[] = md5((string)$i);
}

// End profiling and save data
$data = $profiler === 'tideways_xhprof' ? tideways_xhprof_disable() : xhprof_disable();

// MongoDB 键不能含点号
$profile = [];
foreach ($data as $key => $stats) {
    $profile[str_replace('.', '_', $key)] = $stats;
}

$micro = microtime(true);
$sec = (int)$micro;
$url = $_SERVER['REQUEST_URI'] ?? 'cli';

// Connect to MongoDB（docker-compose 的 mongodb 服务，--auth 模式）
$manager = new MongoDB\Driver\Manager('mongodb://root:123456@mongodb:27017/?authSource=admin');
$bulk = new MongoDB\Driver\BulkWrite();
$bulk->insert([
    'profile' => $profile,
    'meta' => [
        'url' => $url,
        'SERVER' => $_SERVER,
        'get' => $_GET,
        'env' => $_ENV,
        'simple_url' => preg_replace('/\=\d+/', '', $url),
        'request_ts' => new MongoDB\BSON\UTCDateTime($sec * 1000),
        'request_ts_micro' => new MongoDB\BSON\UTCDateTime((int)($micro * 1000)),
        'request_date' => date('Y-m-d', $sec),
    ],
]);
$writeResult = $manager->executeBulkWrite('xhgui.results', $bulk);

echo 'Saved ' . $writeResult->getInsertedCount() . ' profile to xhgui, view at http://localhost:8142' . PHP_EOL;