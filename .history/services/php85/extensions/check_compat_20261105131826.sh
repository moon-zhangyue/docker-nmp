#!/bin/sh
# 检查 PECL 扩展最新稳定版对 PHP API 的兼容性
for e in redis swoole mongodb imagick xdebug zookeeper; do
    echo "===== $e ====="
    xml=$(curl -sL "https://pecl.php.net/rest/r/$e/stable.xml")
    if [ -z "$xml" ]; then
        echo "  (no data)"
        continue
    fi
    echo "$xml" | grep -E "<(a|r)>" | head -8
done
