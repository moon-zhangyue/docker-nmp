<?php
// 使用 Guzzle HTTP 客户端访问 Consul API
require_once 'vendor/autoload.php';

use GuzzleHttp\Client;

$client = new Client([
    'base_uri' => 'http://consul:8500/v1/',
    'timeout'  => 2.0,
]);

// 获取所有服务
try {
    $response = $client->get('catalog/services');
    $services = json_decode($response->getBody(), true);
    
    echo "<h1>Consul 服务列表</h1>";
    echo "<pre>";
    print_r($services);
    echo "</pre>";
    
    // 获取健康检查状态
    $response = $client->get('health/state/any');
    $checks = json_decode($response->getBody(), true);
    
    echo "<h1>健康检查状态</h1>";
    echo "<pre>";
    print_r($checks);
    echo "</pre>";
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage();
}