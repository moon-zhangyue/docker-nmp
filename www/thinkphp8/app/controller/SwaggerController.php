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
 *     path="/redis/set/userfollows"
 * )
 *
 * @OA\PathItem(
 *     path="/redis/set/random-prize"
 * )
 *
 * @OA\PathItem(
 *     path="/redis/set/{path}"
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
                '/api/example' => [
                    'get' => [
                        'summary' => '示例API',
                        'responses' => [
                            '200' => [
                                'description' => '成功响应'
                            ]
                        ]
                    ]
                ],
                '/redis/set/userfollows' => [
                    'get' => [
                        'summary' => '用户关注关系操作',
                        'description' => '管理用户之间的关注关系',
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
                        'description' => '使用Redis Set实现随机抽奖功能',
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
                '/api/example' => [
                    'get' => [
                        'summary' => '示例API',
                        'responses' => [
                            '200' => [
                                'description' => '成功响应'
                            ]
                        ]
                    ]
                ],
                '/redis/set/userfollows' => [
                    'get' => [
                        'summary' => '用户关注关系操作',
                        'description' => '管理用户之间的关注关系',
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
                        'description' => '使用Redis Set实现随机抽奖功能',
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
        $yaml .= "  /api/example:\n";
        $yaml .= "    get:\n";
        $yaml .= "      summary: '示例API'\n";
        $yaml .= "      responses:\n";
        $yaml .= "        '200':\n";
        $yaml .= "          description: '成功响应'\n";
        $yaml .= "  /redis/set/userfollows:\n";
        $yaml .= "    get:\n";
        $yaml .= "      summary: '用户关注关系操作'\n";
        $yaml .= "      description: '管理用户之间的关注关系'\n";
        $yaml .= "      tags:\n";
        $yaml .= "        - 'Redis Set'\n";
        $yaml .= "      responses:\n";
        $yaml .= "        '200':\n";
        $yaml .= "          description: '操作成功'\n";
        $yaml .= "  /redis/set/random-prize:\n";
        $yaml .= "    get:\n";
        $yaml .= "      summary: '随机抽奖功能'\n";
        $yaml .= "      description: '使用Redis Set实现随机抽奖功能'\n";
        $yaml .= "      tags:\n";
        $yaml .= "        - 'Redis Set'\n";
        $yaml .= "      responses:\n";
        $yaml .= "        '200':\n";
        $yaml .= "          description: '操作成功'\n";
        $yaml .= "tags:\n";
        $yaml .= "  - name: 'Redis'\n";
        $yaml .= "    description: 'Redis相关接口'\n";
        $yaml .= "  - name: 'Redis Set'\n";
        $yaml .= "    description: 'Redis Set类型操作接口'\n";

        return $yaml;
    }
}
