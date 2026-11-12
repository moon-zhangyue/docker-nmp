<?php
/**
 * XHGui 性能采集前置脚本（php85 的 auto_prepend_file）
 *
 * 触发条件（满足其一）：
 *   1. URL 带 ?xhprof=1 参数
 *   2. 环境变量 XHGUI_PROFILE=1（采集所有 FPM 请求）
 *   3. CLI 下环境变量 XHGUI_PROFILE_CLI=1
 *
 * 采集的数据写入 MongoDB xhgui.results 集合，
 * 在 http://localhost:8142 (XHGui) 查看分析结果。
 *
 * 优先使用 tideways_xhprof 扩展，未安装时回退到 xhprof。
 */

// CLI 默认跳过，避免影响 composer / hyperf / webman 等命令行进程
if (PHP_SAPI === 'cli' && getenv('XHGUI_PROFILE_CLI') !== '1') {
    return;
}

$xhguiEnabled = getenv('XHGUI_PROFILE') === '1'
    || (isset($_GET['xhprof']) && $_GET['xhprof'] !== '0');
if (!$xhguiEnabled) {
    return;
}

if (extension_loaded('tideways_xhprof')) {
    tideways_xhprof_enable(TIDEWAYS_XHPROF_FLAGS_CPU | TIDEWAYS_XHPROF_FLAGS_MEMORY);
    $xhguiProfiler = 'tideways_xhprof';
} elseif (extension_loaded('xhprof')) {
    xhprof_enable(XHPROF_FLAGS_CPU | XHPROF_FLAGS_MEMORY);
    $xhguiProfiler = 'xhprof';
} else {
    return;
}

register_shutdown_function(function () use ($xhguiProfiler) {
    $data = $xhguiProfiler === 'tideways_xhprof'
        ? tideways_xhprof_disable()
        : xhprof_disable();
    if (!$data) {
        return;
    }

    // MongoDB 的键不能包含点号（与 XHGui\Saver\MongoSaver::encodeProfile 一致），
    // 并补全统计字段（与 XHGui\Saver\NormalizingSaver 一致）
    $profile = [];
    foreach ($data as $key => $stats) {
        $profile[str_replace('.', '_', $key)] = $stats + [
            'ct' => 0,
            'wt' => 0,
            'cpu' => 0,
            'mu' => 0,
            'pmu' => 0,
        ];
    }

    $url = $_SERVER['REQUEST_URI'] ?? ($_SERVER['SCRIPT_NAME'] ?? 'cli');
    $micro = microtime(true);
    $sec = (int)$micro;

    try {
        // 使用驱动级 API，无需依赖 composer 的 mongodb/mongodb 库
        $manager = new MongoDB\Driver\Manager(
            'mongodb://root:123456@mongodb:27017/?authSource=admin'
        );
        $bulk = new MongoDB\Driver\BulkWrite();
        $bulk->insert([
            'meta' => [
                'url' => $url,
                'simple_url' => preg_replace('/\=\d+/', '', $url),
                'SERVER' => $_SERVER,
                'get' => $_GET,
                'env' => $_ENV,
                'request_ts' => new MongoDB\BSON\UTCDateTime($sec * 1000),
                'request_ts_micro' => new MongoDB\BSON\UTCDateTime((int)($micro * 1000)),
                'request_date' => date('Y-m-d', $sec),
            ],
            'profile' => $profile,
        ]);
        $manager->executeBulkWrite('xhgui.results', $bulk);
    } catch (Throwable $e) {
        error_log('[xhgui-profiler] save failed: ' . $e->getMessage());
    }
});