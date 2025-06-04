<?php
echo '<h1>Nginx调试信息</h1>';

echo '<h2>$_SERVER变量中与nginx相关的信息：</h2>';
echo '<pre>';
foreach ($_SERVER as $key => $value) {
    if (stripos($key, 'server') !== false || stripos($value, 'nginx') !== false) {
        echo htmlspecialchars($key) . " => " . htmlspecialchars($value) . "\n";
    }
}
echo '</pre>';

echo '<h2>所有$_SERVER变量：</h2>';
echo '<pre>';
print_r($_SERVER);
echo '</pre>';

echo '<h2>使用curl测试HTTP头：</h2>';
if (function_exists('curl_init')) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost');
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_USERAGENT, 'PHP-Debug-Script');
    $headers = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo '<p style="color: red;">Curl错误：' . htmlspecialchars($error) . '</p>';
    } else {
        echo '<pre>' . htmlspecialchars($headers) . '</pre>';
    }
} else {
    echo '<p style="color: red;">curl扩展不可用</p>';
}

echo '<h2>使用file_get_contents测试：</h2>';
$context = stream_context_create([
    'http' => [
        'method' => 'HEAD',
        'timeout' => 5
    ]
]);

$headers = @get_headers('http://localhost', 1, $context);
if ($headers) {
    echo '<pre>';
    print_r($headers);
    echo '</pre>';
} else {
    echo '<p style="color: red;">无法获取headers</p>';
}
?>
