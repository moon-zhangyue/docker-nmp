<?php
declare(strict_types=1);

namespace app\controller;

use think\facade\View;

/**
 * Swagger API文档控制器
 *
 * @OA\OpenApi(
 *     openapi="3.0.0"
 * )
 *
 * @OA\Info(
 *     title="ThinkPHP 8 API文档",
 *     version="1.0.0",
 *     description="ThinkPHP 8项目API接口文档",
 *     @OA\Contact(
 *         email="admin@example.com",
 *         name="API Support"
 *     ),
 *     @OA\License(
 *         name="Apache 2.0",
 *         url="http://www.apache.org/licenses/LICENSE-2.0.html"
 *     )
 * )
 *
 * @OA\Server(
 *     url="/",
 *     description="API服务器"
 * )
 *
 * @OA\Tag(
 *     name="Redis",
 *     description="Redis相关接口"
 * )
 *
 * @OA\Tag(
 *     name="Set",
 *     description="Redis Set类型操作接口"
 * )
 *
 * @OA\PathItem(
 *     path="/redis/set"
 * )
 *
 * @OA\PathItem(
 *     path="/redis/set/basic"
 * )
 *
 * @OA\PathItem(
 *     path="/redis/set/set-operations"
 * )
 *
 * @OA\PathItem(
 *     path="/redis/set/tag-system"
 * )
 *
 * @OA\PathItem(
 *     path="/redis/set/ip-access-control"
 * )
 *
 * @OA\PathItem(
 *     path="/redis/set/userfollows"
 * )
 *
 * @OA\PathItem(
 *     path="/redis/set/random-prize"
 * )
 *
 * @OA\PathItem(
 *     path="/redis/zset"
 * )
 *
 * @OA\PathItem(
 *     path="/redis/zset/basic"
 * )
 *
 * @OA\PathItem(
 *     path="/redis/zset/leaderboard"
 * )
 *
 * @OA\PathItem(
 *     path="/redis/zset/delayed-queue"
 * )
 *
 * @OA\PathItem(
 *     path="/redis/zset/weighted-search"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 */
class SwaggerController
{
    /**
     * 显示Swagger UI界面
     */
    public function index()
    {
        return View::fetch('swagger/index');
    }

    /**
     * 生成OpenAPI规范JSON
     */
    public function json()
    {
        // 直接返回一个简单但有效的OpenAPI文档
        $openApiDoc = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'ThinkPHP 8 API文档',
                'version' => '1.0.0',
                'description' => 'ThinkPHP 8项目API接口文档'
            ],
            'paths' => [
                '/redis/set' => [
                    'get' => [
                        'summary' => 'Redis Set演示页面',
                        'description' => '显示Redis Set类型的演示页面',
                        'tags' => ['Redis Set'],
                        'responses' => [
                            '200' => [
                                'description' => '成功返回页面'
                            ]
                        ]
                    ]
                ],
                '/redis/set/basic' => [
                    'get' => [
                        'summary' => 'Redis Set基本用法示例',
                        'description' => '演示Redis Set类型的基本操作，包括添加、删除、判断元素是否存在等',
                        'tags' => ['Redis Set'],
                        'responses' => [
                            '200' => [
                                'description' => '操作成功'
                            ]
                        ]
                    ]
                ],
                '/redis/set/set-operations' => [
                    'get' => [
                        'summary' => 'Redis Set集合运算示例',
                        'description' => '演示Redis Set类型的集合运算，包括并集、交集和差集操作',
                        'tags' => ['Redis Set'],
                        'responses' => [
                            '200' => [
                                'description' => '操作成功'
                            ]
                        ]
                    ]
                ],
                '/redis/set/tag-system' => [
                    'get' => [
                        'summary' => 'Redis Set实现标签系统',
                        'description' => '使用Redis Set实现标签系统，包括添加标签、移除标签、获取项目标签、查找带有特定标签的项目等',
                        'tags' => ['Redis Set'],
                        'responses' => [
                            '200' => [
                                'description' => '操作成功'
                            ]
                        ]
                    ]
                ],
                '/redis/set/ip-access-control' => [
                    'get' => [
                        'summary' => 'Redis Set实现IP黑白名单',
                        'description' => '使用Redis Set实现IP访问控制，包括黑名单和白名单管理',
                        'tags' => ['Redis Set'],
                        'responses' => [
                            '200' => [
                                'description' => '操作成功'
                            ]
                        ]
                    ]
                ],
                '/redis/set/userfollows' => [
                    'get' => [
                        'summary' => '用户关注关系操作',
                        'description' => '管理用户之间的关注关系，包括关注、取消关注、查询关注状态等',
                        'tags' => ['Redis Set'],
                        'responses' => [
                            '200' => [
                                'description' => '操作成功'
                            ]
                        ]
                    ]
                ],
                '/redis/set/random-prize' => [
                    'get' => [
                        'summary' => '随机抽奖功能',
                        'description' => '使用Redis Set实现随机抽奖功能，包括初始化奖品池、参与抽奖、抽奖和查看统计信息',
                        'tags' => ['Redis Set'],
                        'responses' => [
                            '200' => [
                                'description' => '操作成功'
                            ]
                        ]
                    ]
                ],
                '/redis/zset' => [
                    'get' => [
                        'summary' => 'Redis ZSet演示页面',
                        'description' => '显示Redis ZSet类型的演示页面',
                        'tags' => ['Redis ZSet'],
                        'responses' => [
                            '200' => [
                                'description' => '成功返回页面'
                            ]
                        ]
                    ]
                ],
                '/redis/zset/basic' => [
                    'get' => [
                        'summary' => 'Redis ZSet基本用法示例',
                        'description' => '演示Redis ZSet类型的基本操作，包括添加、删除、获取元素、排序等',
                        'tags' => ['Redis ZSet'],
                        'parameters' => [
                            [
                                'name' => 'key',
                                'in' => 'query',
                                'description' => 'Redis键名，默认为\'zset_demo_basic\'',
                                'required' => false,
                                'schema' => [
                                    'type' => 'string',
                                    'default' => 'zset_demo_basic'
                                ]
                            ]
                        ],
                        'responses' => [
                            '200' => [
                                'description' => '操作成功'
                            ]
                        ]
                    ]
                ],
                '/redis/zset/leaderboard' => [
                    'get' => [
                        'summary' => 'Redis ZSet实现排行榜',
                        'description' => '使用Redis ZSet实现排行榜功能，包括添加/更新分数、获取排名、查看前N名等',
                        'tags' => ['Redis ZSet'],
                        'parameters' => [
                            [
                                'name' => 'action',
                                'in' => 'query',
                                'description' => '操作类型：add(添加/更新分数)、increment(增加分数)、get_user_rank(获取用户排名)、get_top(获取前N名)、get_rank_range(获取指定排名范围)、get_neighbors(获取用户附近排名)、clear(清空排行榜)、view(查看排行榜)',
                                'required' => false,
                                'schema' => [
                                    'type' => 'string',
                                    'default' => 'view'
                                ]
                            ],
                            [
                                'name' => 'user_id',
                                'in' => 'query',
                                'description' => '用户ID',
                                'required' => false,
                                'schema' => [
                                    'type' => 'integer',
                                    'default' => 0
                                ]
                            ],
                            [
                                'name' => 'score',
                                'in' => 'query',
                                'description' => '分数',
                                'required' => false,
                                'schema' => [
                                    'type' => 'number',
                                    'default' => 0
                                ]
                            ],
                            [
                                'name' => 'limit',
                                'in' => 'query',
                                'description' => '获取数量限制',
                                'required' => false,
                                'schema' => [
                                    'type' => 'integer',
                                    'default' => 10
                                ]
                            ],
                            [
                                'name' => 'start',
                                'in' => 'query',
                                'description' => '起始排名（从0开始）',
                                'required' => false,
                                'schema' => [
                                    'type' => 'integer',
                                    'default' => 0
                                ]
                            ],
                            [
                                'name' => 'end',
                                'in' => 'query',
                                'description' => '结束排名',
                                'required' => false,
                                'schema' => [
                                    'type' => 'integer',
                                    'default' => 9
                                ]
                            ],
                            [
                                'name' => 'count',
                                'in' => 'query',
                                'description' => '获取邻近用户的数量',
                                'required' => false,
                                'schema' => [
                                    'type' => 'integer',
                                    'default' => 2
                                ]
                            ]
                        ],
                        'responses' => [
                            '200' => [
                                'description' => '操作成功'
                            ]
                        ]
                    ]
                ],
                '/redis/zset/delayed-queue' => [
                    'get' => [
                        'summary' => 'Redis ZSet实现延迟队列',
                        'description' => '使用Redis ZSet实现延迟队列功能，包括添加延迟任务、获取已到期任务、处理任务等',
                        'tags' => ['Redis ZSet'],
                        'parameters' => [
                            [
                                'name' => 'action',
                                'in' => 'query',
                                'description' => '操作类型：add(添加任务)、get_ready(获取已到期任务)、process_one(处理一个任务)、remove(移除指定任务)、clear(清空队列)、stats(队列统计信息)',
                                'required' => false,
                                'schema' => [
                                    'type' => 'string',
                                    'default' => 'stats'
                                ]
                            ],
                            [
                                'name' => 'id',
                                'in' => 'query',
                                'description' => '任务ID',
                                'required' => false,
                                'schema' => [
                                    'type' => 'string'
                                ]
                            ],
                            [
                                'name' => 'delay',
                                'in' => 'query',
                                'description' => '延迟时间（秒）',
                                'required' => false,
                                'schema' => [
                                    'type' => 'integer',
                                    'default' => 60
                                ]
                            ],
                            [
                                'name' => 'payload',
                                'in' => 'query',
                                'description' => '任务内容',
                                'required' => false,
                                'schema' => [
                                    'type' => 'string'
                                ]
                            ]
                        ],
                        'responses' => [
                            '200' => [
                                'description' => '操作成功'
                            ]
                        ]
                    ]
                ],
                '/redis/zset/weighted-search' => [
                    'get' => [
                        'summary' => 'Redis ZSet实现权重搜索',
                        'description' => '使用Redis ZSet实现带权重的搜索功能，包括建立索引和搜索操作',
                        'tags' => ['Redis ZSet'],
                        'parameters' => [
                            [
                                'name' => 'action',
                                'in' => 'query',
                                'description' => '操作类型：index(建立索引)、search(执行搜索)',
                                'required' => false,
                                'schema' => [
                                    'type' => 'string',
                                    'default' => 'search'
                                ]
                            ],
                            [
                                'name' => 'keyword',
                                'in' => 'query',
                                'description' => '搜索关键词',
                                'required' => false,
                                'schema' => [
                                    'type' => 'string'
                                ]
                            ],
                            [
                                'name' => 'limit',
                                'in' => 'query',
                                'description' => '结果数量限制',
                                'required' => false,
                                'schema' => [
                                    'type' => 'integer',
                                    'default' => 5
                                ]
                            ]
                        ],
                        'responses' => [
                            '200' => [
                                'description' => '操作成功'
                            ]
                        ]
                    ]
                ]
            ],
            'tags' => [
                [
                    'name' => 'Redis',
                    'description' => 'Redis相关接口'
                ],
                [
                    'name' => 'Redis Set',
                    'description' => 'Redis Set类型操作接口'
                ],
                [
                    'name' => 'Redis ZSet',
                    'description' => 'Redis ZSet(有序集合)类型操作接口'
                ]
            ]
        ];

        // 设置响应头，确保正确的Content-Type
        return json($openApiDoc)->header(['Content-Type' => 'application/json']);
    }

    /**
     * 生成OpenAPI规范YAML
     */
    public function yaml()
    {
        // 获取与JSON方法相同的数据
        $openApiDoc = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'ThinkPHP 8 API文档',
                'version' => '1.0.0',
                'description' => 'ThinkPHP 8项目API接口文档'
            ],
            'paths' => [
                '/redis/set' => [
                    'get' => [
                        'summary' => 'Redis Set演示页面',
                        'description' => '显示Redis Set类型的演示页面',
                        'tags' => ['Redis Set'],
                        'responses' => [
                            '200' => [
                                'description' => '成功返回页面'
                            ]
                        ]
                    ]
                ],
                '/redis/set/basic' => [
                    'get' => [
                        'summary' => 'Redis Set基本用法示例',
                        'description' => '演示Redis Set类型的基本操作，包括添加、删除、判断元素是否存在等',
                        'tags' => ['Redis Set'],
                        'responses' => [
                            '200' => [
                                'description' => '操作成功'
                            ]
                        ]
                    ]
                ],
                '/redis/set/set-operations' => [
                    'get' => [
                        'summary' => 'Redis Set集合运算示例',
                        'description' => '演示Redis Set类型的集合运算，包括并集、交集和差集操作',
                        'tags' => ['Redis Set'],
                        'responses' => [
                            '200' => [
                                'description' => '操作成功'
                            ]
                        ]
                    ]
                ],
                '/redis/set/tag-system' => [
                    'get' => [
                        'summary' => 'Redis Set实现标签系统',
                        'description' => '使用Redis Set实现标签系统，包括添加标签、移除标签、获取项目标签、查找带有特定标签的项目等',
                        'tags' => ['Redis Set'],
                        'responses' => [
                            '200' => [
                                'description' => '操作成功'
                            ]
                        ]
                    ]
                ],
                '/redis/set/ip-access-control' => [
                    'get' => [
                        'summary' => 'Redis Set实现IP黑白名单',
                        'description' => '使用Redis Set实现IP访问控制，包括黑名单和白名单管理',
                        'tags' => ['Redis Set'],
                        'responses' => [
                            '200' => [
                                'description' => '操作成功'
                            ]
                        ]
                    ]
                ],
                '/redis/set/userfollows' => [
                    'get' => [
                        'summary' => '用户关注关系操作',
                        'description' => '管理用户之间的关注关系，包括关注、取消关注、查询关注状态等',
                        'tags' => ['Redis Set'],
                        'responses' => [
                            '200' => [
                                'description' => '操作成功'
                            ]
                        ]
                    ]
                ],
                '/redis/set/random-prize' => [
                    'get' => [
                        'summary' => '随机抽奖功能',
                        'description' => '使用Redis Set实现随机抽奖功能，包括初始化奖品池、参与抽奖、抽奖和查看统计信息',
                        'tags' => ['Redis Set'],
                        'responses' => [
                            '200' => [
                                'description' => '操作成功'
                            ]
                        ]
                    ]
                ]
            ],
            'tags' => [
                [
                    'name' => 'Redis',
                    'description' => 'Redis相关接口'
                ],
                [
                    'name' => 'Redis Set',
                    'description' => 'Redis Set类型操作接口'
                ],
                [
                    'name' => 'Redis ZSet',
                    'description' => 'Redis ZSet(有序集合)类型操作接口'
                ]
            ]
        ];

        // 设置响应头为YAML
        header('Content-Type: application/x-yaml');

        // 手动构建YAML
        $yaml = "openapi: 3.0.0\n";
        $yaml .= "info:\n";
        $yaml .= "  title: 'ThinkPHP 8 API文档'\n";
        $yaml .= "  version: '1.0.0'\n";
        $yaml .= "  description: 'ThinkPHP 8项目API接口文档'\n";
        $yaml .= "paths:\n";
        $yaml .= "  /redis/set:\n";
        $yaml .= "    get:\n";
        $yaml .= "      summary: 'Redis Set演示页面'\n";
        $yaml .= "      description: '显示Redis Set类型的演示页面'\n";
        $yaml .= "      tags:\n";
        $yaml .= "        - 'Redis Set'\n";
        $yaml .= "      responses:\n";
        $yaml .= "        '200':\n";
        $yaml .= "          description: '成功返回页面'\n";
        $yaml .= "  /redis/set/basic:\n";
        $yaml .= "    get:\n";
        $yaml .= "      summary: 'Redis Set基本用法示例'\n";
        $yaml .= "      description: '演示Redis Set类型的基本操作，包括添加、删除、判断元素是否存在等'\n";
        $yaml .= "      tags:\n";
        $yaml .= "        - 'Redis Set'\n";
        $yaml .= "      responses:\n";
        $yaml .= "        '200':\n";
        $yaml .= "          description: '操作成功'\n";
        $yaml .= "  /redis/set/set-operations:\n";
        $yaml .= "    get:\n";
        $yaml .= "      summary: 'Redis Set集合运算示例'\n";
        $yaml .= "      description: '演示Redis Set类型的集合运算，包括并集、交集和差集操作'\n";
        $yaml .= "      tags:\n";
        $yaml .= "        - 'Redis Set'\n";
        $yaml .= "      responses:\n";
        $yaml .= "        '200':\n";
        $yaml .= "          description: '操作成功'\n";
        $yaml .= "  /redis/set/tag-system:\n";
        $yaml .= "    get:\n";
        $yaml .= "      summary: 'Redis Set实现标签系统'\n";
        $yaml .= "      description: '使用Redis Set实现标签系统，包括添加标签、移除标签、获取项目标签、查找带有特定标签的项目等'\n";
        $yaml .= "      tags:\n";
        $yaml .= "        - 'Redis Set'\n";
        $yaml .= "      responses:\n";
        $yaml .= "        '200':\n";
        $yaml .= "          description: '操作成功'\n";
        $yaml .= "  /redis/set/ip-access-control:\n";
        $yaml .= "    get:\n";
        $yaml .= "      summary: 'Redis Set实现IP黑白名单'\n";
        $yaml .= "      description: '使用Redis Set实现IP访问控制，包括黑名单和白名单管理'\n";
        $yaml .= "      tags:\n";
        $yaml .= "        - 'Redis Set'\n";
        $yaml .= "      responses:\n";
        $yaml .= "        '200':\n";
        $yaml .= "          description: '操作成功'\n";
        $yaml .= "  /redis/set/userfollows:\n";
        $yaml .= "    get:\n";
        $yaml .= "      summary: '用户关注关系操作'\n";
        $yaml .= "      description: '管理用户之间的关注关系，包括关注、取消关注、查询关注状态等'\n";
        $yaml .= "      tags:\n";
        $yaml .= "        - 'Redis Set'\n";
        $yaml .= "      responses:\n";
        $yaml .= "        '200':\n";
        $yaml .= "          description: '操作成功'\n";
        $yaml .= "  /redis/set/random-prize:\n";
        $yaml .= "    get:\n";
        $yaml .= "      summary: '随机抽奖功能'\n";
        $yaml .= "      description: '使用Redis Set实现随机抽奖功能，包括初始化奖品池、参与抽奖、抽奖和查看统计信息'\n";
        $yaml .= "      tags:\n";
        $yaml .= "        - 'Redis Set'\n";
        $yaml .= "      responses:\n";
        $yaml .= "        '200':\n";
        $yaml .= "          description: '操作成功'\n";
        $yaml .= "  /redis/zset:\n";
        $yaml .= "    get:\n";
        $yaml .= "      summary: 'Redis ZSet演示页面'\n";
        $yaml .= "      description: '显示Redis ZSet类型的演示页面'\n";
        $yaml .= "      tags:\n";
        $yaml .= "        - 'Redis ZSet'\n";
        $yaml .= "      responses:\n";
        $yaml .= "        '200':\n";
        $yaml .= "          description: '成功返回页面'\n";
        $yaml .= "  /redis/zset/basic:\n";
        $yaml .= "    get:\n";
        $yaml .= "      summary: 'Redis ZSet基本用法示例'\n";
        $yaml .= "      description: '演示Redis ZSet类型的基本操作，包括添加、删除、获取元素、排序等'\n";
        $yaml .= "      tags:\n";
        $yaml .= "        - 'Redis ZSet'\n";
        $yaml .= "      parameters:\n";
        $yaml .= "        - name: key\n";
        $yaml .= "          in: query\n";
        $yaml .= "          description: 'Redis键名，默认为''zset_demo_basic'''\n";
        $yaml .= "          required: false\n";
        $yaml .= "          schema:\n";
        $yaml .= "            type: string\n";
        $yaml .= "            default: zset_demo_basic\n";
        $yaml .= "      responses:\n";
        $yaml .= "        '200':\n";
        $yaml .= "          description: '操作成功'\n";
        $yaml .= "  /redis/zset/leaderboard:\n";
        $yaml .= "    get:\n";
        $yaml .= "      summary: 'Redis ZSet实现排行榜'\n";
        $yaml .= "      description: '使用Redis ZSet实现排行榜功能，包括添加/更新分数、获取排名、查看前N名等'\n";
        $yaml .= "      tags:\n";
        $yaml .= "        - 'Redis ZSet'\n";
        $yaml .= "      parameters:\n";
        $yaml .= "        - name: action\n";
        $yaml .= "          in: query\n";
        $yaml .= "          description: '操作类型：add(添加/更新分数)、increment(增加分数)、get_user_rank(获取用户排名)、get_top(获取前N名)、get_rank_range(获取指定排名范围)、get_neighbors(获取用户附近排名)、clear(清空排行榜)、view(查看排行榜)'\n";
        $yaml .= "          required: false\n";
        $yaml .= "          schema:\n";
        $yaml .= "            type: string\n";
        $yaml .= "            default: view\n";
        $yaml .= "        - name: user_id\n";
        $yaml .= "          in: query\n";
        $yaml .= "          description: '用户ID'\n";
        $yaml .= "          required: false\n";
        $yaml .= "          schema:\n";
        $yaml .= "            type: integer\n";
        $yaml .= "            default: 0\n";
        $yaml .= "        - name: score\n";
        $yaml .= "          in: query\n";
        $yaml .= "          description: '分数'\n";
        $yaml .= "          required: false\n";
        $yaml .= "          schema:\n";
        $yaml .= "            type: number\n";
        $yaml .= "            default: 0\n";
        $yaml .= "        - name: limit\n";
        $yaml .= "          in: query\n";
        $yaml .= "          description: '获取数量限制'\n";
        $yaml .= "          required: false\n";
        $yaml .= "          schema:\n";
        $yaml .= "            type: integer\n";
        $yaml .= "            default: 10\n";
        $yaml .= "        - name: start\n";
        $yaml .= "          in: query\n";
        $yaml .= "          description: '起始排名（从0开始）'\n";
        $yaml .= "          required: false\n";
        $yaml .= "          schema:\n";
        $yaml .= "            type: integer\n";
        $yaml .= "            default: 0\n";
        $yaml .= "        - name: end\n";
        $yaml .= "          in: query\n";
        $yaml .= "          description: '结束排名'\n";
        $yaml .= "          required: false\n";
        $yaml .= "          schema:\n";
        $yaml .= "            type: integer\n";
        $yaml .= "            default: 9\n";
        $yaml .= "        - name: count\n";
        $yaml .= "          in: query\n";
        $yaml .= "          description: '获取邻近用户的数量'\n";
        $yaml .= "          required: false\n";
        $yaml .= "          schema:\n";
        $yaml .= "            type: integer\n";
        $yaml .= "            default: 2\n";
        $yaml .= "      responses:\n";
        $yaml .= "        '200':\n";
        $yaml .= "          description: '操作成功'\n";
        $yaml .= "  /redis/zset/delayed-queue:\n";
        $yaml .= "    get:\n";
        $yaml .= "      summary: 'Redis ZSet实现延迟队列'\n";
        $yaml .= "      description: '使用Redis ZSet实现延迟队列功能，包括添加延迟任务、获取已到期任务、处理任务等'\n";
        $yaml .= "      tags:\n";
        $yaml .= "        - 'Redis ZSet'\n";
        $yaml .= "      parameters:\n";
        $yaml .= "        - name: action\n";
        $yaml .= "          in: query\n";
        $yaml .= "          description: '操作类型：add(添加任务)、get_ready(获取已到期任务)、process_one(处理一个任务)、remove(移除指定任务)、clear(清空队列)、stats(队列统计信息)'\n";
        $yaml .= "          required: false\n";
        $yaml .= "          schema:\n";
        $yaml .= "            type: string\n";
        $yaml .= "            default: stats\n";
        $yaml .= "        - name: id\n";
        $yaml .= "          in: query\n";
        $yaml .= "          description: '任务ID'\n";
        $yaml .= "          required: false\n";
        $yaml .= "          schema:\n";
        $yaml .= "            type: string\n";
        $yaml .= "        - name: delay\n";
        $yaml .= "          in: query\n";
        $yaml .= "          description: '延迟时间（秒）'\n";
        $yaml .= "          required: false\n";
        $yaml .= "          schema:\n";
        $yaml .= "            type: integer\n";
        $yaml .= "            default: 60\n";
        $yaml .= "        - name: payload\n";
        $yaml .= "          in: query\n";
        $yaml .= "          description: '任务内容'\n";
        $yaml .= "          required: false\n";
        $yaml .= "          schema:\n";
        $yaml .= "            type: string\n";
        $yaml .= "      responses:\n";
        $yaml .= "        '200':\n";
        $yaml .= "          description: '操作成功'\n";
        $yaml .= "  /redis/zset/weighted-search:\n";
        $yaml .= "    get:\n";
        $yaml .= "      summary: 'Redis ZSet实现权重搜索'\n";
        $yaml .= "      description: '使用Redis ZSet实现带权重的搜索功能，包括建立索引和搜索操作'\n";
        $yaml .= "      tags:\n";
        $yaml .= "        - 'Redis ZSet'\n";
        $yaml .= "      parameters:\n";
        $yaml .= "        - name: action\n";
        $yaml .= "          in: query\n";
        $yaml .= "          description: '操作类型：index(建立索引)、search(执行搜索)'\n";
        $yaml .= "          required: false\n";
        $yaml .= "          schema:\n";
        $yaml .= "            type: string\n";
        $yaml .= "            default: search\n";
        $yaml .= "        - name: keyword\n";
        $yaml .= "          in: query\n";
        $yaml .= "          description: '搜索关键词'\n";
        $yaml .= "          required: false\n";
        $yaml .= "          schema:\n";
        $yaml .= "            type: string\n";
        $yaml .= "        - name: limit\n";
        $yaml .= "          in: query\n";
        $yaml .= "          description: '结果数量限制'\n";
        $yaml .= "          required: false\n";
        $yaml .= "          schema:\n";
        $yaml .= "            type: integer\n";
        $yaml .= "            default: 5\n";
        $yaml .= "      responses:\n";
        $yaml .= "        '200':\n";
        $yaml .= "          description: '操作成功'\n";
        $yaml .= "tags:\n";
        $yaml .= "  - name: 'Redis'\n";
        $yaml .= "    description: 'Redis相关接口'\n";
        $yaml .= "  - name: 'Redis Set'\n";
        $yaml .= "    description: 'Redis Set类型操作接口'\n";
        $yaml .= "  - name: 'Redis ZSet'\n";
        $yaml .= "    description: 'Redis ZSet(有序集合)类型操作接口'\n";

        return $yaml;
    }
}
