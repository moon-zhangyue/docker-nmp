<?php
declare(strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\App;
use OpenApi\Generator;

class Swagger extends Command
{
    protected function configure()
    {
        $this->setName('swagger')
            ->addArgument('action', null, '操作类型：json或yaml', 'json')
            ->setDescription('生成Swagger API文档');
    }

    protected function execute(Input $input, Output $output)
    {
        $action = $input->getArgument('action');

        // 设置Swagger扫描目录
        $directories = [
            App::getRootPath() . 'app/controller',
            App::getRootPath() . 'app/controller/redis',
        ];

        try {
            // 创建一个基本的OpenAPI对象
            $openapi = new \OpenApi\Annotations\OpenApi([]);
            $openapi->openapi = '3.0.0'; // 明确设置OpenAPI版本

            // 添加基本信息
            $openapi->info = new \OpenApi\Annotations\Info([]);
            $openapi->info->title = 'ThinkPHP 8 API文档';
            $openapi->info->version = '1.0.0';
            $openapi->info->description = 'ThinkPHP 8项目API接口文档';

            // 添加服务器信息
            $openapi->servers = [
                new \OpenApi\Annotations\Server([
                    'url' => '/',
                    'description' => 'API服务器'
                ])
            ];

            // 添加一个基本路径，避免缺少PathItem错误
            $openapi->paths = [
                new \OpenApi\Annotations\PathItem([
                    'path' => '/api',
                    'get' => new \OpenApi\Annotations\Get([
                        'summary' => '示例API',
                        'responses' => [
                            new \OpenApi\Annotations\Response([
                                'response' => 200,
                                'description' => '成功响应'
                            ])
                        ]
                    ])
                ])
            ];

            // 尝试扫描目录并合并结果
            try {
                $scannedOpenapi = Generator::scan($directories);
                if (isset($scannedOpenapi->paths)) {
                    foreach ($scannedOpenapi->paths as $path) {
                        $openapi->paths[] = $path;
                    }
                }
                if (isset($scannedOpenapi->tags)) {
                    $openapi->tags = $scannedOpenapi->tags;
                }
                $output->writeln('<info>成功扫描API目录并合并结果</info>');
            } catch (\Exception $scanException) {
                $output->writeln('<comment>扫描目录时出现警告：' . $scanException->getMessage() . '</comment>');
                $output->writeln('<info>将使用基本OpenAPI结构</info>');
            }

            // 确保生成的文档包含版本字段
            if ($action === 'yaml') {
                // 生成YAML格式
                $yamlContent = "openapi: 3.0.0\n"; // 强制添加版本字段
                $yamlContent .= substr($openapi->toYaml(), strpos($openapi->toYaml(), "info:"));
                $output->writeln($yamlContent);
            } else {
                // 生成JSON格式
                $jsonArray = json_decode($openapi->toJson(), true);
                $jsonArray['openapi'] = '3.0.0'; // 强制设置版本字段
                $jsonContent = json_encode($jsonArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                $output->writeln($jsonContent);
            }

            $output->writeln('Swagger文档生成成功！');

        } catch (\Exception $e) {
            $output->writeln('<error>生成Swagger文档失败：' . $e->getMessage() . '</error>');
            return 1;
        }

        return 0;
    }
}
