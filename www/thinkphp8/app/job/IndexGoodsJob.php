<?php
declare(strict_types=1); // 严格类型声明，确保代码中的类型安全

namespace app\job; // 定义当前类所在的命名空间

use think\queue\Job; // 引入think框架的Job类
use app\service\ElasticsearchService; // 引入Elasticsearch服务类
use think\facade\Log; // 引入think框架的日志门面

class IndexGoodsJob
{
    // php think queue:listen // 监听队列任务
    /**
     * 处理队列任务的方法
     * @param Job $job 当前任务实例
     * @param array $data 任务数据
     */
    public function fire(Job $job, $data)
    {
        $esService = new ElasticsearchService(); // 创建Elasticsearch服务实例
        $client    = $esService->getClient(); // 获取Elasticsearch客户端
        // 定义要索引到Elasticsearch的商品数据
        $params = [
            'index' => 'goods', // 索引名称
            'id'    => $data['sku_id'], // 文档ID
            'body'  => [ // 文档内容
                'spu_id'            => $data['spu_id'], // 商品ID
                'name'              => $data['name'], // 商品名称
                'description'       => $data['description'], // 商品描述
                'category_id'       => $data['category_id'], // 商品分类ID
                'brand_id'          => $data['brand_id'], // 商品品牌ID
                'price'             => (float) $data['price'], // 商品价格 - 确保转换为浮点数
                'stock'             => $data['stock'], // 商品库存
                'attributes'        => $data['sku_attributes'], // 商品属性
                'common_attributes' => $data['common_attributes'], // 商品通用属性
                'status'            => $data['status'], // 商品状态
                'created_at'        => $data['created_at'], // 商品创建时间
            ],
        ];
        try {
            // 使用重试机制执行索引操作
            $esService->executeWithRetry(function () use ($client, $params) {
                return $client->index($params); // 执行索引操作
            });

            // 记录成功日志
            Log::info('IndexGoodsJob Async index success:{params}', ['params' => $params]);

            // 删除任务
            $job->delete();
        } catch (\Exception $e) {
            // 记录错误日志
            Log::error('IndexGoodsJob Async index error: ' . $e->getMessage());
            // 如果任务尝试次数超过3次，则删除任务
            if ($job->attempts() >= 3) {
                $job->delete();
            } else {
                // 否则，释放任务并延迟60秒重试
                $job->release(60);
            }
        }
    }
}