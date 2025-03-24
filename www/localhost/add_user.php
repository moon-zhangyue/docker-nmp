<?php

// 引入Swoole相关的命名空间
use Swoole\Coroutine;
use Swoole\Database\PDOConfig;
use Swoole\Database\PDOPool;
use Swoole\Coroutine\Channel;



// 配置参数
$config = [
    'db' => [
        'host'        => 'mysql5',        // 数据库主机名
        'port'        => 3306,           // 数据库端口
        'user'        => 'root',         // 数据库用户名
        'password'    => '123456',       // 数据库密码
        'database'    => 'test',         // 数据库名
        'charset'     => 'utf8mb4',      // 数据库字符集
        'timeout'     => 5,              // 数据库连接超时时间
    ],
    'total'          => 1000000,    // 总数据量
    'concurrency'    => 24,         // 增加并发数以提高吞吐量
    'batch_size'     => 2000,       // 增大批量大小以减少网络往返
    'name_file'      => 'names.txt',
    'progress_interval' => 50000,   // 减少进度显示频率以降低开销
    'memory_limit'   => '1024M',    // 增加内存限制以支持更大批量
    'disable_indexes' => true,      // 插入前禁用索引
];

// 初始化姓名库（协程安全方式）
Coroutine\run(function () use ($config) {
    // 设置内存限制
    ini_set('memory_limit', $config['memory_limit']);

    echo "准备插入 {$config['total']} 条数据...\n";

    // 检查并创建数据表
    $pdo = new PDO(
        "mysql:host={$config['db']['host']};port={$config['db']['port']};dbname={$config['db']['database']};charset={$config['db']['charset']}",
        $config['db']['user'],
        $config['db']['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            // PDO::ATTR_PERSISTENT => true, // 移除持久连接选项
        ]
    );

    // 检查表是否存在，不存在则创建
    $pdo->exec("DROP TABLE IF EXISTS `user`");
    $pdo->exec("CREATE TABLE `user` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `username` varchar(255) NOT NULL,
        `email` varchar(255) NOT NULL,
        `password` varchar(255) NOT NULL,
        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `username` (`username`(191)),
        KEY `email` (`email`(191))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 如果配置了禁用索引，则禁用索引以加速插入
    if ($config['disable_indexes']) {
        $pdo->exec("ALTER TABLE `user` DISABLE KEYS");
    }

    // 优化MySQL配置
    $pdo->exec("SET autocommit=0"); // 关闭自动提交
    $pdo->exec("SET unique_checks=0"); // 关闭唯一性检查
    $pdo->exec("SET foreign_key_checks=0"); // 关闭外键检查

    // 检查姓名文件是否存在
    if (!file_exists($config['name_file'])) {
        echo "姓名文件 {$config['name_file']} 不存在，创建示例文件...\n";
        file_put_contents($config['name_file'], "John\nMary\nRobert\nPatricia\nMichael\nJennifer\nWilliam\nLinda\nDavid\nElizabeth\n");
    }

    // 直接读取姓名文件
    $names = file($config['name_file'], FILE_IGNORE_NEW_LINES);
    if (empty($names)) {
        echo "姓名文件为空，无法生成数据\n";
        return;
    }

    $names = array_map('trim', $names);
    $nameCount = count($names);

    // 预生成一些密码哈希，避免重复计算
    $passwordHashes = [];
    for ($i = 0; $i < 10; $i++) {  // 进一步减少预生成的密码数量
        $passwordHashes[] = password_hash("password$i", PASSWORD_BCRYPT, ['cost' => 4]); // 降低哈希成本
    }
    $passwordCount = count($passwordHashes);

    // 创建PDO连接池配置
    $pdoConfig = (new PDOConfig())
        ->withHost($config['db']['host'])
        ->withPort($config['db']['port'])
        ->withDbName($config['db']['database'])
        ->withCharset($config['db']['charset'])
        ->withUsername($config['db']['user'])
        ->withPassword($config['db']['password'])
        ->withOptions([
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => true,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false, // 使用非缓冲查询
            PDO::ATTR_STRINGIFY_FETCHES => false,
            // PDO::ATTR_PERSISTENT => true, // 移除持久连接选项
            PDO::MYSQL_ATTR_FOUND_ROWS => true,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION wait_timeout=28800; SET NAMES utf8mb4;",
        ]);

    // 创建PDO连接池
    $pool = new PDOPool($pdoConfig, $config['concurrency']);

    // 创建任务通道 - 使用更小的缓冲区以节省内存
    $taskChannel = new Channel($config['concurrency'] * 4);

    // 使用Channel作为互斥锁
    $mutexChannel = new Channel(1);
    $mutexChannel->push(true); // 初始化为可用状态

    // 启动数据生成器协程
    Coroutine::create(function () use ($taskChannel, $names, $nameCount, $passwordHashes, $passwordCount, $config) {
        $totalBatches = ceil($config['total'] / $config['batch_size']);
        $remainingItems = $config['total'];
        $batchCount = 0;

        for ($b = 0; $b < $totalBatches; $b++) {
            $currentBatchSize = min($config['batch_size'], $remainingItems);
            $batch = [];

            for ($i = 0; $i < $currentBatchSize; $i++) {
                $name = $names[mt_rand(0, $nameCount - 1)];
                $username = sprintf("%s_%06d", $name, mt_rand(0, 999999));
                $email = strtolower(str_replace(' ', '.', $name)) . mt_rand(100, 999) . '@example.com';
                $password = $passwordHashes[mt_rand(0, $passwordCount - 1)];

                $batch[] = [
                    'username' => $username,
                    'email'    => $email,
                    'password' => $password
                ];
            }

            $taskChannel->push($batch);
            $remainingItems -= $currentBatchSize;
            $batchCount++;

            // 每处理10个批次，手动触发垃圾回收
            if ($batchCount % 10 == 0) {
                $batch = null; // 释放内存
                gc_collect_cycles(); // 强制垃圾回收
            }

            // 如果通道已满，让出CPU时间片
            if ($taskChannel->isFull()) {
                Coroutine::sleep(0.001);
            }
        }

        // 发送结束信号
        for ($i = 0; $i < $config['concurrency']; $i++) {
            $taskChannel->push(null);
        }
    });

    // 启动消费者协程组
    $start = microtime(true);
    $insertedCount = 0;
    $failedCount = 0;

    $workers = [];
    for ($i = 0; $i < $config['concurrency']; $i++) {
        $workers[$i] = Coroutine::create(function () use ($pool, $taskChannel, &$insertedCount, &$failedCount, $mutexChannel, $config, $i, $start) {
            // 注意这里添加了 $start 到 use 语句中
            while (true) {
                $batch = $taskChannel->pop();

                // 结束信号
                if ($batch === null) {
                    break;
                }

                $pdo = $pool->get();
                try {
                    // 使用批量插入
                    $pdo->beginTransaction();

                    // 构建批量插入SQL
                    $placeholders = [];
                    $values = [];

                    foreach ($batch as $item) {
                        $placeholders[] = "(?, ?, ?)";
                        $values[] = $item['username'];
                        $values[] = $item['email'];
                        $values[] = $item['password'];
                    }

                    $sql = "INSERT INTO user (username, email, password) VALUES " . implode(',', $placeholders);
                    $stmt = $pdo->prepare($sql);

                    $result = $stmt->execute($values);
                    $pdo->commit();

                    if ($result) {
                        $count = count($batch);

                        // 获取互斥锁
                        $mutexChannel->pop();
                        $insertedCount += $count;

                        // 显示进度
                        if ($insertedCount % $config['progress_interval'] == 0 || $insertedCount == $config['total']) {
                            $elapsed = max(0.001, microtime(true) - $start); // 避免除以零
                            $percent = round(($insertedCount / $config['total']) * 100, 2);
                            $rate = round($insertedCount / $elapsed);
                            echo "进度: {$insertedCount}/{$config['total']} ({$percent}%), 速率: {$rate} 条/秒\n";
                        }
                        // 释放互斥锁
                        $mutexChannel->push(true);
                    } else {
                        // 获取互斥锁
                        $mutexChannel->pop();
                        $failedCount += count($batch);
                        echo "插入失败: " . json_encode($stmt->errorInfo()) . "\n";
                        // 释放互斥锁
                        $mutexChannel->push(true);
                    }
                } catch (\Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    // 获取互斥锁
                    $mutexChannel->pop();
                    $failedCount += count($batch);
                    echo "协程 {$i} 异常: " . $e->getMessage() . "\n";
                    // 释放互斥锁
                    $mutexChannel->push(true);
                } finally {
                    $pool->put($pdo);
                }
            }
        });
    }

    // 修改这里：将单个协程ID改为数组形式
    // 等待所有工作协程完成
    Coroutine::join($workers);

    // 或者使用另一种方式等待所有协程完成
    // foreach ($workers as $worker) {
    //     Coroutine::wait($worker);
    // }

    $time = round(microtime(true) - $start, 2);
    echo "插入完成！成功: {$insertedCount} 条，失败: {$failedCount} 条，总计尝试: {$config['total']} 条，耗时 {$time} 秒（" . round($insertedCount / $time) . " 条/秒）\n";

    // 如果配置了禁用索引，则重新启用索引
    if ($config['disable_indexes']) {
        echo "重新启用索引...\n";
        $pdo->exec("ALTER TABLE `user` ENABLE KEYS");
    }

    // 恢复MySQL配置
    $pdo->exec("SET autocommit=1");
    $pdo->exec("SET unique_checks=1");
    $pdo->exec("SET foreign_key_checks=1");

    // 关闭连接池
    $pool->close();
});
