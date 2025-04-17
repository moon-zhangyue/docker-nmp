<?php

namespace app\controller;

use app\BaseController;
use app\service\UserService;
use think\facade\Log;
use think\Request;
use think\facade\Queue;
use think\response\Json;  // 添加这行引入
use app\service\ElasticsearchService;
use app\model\User as UserModel;

class User extends BaseController
{
    private $userService;

    public function __construct()
    {
        // parent::__construct();
        $this->userService = new UserService();
    }

    /**
     * 用户注册接口
     */
    public function register(Request $request)
    {
        try {
            $data = $request->post();

            // 调用注册服务
            $this->userService->register($data);

            return json([
                'code' => 0,
                'msg'  => 'Registration request received successfully',
                'data' => null
            ]);
        } catch (\Exception $e) {
            Log::error('Registration error: {message}', ['message' => $e->getMessage()]);

            return json([
                'code' => 1,
                'msg'  => $e->getMessage(),
                'data' => null
            ]);
        }
    }

    /**
     * 测试redis队列
     */
    public function redis_queue(Request $request)
    {
        try {
            $data = ['order_id' => random_int('10000', '99999'), 'user_id' => random_int('10000', '99999')];

            // 推送任务到 Redis 队列
            $res = Queue::push('app\job\RedisTask', $data);

            return json([
                'code' => 200,
                'msg'  => 'redis_queue push success!',
                'data' => $res
            ]);
        } catch (\Exception $e) {
            Log::error('redis_queue push error: {message}', ['message' => $e->getMessage()]);

            return json([
                'code' => 1,
                'msg'  => $e->getMessage(),
                'data' => null
            ]);
        }
    }
    /**
     * Kafka消息队列测试
     */
    public function kafka_queue(Request $request)
    {
        try {
            $data = [
                'user_id'   => random_int(10000, 99999),
                'message'   => 'Test Kafka message',
                'timestamp' => time()
            ];

            // 推送任务到Kafka队列
            $isSuccess = Queue::push('app\job\KafkaTask', $data, 'kafka');

            if ($isSuccess) {
                return json([
                    'code' => 0,
                    'msg'  => 'Message pushed to Kafka queue successfully',
                    'data' => [
                        'success' => $isSuccess,
                        'data'    => $data
                    ]
                ]);
            } else {
                Log::error('Kafka queue push error');

                return json([
                    'code' => 1,
                    'msg'  => 'Kafka queue push error',
                    'data' => null
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Kafka queue push error: {message}', ['message' => $e->getMessage()]);

            return json([
                'code' => 1,
                'msg'  => $e->getMessage(),
                'data' => null
            ]);
        }
    }

    /* es-head在windows环境中 */
    /**
     * 用户搜索接口
     * 
     * @param Request $request
     * @return Json
     */
    public function search(Request $request)
    {
        try {
            $query = $request->param('query');
            $page  = $request->param('page', 1);
            $size  = $request->param('size', 10);
            $from  = ($page - 1) * $size;

            $esService = new ElasticsearchService();
            $result    = $esService->search($query, ['username', 'email', 'phone', 'country'], $from, $size);

            return json([
                'code' => 0,
                'msg'  => 'Search successful',
                'data' => [
                    'total' => $result['total'],
                    'users' => $result['hits'],
                    'page'  => $page,
                    'size'  => $size
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Elasticsearch search error: {message}', ['message' => $e->getMessage()]);

            return json([
                'code' => 1,
                'msg'  => '搜索失败: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * 按年龄范围搜索用户
     * 
     * @param Request $request
     * @return Json
     */
    public function searchByAge(Request $request)
    {
        try {
            $minAge = $request->param('min_age', 18);
            $maxAge = $request->param('max_age', 30);
            $page   = $request->param('page', 1);
            $size   = $request->param('size', 10);
            $from   = ($page - 1) * $size;

            $esService = new ElasticsearchService();
            $result    = $esService->rangeSearch('age', $minAge, $maxAge, $from, $size);

            return json([
                'code' => 0,
                'msg'  => 'Search by age successful',
                'data' => [
                    'total' => $result['total'],
                    'users' => $result['hits'],
                    'page'  => $page,
                    'size'  => $size
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Elasticsearch range query error: {message}', ['message' => $e->getMessage()]);

            return json([
                'code' => 1,
                'msg'  => '按年龄查询失败: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * 按国家聚合用户数量
     * 
     * @param Request $request
     * @return Json
     */
    public function aggregateByCountry(Request $request)
    {
        try {
            $esService    = new ElasticsearchService();
            $aggregations = [
                'countries' => [
                    'terms' => [
                        'field' => 'country',
                        'size'  => $request->param('size', 10)
                    ]
                ]
            ];

            $result = $esService->aggregate($aggregations);

            return json([
                'code' => 0,
                'msg'  => 'Aggregation successful',
                'data' => $result['countries']['buckets']
            ]);
        } catch (\Exception $e) {
            Log::error('Elasticsearch aggregation error: {message}', ['message' => $e->getMessage()]);

            return json([
                'code' => 1,
                'msg'  => '聚合查询失败: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * 批量索引用户数据
     * 
     * @param array $users 用户数据数组
     * @return Json
     */
    public function bulkIndexUsers($users = null)
    {
        try {
            // 如果没有传入用户数据，尝试从请求中获取
            if ($users === null) {
                $users = request()->post('users');
                if (empty($users)) {
                    return json([
                        'code' => 1,
                        'msg'  => '没有提供用户数据',
                        'data' => null
                    ]);
                }
            }

            $esService = new ElasticsearchService();
            $result    = $esService->bulkIndex($users);

            // 检查是否有错误
            if (isset($result['errors']) && $result['errors']) {
                $errorItems = [];
                foreach ($result['items'] as $item) {
                    if (isset($item['index']['error'])) {
                        $errorItems[] = $item['index']['error'];
                    }
                }

                Log::warning('Elasticsearch bulk index has errors', ['errors' => $errorItems]);

                return json([
                    'code' => 1,
                    'msg'  => '部分数据索引失败',
                    'data' => ['errors' => $errorItems]
                ]);
            }

            return json([
                'code' => 0,
                'msg'  => '批量索引成功',
                'data' => [
                    'took'        => $result['took'],
                    'items_count' => count($result['items'])
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Elasticsearch bulk index error: {message}', ['message' => $e->getMessage()]);

            return json([
                'code' => 1,
                'msg'  => '批量索引失败: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * 带高亮的搜索功能
     * 
     * @param Request $request
     * @return Json
     */
    public function searchWithHighlight(Request $request)
    {
        try {
            $query = $request->param('query');
            $page  = $request->param('page', 1);
            $size  = $request->param('size', 10);
            $from  = ($page - 1) * $size;

            $esService = new ElasticsearchService();
            $result    = $esService->searchWithHighlight(
                $query,
                ['username', 'email', 'country'],
                ['username', 'email'],
                $from,
                $size
            );

            return json([
                'code' => 0,
                'msg'  => 'Search with highlight successful',
                'data' => [
                    'total' => $result['total'],
                    'users' => $result['hits'],
                    'page'  => $page,
                    'size'  => $size
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Elasticsearch highlight search error: {message}', ['message' => $e->getMessage()]);

            return json([
                'code' => 1,
                'msg'  => '高亮搜索失败: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * 模糊搜索功能
     * 
     * @param Request $request
     * @return Json
     */
    public function fuzzySearch(Request $request)
    {
        try {
            $field = $request->param('field', 'username');
            $value = $request->param('value');
            $page  = (int) $request->param('page', 1);
            $size  = (int) $request->param('size', 10);
            $from  = ($page - 1) * $size;

            if (empty($value)) {
                return json([
                    'code' => 1,
                    'msg'  => '搜索值不能为空',
                    'data' => null
                ]);
            }

            $esService = new ElasticsearchService();
            $result    = $esService->fuzzySearch($field, $value, $from, $size);

            return json([
                'code' => 0,
                'msg'  => 'Fuzzy search successful',
                'data' => [
                    'total' => $result['total'],
                    'users' => $result['hits'],
                    'page'  => $page,
                    'size'  => $size
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Elasticsearch fuzzy search error: {message}', ['message' => $e->getMessage()]);

            return json([
                'code' => 1,
                'msg'  => '模糊搜索失败: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * 创建或更新用户索引
     * 
     * @param Request $request
     * @return Json
     */
    public function indexUser(Request $request)
    {
        try {
            $userData = $request->post();
            $userId   = $request->param('id');

            if (empty($userData)) {
                return json([
                    'code' => 1,
                    'msg'  => '用户数据不能为空',
                    'data' => null
                ]);
            }

            // 验证必要字段
            if (empty($userData['username'])) {
                return json([
                    'code' => 1,
                    'msg'  => '用户名不能为空',
                    'data' => null
                ]);
            }

            // 规范化文档结构，确保与importUsersToEs接口使用相同的字段格式
            $document = [
                'username' => $userData['username'],
                'email'    => $userData['email'] ?? '',
                'phone'    => $userData['phone'] ?? '',
                'country'  => $userData['country'] ?? ''
            ];

            // 添加其他可能的字段
            if (!empty($userData['age'])) {
                $document['age'] = (int) $userData['age'];
            }

            // 确保不包含id字段，避免与文档ID冲突
            if (isset($document['id'])) {
                unset($document['id']);
            }

            $esService = new ElasticsearchService();
            $result    = $esService->indexDocument($document, $userId);

            return json([
                'code' => 0,
                'msg'  => '用户索引成功',
                'data' => [
                    'id'     => $result['_id'],
                    'result' => $result['result']
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Elasticsearch index user error: {message}', ['message' => $e->getMessage()]);

            return json([
                'code' => 1,
                'msg'  => '用户索引失败: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * 导入数据库用户数据到Elasticsearch
     * 
     * 将数据库中前2000条用户数据导入到Elasticsearch中
     * 只导入username、email、phone和country字段
     * 
     * @return Json
     */
    public function importUsersToEs()
    {
        try {
            // 从数据库获取前2000条用户数据
            $users = UserModel::limit(2000)
                ->field('id, username, email, phone, country')
                ->select()
                ->toArray();

            if (empty($users)) {
                return json([
                    'code' => 1,
                    'msg'  => '没有找到用户数据',
                    'data' => null
                ]);
            }

            // 准备要导入的数据
            $documents = [];
            foreach ($users as $user) {
                $document    = [
                    'id'       => $user['id'],
                    'username' => $user['username'],
                    'email'    => $user['email'],
                    'phone'    => $user['phone'] ?? '',
                    'country'  => $user['country'] ?? ''
                ];
                $documents[] = $document;
            }

            // 批量导入到Elasticsearch
            $esService = new ElasticsearchService();
            $result    = $esService->bulkIndex($documents);

            // 检查是否有错误
            if (isset($result['errors']) && $result['errors']) {
                $errorCount = 0;
                foreach ($result['items'] as $item) {
                    if (isset($item['index']['error'])) {
                        $errorCount++;
                    }
                }

                Log::warning('Elasticsearch bulk import has errors', ['error_count' => $errorCount]);

                return json([
                    'code' => 1,
                    'msg'  => '部分数据导入失败',
                    'data' => [
                        'total'         => count($users),
                        'error_count'   => $errorCount,
                        'success_count' => count($users) - $errorCount
                    ]
                ]);
            }

            return json([
                'code' => 0,
                'msg'  => '用户数据导入成功',
                'data' => [
                    'total'  => count($users),
                    'took'   => $result['took'],
                    'status' => 'success'
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Import users to Elasticsearch error: {message}', ['message' => $e->getMessage()]);

            return json([
                'code' => 1,
                'msg'  => '导入用户数据失败: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }
}
