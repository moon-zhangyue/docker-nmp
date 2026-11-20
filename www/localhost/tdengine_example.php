<?php
/**
 * TDengine PHP 使用示例
 * 本示例展示了两种连接TDengine的方式：
 * 1. 使用PHP扩展（推荐，性能更好）
 * 2. 使用RESTful API
 */

echo "========== TDengine PHP 使用示例 ==========\n\n";

// 配置信息
$config = [
    'host' => 'tdengine',  // TDengine服务器主机名
    'port' => 6030,         // TDengine服务器端口（原生连接）
    'rest_port' => 6041,    // TDengine RESTful API端口
    'user' => 'root',       // 用户名
    'pass' => 'taosdata',   // 密码
    'database' => 'test_php' // 数据库名
];

// 检查是否安装了TDengine扩展
$extension_loaded = extension_loaded('tdengine');
echo "TDengine PHP扩展状态: " . ($extension_loaded ? "已安装" : "未安装") . "\n\n";

// ==================== 方法1：使用PHP扩展 ====================
if ($extension_loaded) {
    echo "========== 使用PHP扩展连接 ==========\n";
    try {
        // 连接TDengine
        echo "连接TDengine服务器...\n";
        $conn = new TDengine($config['host'], $config['user'], $config['pass'], null, $config['port']);
        echo "连接成功!\n";
        
        // 创建数据库
        echo "\n创建数据库...\n";
        $conn->exec("CREATE DATABASE IF NOT EXISTS {$config['database']}");
        echo "数据库创建成功!\n";
        
        // 使用数据库
        $conn->select_db($config['database']);
        
        // 创建超级表
        echo "\n创建超级表...\n";
        $conn->exec("CREATE STABLE IF NOT EXISTS devices (
            ts TIMESTAMP,
            temperature FLOAT,
            humidity FLOAT,
            status INT
        ) TAGS (
            location VARCHAR(64),
            device_id INT
        )");
        echo "超级表创建成功!\n";
        
        // 创建子表
        echo "\n创建子表...\n";
        $conn->exec("CREATE TABLE IF NOT EXISTS device_1 USING devices TAGS ('办公室', 1)");
        echo "子表创建成功!\n";
        
        // 插入数据
        echo "\n插入数据...\n";
        $timestamp = time() * 1000; // TDengine使用毫秒时间戳
        $sql = "INSERT INTO device_1 VALUES
            ($timestamp, 23.5, 60.0, 1),
            (" . ($timestamp + 1000) . ", 23.6, 60.2, 1),
            (" . ($timestamp + 2000) . ", 23.4, 60.1, 0)";
        $conn->exec($sql);
        echo "数据插入成功!\n";
        
        // 查询数据
        echo "\n查询数据...\n";
        $result = $conn->query("SELECT * FROM device_1 ORDER BY ts DESC LIMIT 10");
        echo "查询结果:\n";
        while ($row = $result->fetch_row()) {
            echo "时间: " . date('Y-m-d H:i:s', $row[0]/1000) . ", 温度: {$row[1]}, 湿度: {$row[2]}, 状态: {$row[3]}\n";
        }
        
        // 聚合查询
        echo "\n聚合查询...\n";
        $result = $conn->query("SELECT AVG(temperature) as avg_temp, MAX(humidity) as max_hum FROM device_1");
        $row = $result->fetch_row();
        echo "平均温度: {$row[0]}, 最大湿度: {$row[1]}\n";
        
        // 关闭连接
        $conn->close();
        echo "\n连接已关闭\n";
        
    } catch (Exception $e) {
        echo "错误(PHP扩展): " . $e->getMessage() . "\n";
    }
}

// ==================== 方法2：使用RESTful API ====================
echo "\n\n========== 使用RESTful API连接 ==========\n";

// 使用RESTful接口连接TDengine
function rest_query($sql) {
    global $config;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://{$config['host']}:{$config['rest_port']}/rest/sql");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $sql);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Basic " . base64_encode("{$config['user']}:{$config['pass']}")
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception("HTTP错误: $httpCode\nResponse: $response");
    }
    
    return json_decode($response, true);
}

try {
    // 创建数据库
    echo "创建数据库...\n";
    $result = rest_query("CREATE DATABASE IF NOT EXISTS {$config['database']}");
    echo "数据库创建成功!\n";
    
    // 使用数据库
    rest_query("USE {$config['database']}");
    
    // 创建超级表
    echo "\n创建超级表...\n";
    $result = rest_query("CREATE STABLE IF NOT EXISTS sensors (
        ts TIMESTAMP,
        temperature FLOAT,
        humidity FLOAT
    ) TAGS (
        location VARCHAR(64),
        device_id INT
    )");
    echo "超级表创建成功!\n";
    
    // 创建子表
    echo "\n创建子表...\n";
    $result = rest_query("CREATE TABLE IF NOT EXISTS sensor_1 USING sensors TAGS ('实验室', 1)");
    echo "子表创建成功!\n";
    
    // 插入数据
    echo "\n插入数据...\n";
    $timestamp = time() * 1000; // TDengine使用毫秒时间戳
    $result = rest_query("INSERT INTO sensor_1 VALUES
        ($timestamp, 25.5, 55.0),
        (" . ($timestamp + 1000) . ", 25.6, 55.2),
        (" . ($timestamp + 2000) . ", 25.4, 55.1)
    ");
    echo "数据插入成功!\n";
    
    // 查询数据
    echo "\n查询数据...\n";
    $result = rest_query("SELECT * FROM sensor_1 ORDER BY ts DESC LIMIT 10");
    echo "查询结果:\n";
    if (isset($result['data'])) {
        foreach ($result['data'] as $row) {
            $time = date('Y-m-d H:i:s', $row[0]/1000);
            echo "时间: $time, 温度: {$row[1]}, 湿度: {$row[2]}\n";
        }
    }
    
    // 聚合查询
    echo "\n聚合查询...\n";
    $result = rest_query("SELECT AVG(temperature) as avg_temp, MAX(humidity) as max_hum FROM sensor_1");
    echo "平均温度: {$result['data'][0][0]}, 最大湿度: {$result['data'][0][1]}\n";
    
    echo "\nRESTful API测试完成\n";
    
} catch (Exception $e) {
    echo "错误(RESTful): " . $e->getMessage() . "\n";
}

echo "\n========== 测试完成 ==========\n";

// 清理测试数据（取消注释以启用）
/*
if ($extension_loaded) {
    try {
        $conn = new TDengine($config['host'], $config['user'], $config['pass'], null, $config['port']);
        $conn->exec("DROP DATABASE IF EXISTS {$config['database']}");
        $conn->close();
        echo "\n测试数据已清理\n";
    } catch (Exception $e) {
        echo "清理数据错误: " . $e->getMessage() . "\n";
    }
}
*/