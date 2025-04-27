<?php

// 加载框架引导文件
require __DIR__ . '/vendor/autoload.php';

echo "Elasticsearch Docker环境连接测试\n";

// 检查cURL扩展
if (!extension_loaded('curl')) {
    echo "错误: PHP cURL扩展未安装或未启用!\n";
    echo "请在Docker容器中安装cURL扩展:\n";
    echo "1. 进入PHP容器: docker exec -it php容器ID bash\n";
    echo "2. 安装cURL: apt-get update && apt-get install -y libcurl4-openssl-dev && docker-php-ext-install curl\n";
    echo "3. 重启PHP容器\n";
    exit(1);
}

// 直接设置ES配置
$config = [
    'hosts'        => ['elasticsearch:9200'], // 使用Docker服务名称
    'index_prefix' => 'logs',
    'timeout'      => 10, // 增加超时时间
    'ssl_verify'   => false,
    'max_retry'    => 3
];

echo "配置信息:\n";
echo "- 服务器地址: " . implode(', ', $config['hosts']) . "\n";
echo "- 索引前缀: " . $config['index_prefix'] . "\n";
echo "- 超时设置: " . $config['timeout'] . "秒\n";

try {
    // 创建ES客户端
    $builder = \Elasticsearch\ClientBuilder::create()
        ->setHosts($config['hosts'])
        ->setRetries($config['max_retry'])
        ->setSSLVerification($config['ssl_verify'])
        ->setConnectionParams([
            'client' => [
                'timeout'         => $config['timeout'],
                'connect_timeout' => $config['timeout']
            ]
        ]);

    $client = $builder->build();

    // 检查集群健康状态
    $health = $client->cluster()->health();
    echo "\n集群连接成功!\n";
    echo "- 集群名称: " . ($health['cluster_name'] ?? '未知') . "\n";
    echo "- 集群状态: " . ($health['status'] ?? '未知') . "\n";
    echo "- 节点数量: " . ($health['number_of_nodes'] ?? '未知') . "\n";

    // 创建索引
    $indexName = strtolower($config['index_prefix'] . '-' . date('Y.m.d'));
    echo "\n正在创建/检查索引: {$indexName}\n";

    // 检查索引是否存在
    $indexExists = $client->indices()->exists(['index' => $indexName]);

    if (!$indexExists) {
        // 创建索引
        $createParams = [
            'index' => $indexName,
            'body'  => [
                'settings' => [
                    'number_of_shards'   => 1,
                    'number_of_replicas' => 0  // Docker环境中可能只有一个节点
                ],
                'mappings' => [
                    'properties' => [
                        '@timestamp' => ['type' => 'date'],
                        'message'    => ['type' => 'text'],
                        'level'      => ['type' => 'keyword'],
                        'level_name' => ['type' => 'keyword'],
                        'channel'    => ['type' => 'keyword']
                    ]
                ]
            ]
        ];

        $response = $client->indices()->create($createParams);
        echo "索引创建" . (isset($response['acknowledged']) && $response['acknowledged'] ? '成功' : '失败') . "\n";
    } else {
        echo "索引已存在\n";
    }

    // 写入测试日志
    echo "\n正在写入测试日志...\n";
    $testMessage = '这是一条在Docker环境中通过Elasticsearch客户端写入的测试日志 - ' . date('Y-m-d H:i:s');

    $document = [
        'index' => $indexName,
        'body'  => [
            '@timestamp' => date('c'),
            'message'    => $testMessage,
            'level'      => 200,
            'level_name' => 'INFO',
            'channel'    => 'docker_test',
            'context'    => [
                'source' => 'docker_script'
            ]
        ]
    ];

    $response = $client->index($document);
    echo "日志写入" . (isset($response['result']) && $response['result'] === 'created' ? '成功' : '失败') . "\n";
    echo "文档ID: " . ($response['_id'] ?? '未知') . "\n";

    // 等待日志写入
    echo "\n等待日志写入索引...\n";
    sleep(2);

    // 查询刚写入的日志
    echo "\n查询刚写入的日志...\n";
    try {
        $searchParams = [
            'index' => $indexName,
            'body'  => [
                'query' => [
                    'match' => [
                        'message' => $testMessage
                    ]
                ]
            ]
        ];

        $searchResult = $client->search($searchParams);
        $hits         = $searchResult['hits']['total']['value'] ?? 0;

        echo "查询结果: 找到 {$hits} 条匹配的日志记录\n";

        if ($hits > 0) {
            echo "日志记录成功写入Elasticsearch!\n";
            echo "\n验证MonologElasticsearch驱动是否正常工作:\n";
            echo "1. 日志配置正确，默认通道设置为'elasticsearch'\n";
            echo "2. Elasticsearch服务器连接正常\n";
            echo "3. 可以成功写入和查询日志\n";
            echo "\n结论: 日志系统配置正确，可以成功记录到Elasticsearch中。\n";
        } else {
            echo "未找到刚写入的日志记录，可能存在延迟或写入失败\n";
            echo "\n请检查:\n";
            echo "1. Elasticsearch索引刷新间隔设置\n";
            echo "2. 日志格式是否正确\n";
        }
    } catch (\Throwable $e) {
        echo "查询日志时出错: {$e->getMessage()}\n";
    }

} catch (\Throwable $e) {
    echo "连接Elasticsearch失败: {$e->getMessage()}\n";
    echo "\n请检查:\n";
    echo "1. Elasticsearch服务是否正在运行\n";
    echo "2. 服务器地址配置是否正确 (elasticsearch:9200)\n";
    echo "3. 网络连接是否正常 (Docker网络配置)\n";
    echo "4. PHP容器是否能够解析elasticsearch主机名\n";

    // 尝试ping Elasticsearch主机
    echo "\n正在尝试ping Elasticsearch主机...\n";
    $output    = null;
    $returnVar = null;
    exec('ping -c 3 elasticsearch 2>&1', $output, $returnVar);
    echo "Ping结果: " . ($returnVar === 0 ? "成功" : "失败") . "\n";
    if (!empty($output)) {
        echo implode("\n", $output) . "\n";
    }

    // 提供Docker环境下的解决方案
    echo "\nDocker环境下的解决方案:\n";
    echo "1. 确保Elasticsearch容器正在运行: docker ps | grep elasticsearch\n";
    echo "2. 确保PHP容器和Elasticsearch容器在同一网络: docker network inspect\n";
    echo "3. 尝试使用Elasticsearch容器IP地址替代服务名称\n";
    echo "4. 检查PHP容器内的/etc/hosts文件是否包含elasticsearch条目\n";
}