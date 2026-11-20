<?php
if (extension_loaded('zookeeper')) {
    echo "Zookeeper 扩展已成功加载\n";

    // 显示扩展版本
    echo "Zookeeper 扩展版本: " . phpversion('zookeeper') . "\n";

    // 尝试连接到 Zookeeper 服务器
    try {
        $zk = new Zookeeper('zookeeper:2181');
        echo "成功连接到 Zookeeper 服务器\n";

        // 创建一个测试节点
        if (!$zk->exists('/test')) {
            $zk->create('/test', 'test data', array(
                array(
                    'perms'  => Zookeeper::PERM_ALL,
                    'scheme' => 'world',
                    'id'     => 'anyone',
                )
            ));
            echo "创建测试节点 /test\n";
        }

        // 获取节点数据
        $data = $zk->get('/test');
        echo "节点 /test 的数据: " . $data . "\n";
    } catch (ZookeeperException $e) {
        echo "Zookeeper 异常: " . $e->getMessage() . "\n";
    }
} else {
    echo "Zookeeper 扩展未加载\n";
}
