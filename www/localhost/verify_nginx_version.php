<?php
/**
 * 验证Nginx版本获取功能
 */

/**
 * 获取Nginx版本（优化版本）
 */
function getNginxVersion()
{
    // 方法1：尝试从SERVER_SOFTWARE获取
    if (isset($_SERVER['SERVER_SOFTWARE'])) {
        $serverSoftware = $_SERVER['SERVER_SOFTWARE'];
        if (preg_match('/nginx\/([0-9.]+)/', $serverSoftware, $matches)) {
            return $matches[1];
        }
    }
    
    // 方法2：尝试通过curl获取头信息
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost');
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        $headers = curl_exec($ch);
        curl_close($ch);
        
        if ($headers && preg_match('/Server:\s*nginx\/([0-9.]+)/i', $headers, $matches)) {
            return $matches[1];
        }
    }
    
    // 方法3：检查所有$_SERVER变量中包含nginx的
    foreach ($_SERVER as $value) {
        if (is_string($value) && preg_match('/nginx\/([0-9.]+)/', $value, $matches)) {
            return $matches[1];
        }
    }
    
    // 如果所有方法都失败，返回SERVER_SOFTWARE或默认信息
    return $_SERVER['SERVER_SOFTWARE'] ?? '无法获取版本信息';
}

echo '<h1>Nginx版本验证</h1>';
echo '<p>Nginx版本：<strong>' . htmlspecialchars(getNginxVersion()) . '</strong></p>';

// 显示SERVER_SOFTWARE的原始值
echo '<p>$_SERVER[\'SERVER_SOFTWARE\']：<strong>' . htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'N/A') . '</strong></p>';

// 测试curl方法
if (function_exists('curl_init')) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost');
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    $headers = curl_exec($ch);
    curl_close($ch);
    
    echo '<h2>通过curl获取的HTTP头：</h2>';
    echo '<pre>' . htmlspecialchars($headers) . '</pre>';
}

echo '<p style="color: green;">✅ 如果上面显示了正确的nginx版本号（如1.20.2），说明修改成功！</p>';
?>
