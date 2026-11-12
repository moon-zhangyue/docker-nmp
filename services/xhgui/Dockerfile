# XHGui 运行容器
# perftools/xhgui 要求 PHP ^7.2 || ~8.0 || ~8.1，不能跑在 php85 上，故独立构建。
# 源码不打进镜像，由 docker-compose 挂载 ${SOURCE_DIR}/xhgui 到 /www/xhgui，
# vendor 依赖已随源码存在于 www/xhgui/html/vendor。
FROM php:8.1-fpm

# ext-mongodb 固定 1.x：composer.lock 中 mongodb/mongodb 1.2.0 +
# alcaeus/mongo-php-adapter 1.2.1 仅兼容 ext-mongodb ^1.3
RUN apt-get update && apt-get install -y --no-install-recommends \
        libssl-dev \
        pkg-config \
    && pecl install mongodb-1.21.1 \
    && docker-php-ext-enable mongodb \
    && rm -rf /var/lib/apt/lists/*

# Slim 3 / Twig 1 等旧依赖在 PHP 8.1 下会产生大量 Deprecated 提示，屏蔽之
RUN printf 'error_reporting = E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED\ndisplay_errors = Off\nlog_errors = On\n' > /usr/local/etc/php/conf.d/xhgui.ini

WORKDIR /www/xhgui/html

EXPOSE 9000

CMD ["php-fpm"]