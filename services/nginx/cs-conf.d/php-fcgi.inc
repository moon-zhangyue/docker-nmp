# P2-3.8：公共 PHP-FPM location 片段
# 由 localhost(443)、tp8.local 等 server 通过 `include /etc/nginx/conf.d/php-fcgi.inc;` 引用，
# 统一使用 [^/]\.php(/|$)（支持 /index.php/foo 形式的 pathinfo），避免每个 server 各写一套。
#
# 文件名用 .inc 而不是 .conf：nginx.conf 里的 `include /etc/nginx/conf.d/*.conf` 是在 http
# 上下文加载的，而 location 只能出现在 server/location 内，若命名为 .conf 会直接语法报错。
location ~ [^/]\.php(/|$) {
    fastcgi_pass   php85:9000;
    include        fastcgi-php.conf;
    include        fastcgi_params;
}
