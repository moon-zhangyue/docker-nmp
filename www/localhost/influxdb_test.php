<?php
/**
 * InfluxDB 连接测试脚本
 * 
 * 本脚本演示如何使用PHP连接InfluxDB 2.x并执行基本操作
 * 需要安装 influxdb/client PHP库: composer require influxdata/influxdb-client-php
 */

// InfluxDB连接参数
$url    = 'http://influxdb:8086';
$token  = 'my-super-secret-auth-token'; // 与.env中的INFLUXDB_ADMIN_TOKEN一致
$org    = 'myorg';                        // 与.env中的INFLUXDB_ORG一致
$bucket = 'mybucket';                  // 与.env中的INFLUXDB_BUCKET一致

echo "<h1>InfluxDB 连接测试</h1>";

// 检查是否安装了必要的库
if (!class_exists('InfluxDB2\\Client')) {
    echo "<p style='color:red'>错误: 未安装 influxdb/client PHP库</p>";
    echo "<p>请运行: <code>composer require influxdata/influxdb-client-php</code></p>";
    exit;
}

try {
    // 创建客户端
    $client = new InfluxDB2\Client([
        'url'       => $url,
        'token'     => $token,
        'org'       => $org,
        'bucket'    => $bucket,
        'precision' => InfluxDB2\Model\WritePrecision::S
    ]);

    // 写入一些测试数据
    $writeApi = $client->createWriteApi();

    $point = new InfluxDB2\Point('measurement1')
        ->addTag('tagname1', 'tagvalue1')
        ->addField('field1', 30)
        ->addField('field2', 25.5)
        ->time(time());

    $writeApi->write($point);
    $writeApi->close();

    echo "<p style='color:green'>成功写入数据到InfluxDB!</p>";

    // 查询数据
    $queryApi = $client->createQueryApi();
    $query    = 'from(bucket:"' . $bucket . '") |> range(start: -1h) |> filter(fn:(r) => r._measurement == "measurement1")';

    $tables = $queryApi->query($query);

    echo "<h2>查询结果:</h2>";
    echo "<pre>";

    foreach ($tables as $table) {
        foreach ($table->records as $record) {
            $time        = $record->getTime();
            $measurement = $record->getMeasurement();
            $field       = $record->getField();
            $value       = $record->getValue();

            echo "$time $measurement $field: $value\n";
        }
    }

    echo "</pre>";

    // 关闭客户端
    $client->close();

} catch (Exception $e) {
    echo "<p style='color:red'>错误: " . $e->getMessage() . "</p>";
}

echo "<h2>InfluxDB 连接信息</h2>";
echo "<ul>";
echo "<li>URL: $url</li>";
echo "<li>组织: $org</li>";
echo "<li>Bucket: $bucket</li>";
echo "<li>Web界面: <a href='http://localhost:8086' target='_blank'>http://localhost:8086</a></li>";
echo "</ul>";

echo "<p>登录凭据:</p>";
echo "<ul>";
echo "<li>用户名: admin</li>";
echo "<li>密码: admin123</li>";
echo "</ul>";