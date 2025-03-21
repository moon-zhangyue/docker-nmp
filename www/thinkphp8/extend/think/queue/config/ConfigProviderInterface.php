<?php

declare(strict_types=1);

namespace think\queue\config;

/**
 * 配置提供者接口
 * 定义配置提供者的标准接口
 */
interface ConfigProviderInterface
{
    /**
     * 获取配置值
     * 
     * @param string $key 配置键名
     * @param mixed $default 默认值
     * @return mixed 配置值
     */
    public function get(string $key, $default = null);

    /**
     * 设置配置值
     * 
     * @param string $key 配置键名
     * @param mixed $value 配置值
     * @return bool 操作是否成功
     */
    public function set(string $key, $value): bool;

    /**
     * 批量设置配置
     * 
     * @param array $config 配置数组
     * @return bool 操作是否成功
     */
    public function setMultiple(array $config): bool;

    /**
     * 删除配置
     * 
     * @param string $key 配置键名
     * @return bool 操作是否成功
     */
    public function delete(string $key): bool;

    /**
     * 获取所有配置键
     * 
     * @return array 配置键列表
     */
    public function getKeys(): array;

    /**
     * 获取所有配置
     * 
     * @return array 配置数组
     */
    public function getAll(): array;

    /**
     * 清除所有配置
     * 
     * @return bool 操作是否成功
     */
    public function clear(): bool;
}
