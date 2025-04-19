<?php
declare(strict_types=1); // 严格类型声明，确保代码类型安全

namespace app\controller; // 定义当前类所在的命名空间

use app\BaseController; // 引入基础控制器类
use think\Request;
use app\service\ElasticsearchService; // 引入Elasticsearch服务类
use think\facade\Log;


class Goods extends BaseController // 定义Goods控制器类，继承自BaseController
{
    protected $esService; // 定义Elasticsearch服务对象的属性

    public function __construct() // 构造函数，初始化Elasticsearch服务对象
    {
        $this->esService = new ElasticsearchService('goods'); // 创建Elasticsearch服务实例，指定索引为'goods'
    }

    // 全文搜索（支持名称和描述）
    public function search(Request $request)
    {
        $query  = $request->post('query'); // 获取请求中的查询参数
        $client = $this->esService->getClient(); // 获取Elasticsearch客户端实例
        $params = [ // 定义搜索参数
            'index' => 'goods', // 指定搜索索引为'goods'
            'body'  => [
                'query'     => [ // 定义查询体
                    'bool' => [ // 使用布尔查询
                        'must'   => [ // 必须匹配的条件
                            'multi_match' => [ // 多字段匹配
                                'query'     => $query, // 搜索查询
                                'fields'    => ['name^2', 'description'], // 名称权重更高
                                'fuzziness' => 'AUTO',
                            ],
                        ],
                        'filter' => [ // 定义一个过滤器数组，用于筛选数据
                            ['term' => ['status' => 1]], // 仅搜索上架商品
                        ],
                    ],
                ],
                'highlight' => [  // 定义一个名为 'highlight' 的数组，用于配置高亮显示的相关设置
                    'fields' => [  // 在 'highlight' 数组中定义一个名为 'fields' 的子数组，用于指定需要高亮显示的字段
                        'name'        => new \stdClass(),  // 在 'fields' 数组中定义一个名为 'name' 的键，其值为一个新的 \stdClass 对象，表示 'name' 字段需要高亮显示
                        'description' => new \stdClass(),  // 在 'fields' 数组中定义一个名为 'description' 的键，其值为一个新的 \stdClass 对象，表示 'description' 字段需要高亮显示
                    ],
                ],
            ],
        ];
        try {
            $response = $client->search($params);
            $hits     = $response['hits']['hits'];// 提取搜索结果中的所有命中项
            $results  = [];
            foreach ($hits as $hit) {
                $results[] = [
                    'source'    => $hit['_source'],
                    'highlight' => $hit['highlight'] ?? [],
                ];
            }
            return json(['status' => 'success', 'data' => $results]);
        } catch (\Exception $e) {
            Log::error('Search error: ' . $e->getMessage());
            return json(['status' => 'error', 'message' => '搜索失败'], 500);
        }
    }

    // 按价格范围和分类过滤
    public function filter(Request $request)
    {
        // 确保价格参数被正确转换为浮点数并强制类型
        $minPrice   = (float) $request->get('min_price', 0);
        $maxPrice   = (float) $request->get('max_price', 10000);
        $categoryId = $request->get('category_id');
        $client     = $this->esService->getClient();

        // 记录请求参数，便于调试
        Log::info('Price filter params:' . json_encode([
            'min_price'      => $minPrice,
            'max_price'      => $maxPrice,
            'min_price_type' => gettype($minPrice),
            'max_price_type' => gettype($maxPrice),
            'category_id'    => $categoryId
        ]));

        // 定义一个数组 $params，用于存储查询参数
        $params = [
            // 指定索引名称为 'goods'
            'index' => 'goods',
            // 指定查询的主体内容
            'body'  => [
                // 定义查询条件
                'query' => [
                    // 使用布尔查询（bool query），可以组合多个查询条件
                    'bool' => [
                        // 定义过滤条件（filter），用于精确匹配，不计算得分
                        'filter' => [
                            // 使用范围查询（range query），查询价格在 $minPrice 和 $maxPrice 之间的商品
                            ['range' => ['price' => ['gte' => (float) $minPrice, 'lte' => (float) $maxPrice]]],
                            // 使用术语查询（term query），查询状态为 1 的商品
                            ['term' => ['status' => 1]],
                        ],

                    ],
                ],
                'sort'  => [
                    'price' => [
                        'order' => 'asc',
                    ],
                ],
            ],
        ];
        if ($categoryId) {
            // 根据类别ID筛选搜索条件
            $params['body']['query']['bool']['filter'][] = ['term' => ['category_id' => $categoryId]];
        }
        try {
            $response = $client->search($params);
            $hits     = $response['hits']['hits'];
            $results  = array_map(fn($hit) => $hit['_source'], $hits);
            return json(['status' => 'success', 'data' => $results]);
        } catch (\Exception $e) {
            Log::error('Filter error: ' . $e->getMessage());
            return json(['status' => 'error', 'message' => '过滤失败'], 500);
        }
    }

    // 按分类聚合统计
    public function aggregateByCategory()
    {
        $client = $this->esService->getClient();
        $params = [
            'index' => 'goods',
            'body'  => [
                'query' => [
                    'term' => ['status' => 1],
                ],
                'aggs'  => [
                    'by_category' => [
                        'terms' => [
                            'field' => 'category_id',
                            'size'  => 10,
                        ],
                    ],
                ],
            ],
        ];
        try {
            $response     = $client->search($params);
            $aggregations = $response['aggregations']['by_category']['buckets'];
            return json(['status' => 'success', 'data' => $aggregations]);
        } catch (\Exception $e) {
            Log::error('Aggregation error: ' . $e->getMessage());
            return json(['status' => 'error', 'message' => '聚合失败'], 500);
        }
    }

    // 批量同步（用于初始化或数据修复）
    public function sync()
    {
        $spus   = \app\model\GoodsSpu::with(['skus', 'attributes'])->select();
        $client = $this->esService->getClient();
        $params = ['body' => []];

        foreach ($spus as $spu) {
            foreach ($spu->skus as $sku) {
                $params['body'][] = [
                    'index' => ['_index' => 'goods', '_id' => $sku->id],
                ];
                $params['body'][] = [
                    'spu_id'            => $spu->id,
                    'name'              => $spu->name,
                    'description'       => $spu->description,
                    'category_id'       => $spu->category_id,
                    'brand_id'          => $spu->brand_id,
                    'price'             => (float) $sku->price, // 确保价格被转换为浮点数
                    'stock'             => $sku->stock,
                    'attributes'        => $sku->attributes,
                    'common_attributes' => $spu->attributes->toArray(),
                    'status'            => $sku->status,
                    'created_at'        => $spu->created_at,
                ];
            }
        }

        try {
            $client->bulk($params);
            return json(['status' => 'success', 'message' => '同步完成']);
        } catch (\Exception $e) {
            Log::error('Sync error: ' . $e->getMessage());
            return json(['status' => 'error', 'message' => '同步失败'], 500);
        }
    }
}