<?php
// InfluxDB连接参数
$url    = 'http://influxdb:8086';
$token  = 'my-super-secret-auth-token'; // 与.env中的INFLUXDB_ADMIN_TOKEN一致
$org    = 'myorg';                        // 与.env中的INFLUXDB_ORG一致
$bucket = 'mybucket';                  // 与.env中的INFLUXDB_BUCKET一致

echo "<h1>InfluxDB 连接测试</h1>";

try {
    // 写入一些测试数据 - 使用当前时间，不指定时间戳
    $data = "measurement1,tagname1=tagvalue1 field1=30,field2=25.5";  // 移除时间戳，让InfluxDB自动添加

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "$url/api/v2/write?org=$org&bucket=$bucket&precision=ns");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Token ' . $token,
        'Content-Type: text/plain; charset=utf-8'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($httpCode == 204) {
        echo "<p style='color:green'>成功写入数据到InfluxDB!</p>";
        echo "<p>写入的数据: <code>$data</code></p>";
    } else {
        echo "<p style='color:red'>写入数据失败: $response</p>";
        curl_close($ch);
        exit;
    }

    curl_close($ch);

    // 等待一秒，确保数据写入完成
    sleep(1);

    // 查询数据 - 使用更宽松的查询，扩大时间范围
    $query = 'from(bucket:"' . $bucket . '") 
              |> range(start: -1d)  // 扩大到1天
              |> filter(fn:(r) => r._measurement == "measurement1")';

    $queryData = ['query' => $query];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "$url/api/v2/query?org=$org");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($queryData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Token ' . $token,
        'Content-Type: application/json',
        'Accept: application/csv'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($httpCode == 200) {
        // 输出原始响应以便调试
        echo "<h2>原始响应:</h2>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";

        // 检查响应是否为空
        if (empty(trim($response))) {
            echo "<p style='color:orange'>查询返回了空响应，可能没有匹配的数据</p>";

            // 尝试一个更宽松的查询，不指定任何过滤条件
            echo "<h2>尝试最宽松的查询:</h2>";
            $broadQuery     = 'from(bucket:"' . $bucket . '") |> range(start: -1d)';
            $broadQueryData = ['query' => $broadQuery];

            $ch2 = curl_init();
            curl_setopt($ch2, CURLOPT_URL, "$url/api/v2/query?org=$org");
            curl_setopt($ch2, CURLOPT_POST, 1);
            curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode($broadQueryData));
            curl_setopt($ch2, CURLOPT_HTTPHEADER, [
                'Authorization: Token ' . $token,
                'Content-Type: application/json',
                'Accept: application/csv'
            ]);
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);

            $broadResponse = curl_exec($ch2);
            $broadHttpCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);

            if ($broadHttpCode == 200) {
                echo "<pre>" . htmlspecialchars($broadResponse) . "</pre>";

                // 如果最宽松的查询也没有结果，检查bucket是否存在
                if (empty(trim($broadResponse))) {
                    echo "<h2>检查存储桶和组织:</h2>";

                    // 获取存储桶列表
                    $ch3 = curl_init();
                    curl_setopt($ch3, CURLOPT_URL, "$url/api/v2/buckets");
                    curl_setopt($ch3, CURLOPT_HTTPHEADER, [
                        'Authorization: Token ' . $token
                    ]);
                    curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);

                    $bucketsResponse = curl_exec($ch3);
                    $bucketsHttpCode = curl_getinfo($ch3, CURLINFO_HTTP_CODE);

                    if ($bucketsHttpCode == 200) {
                        echo "<p>存储桶列表:</p>";
                        echo "<pre>" . htmlspecialchars($bucketsResponse) . "</pre>";
                    } else {
                        echo "<p style='color:red'>获取存储桶列表失败: $bucketsResponse</p>";
                    }

                    curl_close($ch3);
                }
            } else {
                echo "<p style='color:red'>宽松查询失败: $broadResponse</p>";
            }

            curl_close($ch2);
        } else {
            // 解析CSV响应
            $lines = explode("\n", $response);

            echo "<h2>CSV行数: " . count($lines) . "</h2>";

            $headers = null;
            $data    = [];

            foreach ($lines as $i => $line) {
                if (empty(trim($line)))
                    continue;

                $values = str_getcsv($line);

                if ($headers === null) {
                    $headers = $values;
                    echo "<p>找到CSV头: " . implode(", ", $headers) . "</p>";
                    continue;
                }

                // 跳过结果表的元数据行
                if (isset($values[0]) && $values[0] === '#')
                    continue;

                $row = [];
                foreach ($values as $j => $value) {
                    if (isset($headers[$j])) {
                        $row[$headers[$j]] = $value;
                    }
                }

                if (!empty($row)) {
                    $data[] = $row;
                }
            }

            // 显示解析后的数据
            if (!empty($data)) {
                echo "<h2>查询结果:</h2>";
                echo "<pre>";
                foreach ($data as $record) {
                    // 输出完整记录以便调试
                    echo "完整记录: " . json_encode($record) . "\n";

                    // 检查记录中是否包含我们需要的字段
                    if (isset($record['_time']) && isset($record['_value']) && isset($record['_field'])) {
                        $time  = $record['_time'];
                        $field = $record['_field'];
                        $value = $record['_value'];

                        echo "$time $field: $value\n";
                    } else {
                        echo "记录缺少必要字段\n";
                    }
                }
                echo "</pre>";
            } else {
                echo "<p style='color:orange'>查询结果为空或格式不符合预期</p>";
            }
        }
    } else {
        echo "<p style='color:red'>查询数据失败: $response</p>";
    }

    curl_close($ch);

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