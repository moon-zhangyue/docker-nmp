<?php
/**
 * 测试Nginx版本获取功能
 */

echo '<h1>Nginx版本测试</h1>';

/**
 * 获取Nginx版本
 */
function getNginxVersion()
{
    echo '<h2>测试各种方法获取Nginx版本：</h2>';
    
    // 方法1：尝试从SERVER_SOFTWARE获取
    echo '<h3>方法1：$_SERVER[\'SERVER_SOFTWARE\']</h3>';
    if (isset($_SERVER['SERVER_SOFTWARE'])) {
        $serverSoftware = $_SERVER['SERVER_SOFTWARE'];
        echo "原始值：" . htmlspecialchars($serverSoftware) . "<br>";
        if (preg_match('/nginx\/([0-9.]+)/', $serverSoftware, $matches)) {
            echo "提取的版本：" . $matches[1] . "<br>";
            return $matches[1];
        } else {
            echo "未能从SERVER_SOFTWARE中提取版本<br>";
        }
    } else {
        echo "SERVER_SOFTWARE 未设置<br>";
    }
    
    // 方法2：尝试从HTTP头获取
    echo '<h3>方法2：apache_response_headers()</h3>';
    if (function_exists('apache_response_headers')) {
        $headers = apache_response_headers();
        echo "响应头：<pre>" . print_r($headers, true) . "</pre>";
        if (isset($headers['Server'])) {
            if (preg_match('/nginx\/([0-9.]+)/', $headers['Server'], $matches)) {
                echo "提取的版本：" . $matches[1] . "<br>";
                return $matches[1];
            }
        }
    } else {
        echo "apache_response_headers 函数不可用<br>";
    }
    
    // 方法3：尝试执行nginx命令（如果可用）
    echo '<h3>方法3：shell_exec(\'nginx -v\')</h3>';
    if (function_exists('shell_exec')) {
        $output = @shell_exec('nginx -v 2>&1');
        echo "命令输出：" . htmlspecialchars($output) . "<br>";
        if ($output && preg_match('/nginx version: nginx\/([0-9.]+)/', $output, $matches)) {
            echo "提取的版本：" . $matches[1] . "<br>";
            return $matches[1];
        } else {
            echo "未能从命令输出中提取版本<br>";
        }
    } else {
        echo "shell_exec 函数不可用<br>";
    }
    
    // 方法4：尝试通过curl获取头信息
    echo '<h3>方法4：curl获取HTTP头</h3>';
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost');
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $headers = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "HTTP状态码：" . $httpCode . "<br>";
        echo "响应头：<pre>" . htmlspecialchars($headers) . "</pre>";
        
        if ($headers && preg_match('/Server: nginx\/([0-9.]+)/', $headers, $matches)) {
            echo "提取的版本：" . $matches[1] . "<br>";
            return $matches[1];
        } else {
            echo "未能从curl响应头中提取版本<br>";
        }
    } else {
        echo "curl 扩展不可用<br>";
    }
    
    // 方法5：检查所有$_SERVER变量
    echo '<h3>方法5：所有$_SERVER变量</h3>';
    echo '<pre>';
    foreach ($_SERVER as $key => $value) {
        if (stripos($key, 'server') !== false || stripos($value, 'nginx') !== false) {
            echo htmlspecialchars($key) . " => " . htmlspecialchars($value) . "\n";
        }
    }
    echo '</pre>';
    
    // 如果所有方法都失败，返回SERVER_SOFTWARE或默认信息
    return isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : '无法获取版本信息';
}

// 执行测试
$version = getNginxVersion();
echo '<h2>最终结果：' . htmlspecialchars($version) . '</h2>';

// 显示当前请求的所有头信息
echo '<h2>当前请求的所有头信息：</h2>';
if (function_exists('getallheaders')) {
    echo '<pre>' . print_r(getallheaders(), true) . '</pre>';
} else {
    echo '函数 getallheaders() 不可用';
}
?>
