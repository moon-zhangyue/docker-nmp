#!/bin/sh

# 安装编译所需的依赖
apk add --no-cache \
    git \
    cmake \
    make \
    g++ \
    wget \
    tar

# 安装 TDengine 客户端库
cd /tmp
wget -c https://www.taosdata.com/assets-download/TDengine-client-3.2.3.0-Linux-x64.tar.gz
tar -zxf TDengine-client-3.2.3.0-Linux-x64.tar.gz
cd TDengine-client-3.2.3.0
./install_client.sh

# 设置环境变量
export LD_LIBRARY_PATH=/usr/local/taos/driver:$LD_LIBRARY_PATH

# 克隆 TDengine 连接器源码
git clone https://github.com/Yurunsoft/php-tdengine.git /tmp/php-tdengine

# 编译安装
cd /tmp/php-tdengine
phpize
./configure --with-tdengine=/usr/local/taos
# 使用 4 作为默认并行编译数量，避免使用 nproc
make -j4
make install

# 启用扩展
echo "extension=tdengine.so" > /usr/local/etc/php/conf.d/tdengine.ini

# 清理
cd /
rm -rf /tmp/php-tdengine
rm -rf /tmp/TDengine-client-3.2.3.0*