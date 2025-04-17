<?php
namespace app\service;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use think\facade\Config;
use think\facade\Log;

/**
 * Elasticsearch服务类
 * 
 * 该类提供了与Elasticsearch交互的各种方法，包括索引管理、文档操作和搜索功能。
 * 支持基本搜索、高级搜索、高亮搜索、模糊搜索、范围搜索和聚合查询等功能。
 * 
 * @package app\service
 */
class ElasticsearchService
{
    /**
     * Elasticsearch客户端实例
     * 
     * @var Client
     */
    protected $client;

    /**
     * 当前操作的索引名称
     * 
     * @var string
     */
    protected $index;

    /**
     * 构造函数，初始化Elasticsearch客户端
     * 
     * @param string $index 索引名称，默认为'users'
     * @throws \Exception 连接Elasticsearch失败时抛出异常
     */
    public function __construct($index = 'users')
    {
        $config      = Config::get('elasticsearch');
        $this->index = $index;
        try {
            $this->client = ClientBuilder::create()
                ->setHosts($config['hosts'])
                ->setApiKey($config['apiKey'] ?? '') // 如果使用 API Key
                ->build();
        } catch (\Exception $e) {
            Log::error('Elasticsearch connection error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 获取Elasticsearch客户端实例
     * 
     * @return Client Elasticsearch客户端实例
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * 创建索引
     * 
     * @param array $mappings 索引映射
     * @return array
     */
    public function createIndex($mappings = [])
    {
        try {
            $params = [
                'index' => $this->index,
                'body'  => []
            ];

            if (!empty($mappings)) {
                $params['body']['mappings'] = $mappings;
            }

            return $this->client->indices()->create($params);
        } catch (\Exception $e) {
            Log::error('Create index error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 检查索引是否存在
     * 
     * @return bool
     */
    public function indexExists()
    {
        try {
            $params = ['index' => $this->index];
            return $this->client->indices()->exists($params)->asBool();
        } catch (\Exception $e) {
            Log::error('Check index exists error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 删除索引
     * 
     * @return array
     */
    public function deleteIndex()
    {
        try {
            $params = ['index' => $this->index];
            return $this->client->indices()->delete($params);
        } catch (\Exception $e) {
            Log::error('Delete index error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 添加或更新文档
     * 
     * @param array $document 文档数据
     * @param string $id 文档ID
     * @return array
     */
    public function indexDocument($document, $id = null)
    {
        try {
            $params = [
                'index' => $this->index,
                'body'  => $document
            ];

            if ($id) {
                $params['id'] = $id;
            }

            return $this->client->index($params);
        } catch (\Exception $e) {
            Log::error('Index document error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 批量索引文档
     * 
     * @param array $documents 文档数组
     * @return array
     */
    public function bulkIndex($documents)
    {
        try {
            $params = ['body' => []];

            foreach ($documents as $document) {
                $action = ['index' => ['_index' => $this->index]];

                if (isset($document['id'])) {
                    $action['index']['_id'] = $document['id'];
                    unset($document['id']);
                }

                $params['body'][] = $action;
                $params['body'][] = $document;
            }

            return $this->client->bulk($params);
        } catch (\Exception $e) {
            Log::error('Bulk index error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 获取文档
     * 
     * @param string $id 文档ID
     * @return array
     */
    public function getDocument($id)
    {
        try {
            $params = [
                'index' => $this->index,
                'id'    => $id
            ];

            $response = $this->client->get($params);
            return $response['_source'];
        } catch (\Exception $e) {
            Log::error('Get document error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 删除文档
     * 
     * @param string $id 文档ID
     * @return array
     */
    public function deleteDocument($id)
    {
        try {
            $params = [
                'index' => $this->index,
                'id'    => $id
            ];

            return $this->client->delete($params);
        } catch (\Exception $e) {
            Log::error('Delete document error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 基本搜索
     * 
     * @param string $query 搜索关键词
     * @param array $fields 搜索字段
     * @param int $from 起始位置
     * @param int $size 每页大小
     * @return array
     */
    public function search($query, $fields = [], $from = 0, $size = 10)
    {
        try {
            $params = [
                'index' => $this->index,
                'body'  => [
                    'from'  => $from,
                    'size'  => $size,
                    'query' => [
                        'multi_match' => [
                            'query'  => $query,
                            'fields' => !empty($fields) ? $fields : ['*'],
                        ],
                    ],
                ],
            ];

            $response = $this->client->search($params);
            return $this->formatSearchResults($response);
        } catch (\Exception $e) {
            Log::error('Search error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 高级搜索
     * 
     * @param array $query 查询条件
     * @param int $from 起始位置
     * @param int $size 每页大小
     * @return array
     */
    public function advancedSearch($query, $from = 0, $size = 10)
    {
        try {
            $params = [
                'index' => $this->index,
                'body'  => [
                    'from'  => $from,
                    'size'  => $size,
                    'query' => $query
                ],
            ];

            $response = $this->client->search($params);
            return $this->formatSearchResults($response);
        } catch (\Exception $e) {
            Log::error('Advanced search error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 带高亮的搜索
     * 
     * @param string $query 搜索关键词
     * @param array $fields 搜索字段
     * @param array $highlightFields 高亮字段
     * @param int $from 起始位置
     * @param int $size 每页大小
     * @return array
     */
    public function searchWithHighlight($query, $fields = [], $highlightFields = [], $from = 0, $size = 10)
    {
        try {
            $params = [
                'index' => $this->index,
                'body'  => [
                    'from'      => $from,
                    'size'      => $size,
                    'query'     => [
                        'multi_match' => [
                            'query'  => $query,
                            'fields' => !empty($fields) ? $fields : ['*'],
                        ],
                    ],
                    'highlight' => [
                        'pre_tags'  => ['<em>'],
                        'post_tags' => ['</em>'],
                        'fields'    => []
                    ]
                ],
            ];

            // 设置高亮字段
            foreach ($highlightFields as $field) {
                $params['body']['highlight']['fields'][$field] = new \stdClass();
            }

            $response = $this->client->search($params);
            return $this->formatSearchResults($response, true);
        } catch (\Exception $e) {
            Log::error('Search with highlight error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 模糊搜索
     * 
     * @param string $field 字段名
     * @param string $value 搜索值
     * @param int $from 起始位置
     * @param int $size 每页大小
     * @return array
     */
    public function fuzzySearch($field, $value, $from = 0, $size = 10)
    {
        try {
            $params = [
                'index' => $this->index,
                'body'  => [
                    'from'  => $from,
                    'size'  => $size,
                    'query' => [
                        'fuzzy' => [
                            $field => [
                                'value'     => $value,
                                'fuzziness' => 'AUTO'
                            ]
                        ]
                    ]
                ],
            ];

            $response = $this->client->search($params);
            return $this->formatSearchResults($response);
        } catch (\Exception $e) {
            Log::error('Fuzzy search error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 范围搜索
     * 
     * @param string $field 字段名
     * @param mixed $gte 大于等于
     * @param mixed $lte 小于等于
     * @param int $from 起始位置
     * @param int $size 每页大小
     * @return array
     */
    public function rangeSearch($field, $gte = null, $lte = null, $from = 0, $size = 10)
    {
        try {
            $range = [];

            if ($gte !== null) {
                $range['gte'] = $gte;
            }

            if ($lte !== null) {
                $range['lte'] = $lte;
            }

            $params = [
                'index' => $this->index,
                'body'  => [
                    'from'  => $from,
                    'size'  => $size,
                    'query' => [
                        'range' => [
                            $field => $range
                        ]
                    ]
                ],
            ];

            $response = $this->client->search($params);
            return $this->formatSearchResults($response);
        } catch (\Exception $e) {
            Log::error('Range search error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 聚合查询
     * 
     * @param array $aggregations 聚合配置
     * @return array
     */
    public function aggregate($aggregations)
    {
        try {
            $params = [
                'index' => $this->index,
                'body'  => [
                    'size' => 0, // 不返回文档，只返回聚合结果
                    'aggs' => $aggregations
                ],
            ];

            $response = $this->client->search($params);
            return $response['aggregations'];
        } catch (\Exception $e) {
            Log::error('Aggregation error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 格式化搜索结果
     * 
     * @param array $response 搜索响应
     * @param bool $includeHighlight 是否包含高亮信息
     * @return array
     */
    protected function formatSearchResults($response, $includeHighlight = false)
    {
        $total   = $response['hits']['total']['value'] ?? 0;
        $hits    = $response['hits']['hits'];
        $results = [];

        foreach ($hits as $hit) {
            $item           = $hit['_source'];
            $item['_id']    = $hit['_id'];
            $item['_score'] = $hit['_score'];

            if ($includeHighlight && isset($hit['highlight'])) {
                $item['highlight'] = $hit['highlight'];
            }

            $results[] = $item;
        }

        return [
            'total' => $total,
            'hits'  => $results
        ];
    }
}