<?php
declare(strict_types=1); // 严格类型声明，确保代码类型安全

namespace app\controller; // 定义当前类所在的命名空间

use app\BaseController; // 引入基础控制器类
use app\model\GoodsSpu;
use app\validate\GoodsSearchValidate;
use think\Request;
use app\service\ElasticsearchService; // 引入Elasticsearch服务类
use think\facade\Log;
use think\facade\Cache; // 引入日志和缓存类
use think\facade\Request as RequestFacade;

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
        $spus   = GoodsSpu::with(['skus', 'attributes'])->select();
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

        // 预热热门查询
        $this->advancedSearch(['category_id' => 1, 'size' => 10]);
        Log::info('Cache preheated for category 1');

        try {
            $client->bulk($params);

            $lastSyncTime = cache('last_sync_time') ?: '1970-01-01';

            $spus = GoodsSpu::with(['skus', 'attributes'])
                ->where('updated_at', '>=', $lastSyncTime)
                ->select();
            // 批量索引逻辑（同前述）
            cache('last_sync_time', date('Y-m-d'));

            return json(['status' => 'success', 'message' => '同步完成']);
        } catch (\Exception $e) {
            Log::error('Sync error: ' . $e->getMessage());
            return json(['status' => 'error', 'message' => '同步失败'], 500);
        }
    }

    // 高级搜索：支持全文搜索、属性过滤和排序
    public function advancedSearch()
    {
        // 1. 验证输入
        $validate = new GoodsSearchValidate();
        if (!$validate->check(RequestFacade::param())) {
            return json(['status' => 'error', 'message' => $validate->getError()], 400);
        }

        // 2. 解析查询参数
        $query            = RequestFacade::param('query', ''); // 全文搜索关键词
        $categoryId       = RequestFacade::param('category_id', 0); // 分类ID
        $minPrice         = RequestFacade::param('min_price', 0); // 最低价格
        $maxPrice         = RequestFacade::param('max_price', 10000); // 最高价格
        $skuAttributes    = RequestFacade::param('sku_attributes', []); // SKU 属性（如 color=红色&size=M）
        $commonAttributes = RequestFacade::param('common_attributes', []); // 公共属性（如 material=棉质）
        $sortField        = RequestFacade::param('sort', 'price'); // 排序字段
        $sortOrder        = RequestFacade::param('order', 'asc'); // 排序顺序（asc/desc）
        $from             = RequestFacade::param('from', 0); // 分页起始
        $size             = RequestFacade::param('size', 10); // 每页数量
        $aggregateFields  = RequestFacade::param('aggregate_fields', ['color', 'size']); // 动态聚合字段

        // 3. 生成缓存键
        $cacheKey = $this->generateCacheKey([
            'query'             => $query,
            'category_id'       => $categoryId,
            'min_price'         => $minPrice,
            'max_price'         => $maxPrice,
            'sku_attributes'    => $skuAttributes,
            'common_attributes' => $commonAttributes,
            'sort'              => $sortField,
            'order'             => $sortOrder,
            'from'              => $from,
            'size'              => $size,
            'aggregate_fields'  => $aggregateFields,
        ]);

        // 4. 检查缓存
        if ($cached = Cache::get($cacheKey)) {
            return json($cached);
        }

        // 缓存不存在，获取锁防止缓存击穿
        $lockKey = 'lock_' . $cacheKey;
        if (Cache::lock($lockKey, 10)->acquire()) {
            // 双重检查，防止其他进程已设置缓存
            if ($cached = Cache::get($cacheKey)) {
                Cache::lock($lockKey)->release();
                return json($cached);
            }

            // 继续执行查询和缓存逻辑，锁会在查询完成后释放
            // 注意：不在此处释放锁，而是在设置缓存后释放
        } else {
            // 无法获取锁，说明其他进程正在设置缓存，等待一段时间后重试
            usleep(100000); // 等待100毫秒
            if ($cached = Cache::get($cacheKey)) {
                return json($cached);
            }
        }

        // 5. 构建 Elasticsearch 查询
        $client = $this->esService->getClient();

        $params = [
            'index' => 'goods',
            'body'  => [
                'from'      => $from,
                'size'      => $size,
                'query'     => [
                    'bool' => [
                        'must'   => [],
                        'filter' => [
                            ['term' => ['status' => 1]], // 仅上架商品
                            ['range' => ['price' => ['gte' => $minPrice, 'lte' => $maxPrice]]],
                        ],
                    ],
                ],
                'sort'      => [
                    [$sortField => ['order' => $sortOrder]],
                    ['stock' => ['order' => 'desc']], // 次要排序：库存降序
                ],
                'highlight' => [ // 定义高亮字段配置
                    'fields' => [ // 指定需要高亮的字段
                        'name'        => new \stdClass(), // 高亮'name'字段
                        'description' => new \stdClass(), // 高亮'description'字段
                    ],
                ],
                'aggs'      => [ // 定义聚合查询配置
                    'sku_attributes'    => [ // 定义一个名为'sku_attributes'的嵌套聚合
                        'nested' => ['path' => 'attributes'], // 指定嵌套路径为'attributes'
                        'aggs'   => [], // 嵌套聚合内部的子聚合，当前为空
                    ],
                    'common_attributes' => [ // 定义一个名为'common_attributes'的嵌套聚合
                        'nested' => ['path' => 'common_attributes'], // 指定嵌套路径为'common_attributes'
                        'aggs'   => [ // 定义嵌套聚合内部的子聚合
                            'by_name' => [ // 定义一个名为'by_name'的聚合
                                'terms' => ['field' => 'common_attributes.name'], // 按'common_attributes.name'字段进行分词聚合
                                'aggs'  => [ // 定义'by_name'聚合内部的子聚合
                                    'by_value' => [ // 定义一个名为'by_value'的聚合
                                        'terms' => ['field' => 'common_attributes.value'], // 按'common_attributes.value'字段进行分词聚合
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        if (!$sortField) {
            $params['body']['sort'] = [['_score' => ['order' => 'desc']]];
        } else {
            $sortFields             = explode(',', $sortField);
            $sortOrders             = explode(',', $sortOrder);
            $params['body']['sort'] = [];
            foreach ($sortFields as $index => $field) {
                $order = $sortOrders[$index] ?? 'asc';

                $params['body']['sort'][] = [$field => ['order' => $order]];
            }
        }

        // 6. 添加全文搜索
        if ($query) {
            $params['body']['query']['bool']['must'][] = [
                'multi_match' => [
                    'query'     => $query,
                    'fields'    => ['name^2', 'description'],
                    'fuzziness' => 'AUTO',
                ],
            ];
        }

        // 7. 添加分类过滤
        if ($categoryId) {
            $params['body']['query']['bool']['filter'][] = [
                'term' => ['category_id' => $categoryId],
            ];
        }

        // 8. 添加 SKU 属性过滤（nested 查询）
        if (!empty($skuAttributes)) {
            foreach ($skuAttributes as $key => $value) {
                $values = is_array($value) ? $value : explode(',', $value);

                $params['body']['query']['bool']['filter'][] = [
                    'nested' => [
                        'path'  => 'attributes',
                        'query' => [
                            'bool' => [
                                'filter' => [
                                    ['term' => ["attributes.{$key}" => $values]],
                                ],
                            ],
                        ],
                    ],
                ];
            }
        }

        // 9. 添加公共属性过滤（nested 查询）
        if (!empty($commonAttributes)) {
            foreach ($commonAttributes as $attr) {
                if (isset($attr['name']) && isset($attr['value'])) {
                    $params['body']['query']['bool']['filter'][] = [
                        'nested' => [
                            'path'  => 'common_attributes',
                            'query' => [
                                'bool' => [
                                    'filter' => [
                                        ['term' => ['common_attributes.name' => $attr['name']]],
                                        ['term' => ['common_attributes.value' => $attr['value']]],
                                    ],
                                ],
                            ],
                        ],
                    ];
                }
            }
        }

        // 10. 添加动态属性聚合
        foreach ($aggregateFields as $field) {
            $params['body']['aggs']['sku_attributes']['aggs']['by_' . $field] = [
                'terms' => [
                    'field' => "attributes.{$field}",
                    'size'  => 10,
                ],
            ];
        }

        // 11. 执行查询
        try {
            $start    = microtime(true);
            $response = $client->search($params);

            // 12. 格式化搜索结果
            $hits    = $response['hits']['hits'];
            $results = [];
            foreach ($hits as $hit) {
                $results[] = [
                    'source'    => $hit['_source'],
                    'highlight' => $hit['highlight'] ?? [],
                ];
            }
            $duration = microtime(true) - $start;
            Log::info('Advanced search completed {duration} seconds,{params}', [
                'duration' => $duration,
                'params'   => json_encode($params['body']),
            ]);

            // 13. 格式化聚合结果
            $aggregations = [
                'sku_attributes'    => [],
                'common_attributes' => [],
            ];
            foreach ($aggregateFields as $field) {
                if (isset($response['aggregations']['sku_attributes']['by_' . $field]['buckets'])) {
                    $aggregations['sku_attributes'][$field] = $response['aggregations']['sku_attributes']['by_' . $field]['buckets'];
                }
            }
            foreach ($response['aggregations']['common_attributes']['by_name']['buckets'] as $nameBucket) {
                $aggregations['common_attributes'][$nameBucket['key']] = $nameBucket['by_value']['buckets'];
            }

            // 14. 构造响应
            $responseData = [
                'status'       => 'success',
                'data'         => $results,
                'total'        => $response['hits']['total']['value'],
                'aggregations' => $aggregations,
            ];

            // 15. 缓存结果
            $cacheTTL = $query ? 300 : 3600; // 有关键词 5 分钟，无关键词 1 小时
            try {
                Cache::set($cacheKey, $responseData, $cacheTTL);
            } catch (\Exception $e) {
                Log::error('Cache set error: ' . $e->getMessage());
            }

            // 释放锁，防止缓存击穿
            if (isset($lockKey) && Cache::has('lock:' . $lockKey)) {
                Cache::lock($lockKey)->release();
            }

            Log::info('Advanced search completed:{duration}ms,{params},{cache_key}', [
                'duration'  => $duration,
                'params'    => json_encode($params['body'], JSON_UNESCAPED_UNICODE),
                'cache_key' => $cacheKey,
            ]);

            return json($responseData);
        } catch (\Exception $e) {
            Log::error('Advanced search error: ' . $e->getMessage());
            return json(['status' => 'error', 'message' => '搜索失败'], 500);
        }
    }

    // 生成缓存键
    protected function generateCacheKey(array $params): string
    {
        // $sortedParams = $params;
        // ksort($sortedParams); // 确保参数顺序一致

        $relevantParams = [
            'query'          => $params['query'],
            'category_id'    => $params['category_id'],
            'sku_attributes' => $params['sku_attributes'],
            // 仅包含影响结果的参数
        ];
        return 'goods_search_' . md5(json_encode($relevantParams));
    }
}