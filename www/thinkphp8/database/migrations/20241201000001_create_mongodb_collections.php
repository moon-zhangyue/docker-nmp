<?php

use think\migration\Migrator;
use think\migration\db\Column;

/**
 * MongoDB集合创建迁移
 * 用于初始化MongoDB的集合结构和索引
 */
class CreateMongodbCollections extends Migrator
{
    /**
     * 执行迁移
     */
    public function up()
    {
        // 注意：这是一个示例迁移文件
        // 实际的MongoDB集合和索引创建应该通过MongoDB的原生命令或专门的MongoDB迁移工具来完成
        // 这里提供的是ThinkPHP框架下的迁移文件结构示例
        
        $this->output->writeln('开始创建MongoDB集合和索引...');
        
        try {
            // 由于ThinkPHP的迁移主要针对关系型数据库
            // MongoDB的集合创建和索引建立需要使用专门的脚本
            $this->createMongoDBCollections();
            
            $this->output->writeln('MongoDB集合和索引创建完成');
        } catch (\Exception $e) {
            $this->output->writeln('MongoDB集合创建失败: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 回滚迁移
     */
    public function down()
    {
        $this->output->writeln('开始删除MongoDB集合...');
        
        try {
            $this->dropMongoDBCollections();
            $this->output->writeln('MongoDB集合删除完成');
        } catch (\Exception $e) {
            $this->output->writeln('MongoDB集合删除失败: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 创建MongoDB集合和索引
     */
    private function createMongoDBCollections()
    {
        // 这里应该调用MongoDB的原生方法来创建集合和索引
        // 以下是伪代码示例，实际实现需要使用MongoDB PHP驱动
        
        $collections = [
            'products' => [
                'indexes' => [
                    ['name' => 'text', 'description' => 'text'], // 全文搜索索引
                    ['category_id' => 1], // 分类索引
                    ['price' => 1], // 价格索引
                    ['created_at' => -1], // 创建时间索引
                    ['sku' => 1], // SKU唯一索引
                ]
            ],
            'iot_data' => [
                'indexes' => [
                    ['device_id' => 1, 'timestamp' => -1], // 复合索引
                    ['timestamp' => -1], // 时间索引
                    ['device_type' => 1], // 设备类型索引
                    ['location' => '2dsphere'], // 地理空间索引
                ]
            ],
            'locations' => [
                'indexes' => [
                    ['location' => '2dsphere'], // 2dsphere地理空间索引
                    ['type' => 1], // 位置类型索引
                    ['name' => 1], // 名称索引
                    ['coordinates' => '2d'], // 2d地理空间索引
                ]
            ],
            'analytics' => [
                'indexes' => [
                    ['user_id' => 1, 'timestamp' => -1], // 用户行为复合索引
                    ['event_type' => 1], // 事件类型索引
                    ['timestamp' => -1], // 时间索引
                    ['session_id' => 1], // 会话索引
                ]
            ],
            'global_data' => [
                'indexes' => [
                    ['region' => 1], // 区域索引
                    ['data_type' => 1], // 数据类型索引
                    ['created_at' => -1], // 创建时间索引
                    ['sync_status' => 1], // 同步状态索引
                    ['global_id' => 1], // 全球ID索引
                ]
            ]
        ];
        
        $this->output->writeln('MongoDB集合配置已准备，需要通过MongoDB原生命令创建');
        $this->output->writeln('请参考以下MongoDB命令:');
        
        foreach ($collections as $collectionName => $config) {
            $this->output->writeln("\n// 创建集合: {$collectionName}");
            $this->output->writeln("db.createCollection('{$collectionName}')");
            
            if (isset($config['indexes'])) {
                foreach ($config['indexes'] as $index) {
                    $indexJson = json_encode($index, JSON_UNESCAPED_UNICODE);
                    $this->output->writeln("db.{$collectionName}.createIndex({$indexJson})");
                }
            }
        }
        
        // 分片配置命令
        $this->output->writeln("\n// 分片配置命令:");
        $this->output->writeln("sh.enableSharding('thinkphp_mongodb')");
        $this->output->writeln("sh.shardCollection('thinkphp_mongodb.iot_data', {device_id: 1, timestamp: 1})");
        $this->output->writeln("sh.shardCollection('thinkphp_mongodb.products', {category_id: 1, created_at: 1})");
        $this->output->writeln("sh.shardCollection('thinkphp_mongodb.analytics', {user_id: 'hashed'})");
    }
    
    /**
     * 删除MongoDB集合
     */
    private function dropMongoDBCollections()
    {
        $collections = ['products', 'iot_data', 'locations', 'analytics', 'global_data'];
        
        $this->output->writeln('请使用以下MongoDB命令删除集合:');
        
        foreach ($collections as $collectionName) {
            $this->output->writeln("db.{$collectionName}.drop()");
        }
    }
}