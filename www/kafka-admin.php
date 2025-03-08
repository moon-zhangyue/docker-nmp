<?php
// Kafka 管理工具
$brokers = 'kafka:9092';

// 创建 Kafka 配置
$conf = new RdKafka\Conf();
$conf->set('metadata.broker.list', $brokers);

// 创建生产者
$producer = new RdKafka\Producer($conf);

// 获取元数据
$metadata = $producer->getMetadata(true, null, 10000);

// 显示 Kafka 信息
echo "<h1>Kafka 集群信息</h1>";
echo "<h2>Brokers:</h2>";
echo "<ul>";
foreach ($metadata->getBrokers() as $broker) {
    echo "<li>Broker {$broker->getId()}: {$broker->getHost()}:{$broker->getPort()}</li>";
}
echo "</ul>";

// 显示主题
echo "<h2>Topics:</h2>";
echo "<ul>";
foreach ($metadata->getTopics() as $topic) {
    echo "<li>Topic: {$topic->getTopic()}</li>";
    echo "<ul>";
    foreach ($topic->getPartitions() as $partition) {
        echo "<li>Partition: {$partition->getId()}, Leader: {$partition->getLeader()}</li>";
    }
    echo "</ul>";
}
echo "</ul>";
