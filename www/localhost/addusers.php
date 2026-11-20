<?php

// 数据库连接配置
$host = 'mysql5'; // 数据库主机名
$user = 'root';   // 数据库用户名
$pass = '123456'; // 数据库密码
$dbname = 'test'; // 数据库名称
$nameFile = 'names.txt'; // 姓名库文件

// 配置参数
$totalRecords = 1000000; // 总记录数
$bufferSize = 500000; // 增大内存缓冲区大小以减少磁盘I/O
$csvFile = 'temp.csv'; // 临时CSV文件名

// 预先加载姓名到内存中以提高性能
$names = file($nameFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if (empty($names)) die("姓名文件为空或不存在");
$nameCount = count($names);

// 预生成一些密码哈希，避免重复计算
$passwordHashes = [];
for ($i = 0; $i < 10; $i++) {
    $passwordHashes[] = password_hash("password$i", PASSWORD_DEFAULT);
}
$passwordCount = count($passwordHashes);

// 创建CSV临时文件 - 使用更高效的写入方式
$start = microtime(true); // 记录开始时间
$csv = fopen($csvFile, 'w');
if (!$csv) die("无法创建临时文件");

// 禁用PHP输出缓冲以减少内存使用
ob_end_clean();

// 批量生成CSV数据
$csvData = '';
for ($i = 0; $i < $totalRecords; $i++) {
    $name = $names[mt_rand(0, $nameCount - 1)];

    // 生成仿真数据
    $username = sprintf("%s_%06d", $name, mt_rand(0, 999999));
    $email = mb_strtolower(str_replace(' ', '.', $name)) . mt_rand(100, 999) . '@example.com';
    $password = $passwordHashes[mt_rand(0, $passwordCount - 1)];

    // 直接构建CSV行，避免使用fputcsv的开销
    $csvData .= '"' . $username . '","' . $email . '","' . $password . '"' . "\n";

    // 分批写入文件以减少内存使用
    if (($i % $bufferSize == 0 && $i > 0) || $i == $totalRecords - 1) {
        fwrite($csv, $csvData);
        $csvData = '';
    }
}
fclose($csv);

echo "CSV文件生成完成，耗时: " . round(microtime(true) - $start, 2) . " 秒\n";

// 创建数据库连接并启用local_infile
$conn = mysqli_init();
$conn->options(MYSQLI_OPT_LOCAL_INFILE, true);
$conn->real_connect($host, $user, $pass, $dbname);

// 检查连接是否成功
if ($conn->connect_error) die("连接失败: " . $conn->connect_error);
// 设置数据库连接字符集为utf8mb4
$conn->set_charset("utf8mb4");

// 确保表存在
$conn->query("CREATE TABLE IF NOT EXISTS user_test (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// 优化MySQL导入设置
// 禁用自动提交，以手动控制事务的提交时机
$conn->query("SET autocommit=0");
// 禁用唯一性检查，可能在批量插入数据时提高性能，但需谨慎使用以避免数据不一致
$conn->query("SET unique_checks=0");
// 禁用外键检查，可以在插入或更新数据时忽略外键约束，同样需谨慎使用以维护数据完整性
$conn->query("SET foreign_key_checks=0");

// 检查local_infile是否启用
$result = $conn->query("SHOW VARIABLES LIKE 'local_infile'");
$row = $result->fetch_assoc();
echo "local_infile当前设置为: " . $row['Value'] . "\n";

// 尝试启用local_infile
$conn->query("SET GLOBAL local_infile = 1");

// 再次检查设置
$result = $conn->query("SHOW VARIABLES LIKE 'local_infile'");
$row = $result->fetch_assoc();
echo "设置后local_infile为: " . $row['Value'] . "\n";

// 使用LOAD DATA INFILE导入
$importStart = microtime(true);
$sql = "LOAD DATA LOCAL INFILE '$csvFile' 
        INTO TABLE user_test 
        FIELDS TERMINATED BY ',' 
        ENCLOSED BY '\"' 
        LINES TERMINATED BY '\n' 
        (username, email, password)";

if (!$conn->query($sql)) {
    // 如果LOAD DATA LOCAL INFILE失败，尝试使用PDO
    echo "mysqli导入失败，尝试使用PDO: " . $conn->error . "\n";

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass, [
            PDO::MYSQL_ATTR_LOCAL_INFILE => true,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        $pdo->exec($sql);
        echo "PDO导入成功\n";
    } catch (PDOException $e) {
        die("PDO导入也失败: " . $e->getMessage());
    }
}

// 恢复MySQL设置
$conn->query("SET autocommit=1");
$conn->query("SET unique_checks=1");
$conn->query("SET foreign_key_checks=1");

// 清理临时文件
unlink($csvFile);

$time = round(microtime(true) - $start, 2);
$importTime = round(microtime(true) - $importStart, 2);
echo "全部完成！总耗时: $time 秒，导入耗时: $importTime 秒 (" . round($totalRecords / $time) . " 条/秒)";
