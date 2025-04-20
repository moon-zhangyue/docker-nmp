<?php
// 数据库连接配置
$host     = 'mysql5'; // 替换为你的数据库地址
$dbname   = 'tp8'; // 替换为你的数据库名称
$username = 'root'; // 替换为你的数据库用户名
$password = '123456'; // 替换为你的数据库密码

try {
    // 创建 PDO 连接
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 获取需要更新的用户 ID 列表（假设表中有超过 10 万条记录）
    $stmt     = $pdo->query("SELECT id FROM user LIMIT 100000");
    $user_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // 定义常见的中国手机号前缀
    $phone_prefixes = ['13', '15', '18', '17', '19', '16', '14'];

    // 批量大小
    $batch_size = 1000;

    // 分批次处理更新
    foreach (array_chunk($user_ids, $batch_size) as $batch_user_ids) {
        $update_data = [];
        foreach ($batch_user_ids as $user_id) {
            // 随机选择一个手机号前缀
            $prefix = $phone_prefixes[array_rand($phone_prefixes)];

            // 生成剩余的 9 位数字
            $suffix = str_pad(random_int(0, 999999999), 9, '0', STR_PAD_LEFT);

            // 拼接成完整的手机号
            $phone = $prefix . $suffix;

            // 固定国家为中国
            $country = 'China';

            // 收集更新数据
            $update_data[] = [
                'id'      => $user_id,
                'phone'   => $phone,
                'country' => $country,
            ];
        }

        // 准备批量更新语句
        $sql  = "UPDATE user SET phone = :phone, country = :country WHERE id = :id";
        $stmt = $pdo->prepare($sql);

        // 执行批量更新
        foreach ($update_data as $data) {
            $stmt->execute([
                ':id'      => $data['id'],
                ':phone'   => $data['phone'],
                ':country' => $data['country'],
            ]);
        }

        echo "已成功更新 " . count($batch_user_ids) . " 条记录\n";
    }

} catch (Exception $e) {
    echo "发生错误: " . $e->getMessage() . "\n";
}