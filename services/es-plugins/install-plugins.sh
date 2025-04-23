#!/bin/bash

# Elasticsearch 插件安装脚本
# 用于解决插件安装问题

# 确保脚本在错误时退出
set -e

echo "===== Elasticsearch 插件安装脚本 ====="
echo "此脚本将帮助您正确安装 Elasticsearch 插件"

# 清理plugins目录中的所有zip文件
echo "清理plugins目录中的所有zip文件..."
find /usr/share/elasticsearch/plugins -name "*.zip" -type f -delete

# 检查是否有旧的插件zip文件在临时目录
if [ ! -d "/tmp/es_plugins" ]; then
  echo "创建临时插件目录..."
  mkdir -p /tmp/es_plugins
fi

# 如果有插件zip文件在plugins目录，移动到临时目录
if ls /usr/share/elasticsearch/plugins/*.zip 1> /dev/null 2>&1; then
  echo "发现插件zip文件，正在移动到临时目录..."
  mv /usr/share/elasticsearch/plugins/*.zip /tmp/es_plugins/
  echo "已将插件文件移动到 /tmp/es_plugins/"
fi

# 安装插件
install_plugin() {
  local plugin_name=$1
  local plugin_file=$2
  local plugin_url=$3
  
  echo "正在安装插件: $plugin_name"
  
  # 检查插件是否已安装
  if elasticsearch-plugin list | grep -q "$plugin_name"; then
    echo "插件 $plugin_name 已安装，跳过"
    return 0
  fi
  
  # 检查是否有本地插件文件
  if [[ "$plugin_url" == file://* ]]; then
    echo "使用本地文件安装: $plugin_url"
    elasticsearch-plugin install --batch "$plugin_url"
  elif [ -f "/tmp/es_plugins/$plugin_file" ]; then
    echo "使用临时目录中的文件安装: $plugin_file"
    elasticsearch-plugin install --batch "file:///tmp/es_plugins/$plugin_file"
  else
    echo "从远程下载安装: $plugin_url"
    elasticsearch-plugin install --batch "$plugin_url"
  fi
  
  echo "插件 $plugin_name 安装完成"
}

# 根据环境变量安装插件
# 确保PLUGINS变量有值，并且前后都有逗号，便于匹配
PLUGINS=",${PLUGINS},"

if [[ "$PLUGINS" == *",analysis-icu,"* ]]; then
  install_plugin "analysis-icu" "" "analysis-icu"
fi

# if [[ "$PLUGINS" == *",analysis-ik,"* ]]; then
#   install_plugin "analysis-ik" "analysis-ik-Latest.zip" "https://github.com/infinilabs/analysis-ik/archive/refs/tags/Latest.zip"
# fi

# if [[ "$PLUGINS" == *",analysis-pinyin,"* ]]; then
#   install_plugin "analysis-pinyin" "analysis-pinyin-Latest.zip" "https://github.com/infinilabs/analysis-pinyin/archive/refs/tags/Latest.zip"
# fi

if [[ "$PLUGINS" == *",analysis-smartcn,"* ]]; then
  install_plugin "analysis-smartcn" "" "analysis-smartcn"
fi

# 最后再次清理plugins目录中的所有zip文件
echo "最终清理plugins目录中的所有zip文件..."
find /usr/share/elasticsearch/plugins -name "*.zip" -type f -delete

echo "===== 插件安装完成 ====="
echo "已安装的插件列表:"
elasticsearch-plugin list

echo ""
echo "如果您需要手动安装插件，可以使用以下命令:"
echo "elasticsearch-plugin install [plugin_name]"

echo ""
echo "祝您使用愉快！"