<?php
// 一键清理所有 logs-YYYY.MM.DD 索引脚本
require __DIR__ . '/vendor/autoload.php';

use Elasticsearch\ClientBuilder;

$client = ClientBuilder::create()
    ->setHosts(['ELASTICSEARCH_HOST' => 'elasticsearch:9200'])
    ->setConnectionParams([
        'client' => [
            'timeout'         => 10,
            'connect_timeout' => 5
        ]
    ])
    ->build();

$prefix = 'logs';

try {
    // 获取所有索引
    $indices  = $client->cat()->indices(['format' => 'json']);
    $pattern  = "/^{$prefix}-\\d{4}\\.\\d{2}\\.\\d{2}$/";
    $toDelete = [];
    foreach ($indices as $idx) {
        $name = $idx['index'] ?? '';
        if (preg_match($pattern, $name)) {
            $toDelete[] = $name;
        }
    }
    if (empty($toDelete)) {
        echo "没有需要删除的 {$prefix}-YYYY.MM.DD 索引\n";
        exit(0);
    }
    echo "将删除以下索引：\n";
    foreach ($toDelete as $name) {
        echo " - $name\n";
    }
    echo "确认删除？输入 y 并回车继续：";
    $line = trim(fgets(STDIN));
    if (strtolower($line) !== 'y') {
        echo "操作已取消\n";
        exit(0);
    }
    foreach ($toDelete as $name) {
        try {
            $client->indices()->delete(['index' => $name]);
            echo "已删除 $name\n";
        } catch (Exception $e) {
            echo "删除 $name 失败: " . $e->getMessage() . "\n";
        }
    }
    echo "清理完成！\n";
} catch (Exception $e) {
    echo "操作失败: " . $e->getMessage() . "\n";
    exit(1);
}