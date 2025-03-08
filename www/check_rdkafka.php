<?php

echo "RdKafka扩展版本信息: ";
var_dump(phpversion('rdkafka'));
echo PHP_EOL;

echo "检查RdKafka\\Producer类是否存在: ";
var_dump(class_exists('RdKafka\\Producer'));
echo PHP_EOL;

if (class_exists('RdKafka\\Producer')) {
    echo "RdKafka\\Producer类中的方法: ";
    var_dump(get_class_methods('RdKafka\\Producer'));
    echo PHP_EOL;
}