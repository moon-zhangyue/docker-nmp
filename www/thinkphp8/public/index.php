<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2019 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

use think\App;

// [ 应用入口文件 ]

require __DIR__ . '/../vendor/autoload.php';

// 执行HTTP应用并响应
$http = (new App())->http;

$response = $http->run();

// 仅在浏览器访问时显示帮助信息
if (strpos($_SERVER['HTTP_ACCEPT'], 'text/html') !== false && empty($_SERVER['PATH_INFO'])) {
    // 获取域名和端口
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    echo '<html>
    <head>
        <title>ThinkPHP8 多租户队列演示</title>
        <style type="text/css">
            body {
                font-family: "Microsoft YaHei", sans-serif;
                margin: 0;
                padding: 20px;
                color: #333;
                background-color: #f9f9f9;
            }
            h1 {
                color: #1e88e5;
                border-bottom: 1px solid #ddd;
                padding-bottom: 10px;
            }
            h2 {
                color: #0d47a1;
                margin-top: 25px;
            }
            ul {
                list-style-type: none;
                padding-left: 20px;
            }
            li {
                margin-bottom: 10px;
                position: relative;
            }
            a {
                color: #1976d2;
                text-decoration: none;
            }
            a:hover {
                text-decoration: underline;
            }
            .endpoint {
                background-color: #fff;
                border: 1px solid #ddd;
                border-radius: 4px;
                padding: 10px;
                margin: 10px 0;
                font-family: Consolas, monospace;
            }
            .method {
                font-weight: bold;
                color: #4caf50;
            }
            .path {
                color: #f44336;
            }
            .button {
                display: inline-block;
                background-color: #1976d2;
                color: white;
                padding: 8px 12px;
                border-radius: 4px;
                margin-top: 10px;
                cursor: pointer;
            }
            .button:hover {
                background-color: #1565c0;
            }
            .code {
                background-color: #f5f5f5;
                border: 1px solid #ddd;
                padding: 10px;
                margin: 10px 0;
                font-family: Consolas, monospace;
                overflow-x: auto;
            }
            .section {
                margin-top: 30px;
                padding-top: 20px;
                border-top: 1px solid #eee;
            }
        </style>
    </head>
    <body>
        <h1>ThinkPHP8 多租户队列演示</h1>
        <p>这是ThinkPHP8多租户队列功能的演示应用，您可以通过以下接口测试多租户队列功能。</p>
        
        <h2>租户管理接口</h2>
        <ul>
            <li>
                <div class="endpoint">
                    <span class="method">GET</span> 
                    <span class="path">http://'.$host.'/tenant_queue_example/list_tenants</span>
                </div>
                <p>列出所有租户</p>
                <a href="http://'.$host.'/tenant_queue_example/list_tenants" class="button">点击测试</a>
            </li>
            <li>
                <div class="endpoint">
                    <span class="method">GET/POST</span> 
                    <span class="path">http://'.$host.'/tenant_queue_example/get_tenant_config?tenant_id=tenant-1</span>
                </div>
                <p>获取指定租户的配置</p>
                <a href="http://'.$host.'/tenant_queue_example/get_tenant_config?tenant_id=tenant-1" class="button">点击测试</a>
            </li>
        </ul>
        
        <h2>队列任务接口</h2>
        <ul>
            <li>
                <div class="endpoint">
                    <span class="method">POST</span> 
                    <span class="path">http://'.$host.'/tenant_queue_example/create_tenant_and_queue</span>
                </div>
                <p>创建租户并添加队列任务</p>
                <div class="code">
                参数:
                - tenant_id: 租户ID
                - job_data: 任务数据，例如：{"task_type":"process_data","data":{"key":"value"}}
                </div>
                <p>示例请求：</p>
                <div class="code">
                curl -X POST \\
                  http://'.$host.'/tenant_queue_example/create_tenant_and_queue \\
                  -H \'Content-Type: application/json\' \\
                  -d \'{
                    "tenant_id": "tenant-1",
                    "job_data": {
                      "task_type": "process_data",
                      "data": {"key": "value"}
                    }
                  }\'
                </div>
            </li>
            <li>
                <div class="endpoint">
                    <span class="method">POST</span> 
                    <span class="path">http://'.$host.'/tenant_queue_example/create_delayed_task</span>
                </div>
                <p>创建延迟队列任务</p>
                <div class="code">
                参数:
                - tenant_id: 租户ID
                - job_data: 任务数据
                - delay: 延迟秒数，默认60
                </div>
                <p>示例请求：</p>
                <div class="code">
                curl -X POST \\
                  http://'.$host.'/tenant_queue_example/create_delayed_task \\
                  -H \'Content-Type: application/json\' \\
                  -d \'{
                    "tenant_id": "tenant-1",
                    "job_data": {
                      "task_type": "send_notification",
                      "recipients": ["user1@example.com"],
                      "message": "测试通知"
                    },
                    "delay": 30
                  }\'
                </div>
            </li>
        </ul>
        
        <div class="section">
            <h2>Kafka队列接口</h2>
            <ul>
                <li>
                    <div class="endpoint">
                        <span class="method">POST</span> 
                        <span class="path">http://'.$host.'/kafka_queue/push</span>
                    </div>
                    <p>使用特定租户发送Kafka消息</p>
                    <div class="code">
                    参数:
                    - tenant_id: 租户ID，默认为"default"
                    - message: 消息内容，支持字符串、数组或对象
                    - topic: 主题名称，默认为"default"
                    - delay: 延迟秒数，默认0（立即执行）
                    - metadata: 消息元数据，可选
                    </div>
                    <p>示例请求：</p>
                    <div class="code">
                    curl -X POST \\
                      http://'.$host.'/kafka_queue/push \\
                      -H \'Content-Type: application/json\' \\
                      -d \'{
                        "tenant_id": "tenant-1",
                        "message": "这是一条Kafka测试消息",
                        "topic": "default",
                        "metadata": {
                          "source": "api",
                          "priority": "high"
                        }
                      }\'
                    </div>
                </li>
                <li>
                    <div class="endpoint">
                        <span class="method">POST</span> 
                        <span class="path">http://'.$host.'/kafka_queue/batch_push</span>
                    </div>
                    <p>批量发送Kafka消息</p>
                    <div class="code">
                    参数:
                    - tenant_id: 租户ID，默认为"default"
                    - messages: 消息数组，每个元素可以是字符串、数组或对象
                    - topic: 主题名称，默认为"default"
                    </div>
                    <p>示例请求：</p>
                    <div class="code">
                    curl -X POST \\
                      http://'.$host.'/kafka_queue/batch_push \\
                      -H \'Content-Type: application/json\' \\
                      -d \'{
                        "tenant_id": "tenant-1",
                        "messages": [
                          "消息1",
                          "消息2",
                          {"title": "消息3", "content": "这是第三条消息"}
                        ],
                        "topic": "default"
                      }\'
                    </div>
                </li>
            </ul>
        </div>
        
        <h2>处理队列任务</h2>
        <p>队列任务需要启动队列处理进程才能执行，在Docker容器中执行:</p>
        <div class="code">
        # 处理默认队列
        docker exec php82 sh -c "cd /www/thinkphp8 && php think queue:work"
        
        # 处理特定租户队列
        docker exec php82 sh -c "cd /www/thinkphp8 && php think queue:work --queue=租户ID.default"
        
        # 例如处理tenant-1租户队列
        docker exec php82 sh -c "cd /www/thinkphp8 && php think queue:work --queue=tenant-1.default"
        </div>
    </body>
</html>';
}

$response->send();

$http->end($response);
