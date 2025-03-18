<?php

namespace app\controller;

use support\Db;
use support\Request;
use Webman\RedisQueue\Client;
use Webman\RedisQueue\Redis;

class UserController
{
    public function hello(Request $request): \support\Response
    {
        $default_name = 'webman';
        // 从get请求里获得name参数，如果没有传递name参数则返回$default_name
        $name = $request->get('name', $default_name);
        // 向浏览器返回字符串
        return response('hello ' . $name);
    }

    public function db(Request $request): \support\Response
    {
        $default_uid = 2;
        $id          = $request->get('id', $default_uid);
        $name        = Db::table('user')->where('id', $id)->value('name');
        return response("hello $name");
    }

    /**
     * 测试消息延迟队列
     */
    public function queue(Request $request): \support\Response
    {
        // 队列名
        $queue = 'send-mail';
        // 数据，可以直接传数组，无需序列化
        $data = ['to' => 'tom@gmail.com', 'content' => time()];
        // 投递消息
        Redis::send($queue, $data);
        // 投递延迟消息，消息会在10秒后处理
        $res = Redis::send($queue, $data, 20);
        //添加到数据库
        $mail_data = [
            'data'     => json_encode($data),
            'type'     => 'mail',
            'add_time' => date('Y-m-d H:i:s', time())
        ];
        Db::table('message')->insert($mail_data);

        // 队列名
        $sms_queue = 'send-sms';
        // 数据，可以直接传数组，无需序列化
        $sms_data = ['to' => '13833711221', 'content' => time() . '短信'];
        // 投递消息
        Redis::send($sms_queue, $sms_data);
        // 投递延迟消息，消息会在10秒后处理
        $sms_res = Redis::send($sms_queue, $sms_data, 20);

        $sms_data = [
            'data'     => json_encode($sms_data),
            'type'     => 'sms',
            'add_time' => date('Y-m-d H:i:s', time())
        ];
        Db::table('message')->insert($sms_data);

        // 返回结果
        return response($res);
//        return response('redis queue test');
    }
}