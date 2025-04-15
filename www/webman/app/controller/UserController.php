<?php

namespace app\controller;

use app\service\UserService;
use support\Db;
use support\Request;
use Webman\RedisQueue\Client;
use Webman\RedisQueue\Redis;
use support\Redis as RedisServer;

/**
 * 用户控制器
 * 
 * 提供用户相关的API接口，包括注册、登录、查询和退出功能
 */
class UserController
{
    /**
     * 用户服务实例
     * 
     * @var UserService
     */
    protected $userService;

    /**
     * 构造函数，初始化用户服务
     */
    public function __construct()
    {
        $this->userService = new UserService();
    }

    /**
     * 用户注册接口
     * 
     * @param Request $request
     * @return \support\Response
     */
    public function register(Request $request): \support\Response
    {
        // 获取请求数据
        $data = $request->post();

        // 调用服务层处理注册逻辑
        $result = $this->userService->register($data);

        // 返回JSON响应
        return json($result);
    }

    /**
     * 用户登录接口
     * 
     * @param Request $request
     * @return \support\Response
     */
    public function login(Request $request): \support\Response
    {
        // 获取用户名和密码
        $username = $request->post('username');
        $password = $request->post('password');

        if (empty($username) || empty($password)) {
            return json(['code' => 400, 'msg' => '用户名和密码不能为空']);
        }

        // 调用服务层处理登录逻辑
        $result = $this->userService->login($username, $password, $request);

        // 返回JSON响应
        return json($result);
    }

    /**
     * 获取用户信息接口
     * 
     * @param Request $request
     * @return \support\Response
     */
    public function info(Request $request): \support\Response
    {
        // 从会话中获取用户ID
        $userId = $request->get('user_id');

        if (!$userId) {
            return json(['code' => 401, 'msg' => '请传递用户ID']);
        }

        // 调用服务层获取用户信息
        $result = $this->userService->getUserInfo($userId);

        // 返回JSON响应
        return json($result);
    }

    /**
     * 用户退出登录接口
     * 
     * @param Request $request
     * @return \support\Response
     */
    public function logout(Request $request): \support\Response
    {
        // 调用服务层处理退出逻辑
        $result = $this->userService->logout($request);

        // 返回JSON响应
        return json($result);
    }

    /**
     * 根据ID获取指定用户信息接口
     * 
     * @param Request $request
     * @return \support\Response
     */
    public function getUserById(Request $request): \support\Response
    {
        // 获取用户ID参数
        $userId = $request->get('id');

        if (!$userId || !is_numeric($userId)) {
            return json(['code' => 400, 'msg' => '用户ID无效']);
        }

        // 调用服务层获取用户信息
        $result = $this->userService->getUserInfo((int) $userId);

        // 返回JSON响应
        return json($result);
    }

    /**
     * 测试方法 - Hello World
     * 
     * @param Request $request
     * @return \support\Response
     */
    public function hello(Request $request): \support\Response
    {
        $default_name = 'webman';
        // 从get请求里获得name参数，如果没有传递name参数则返回$default_name
        $name = $request->get('name', $default_name);
        // 向浏览器返回字符串
        return response('hello ' . $name);
    }

    /**
     * 测试方法 - 数据库查询
     * 
     * @param Request $request
     * @return \support\Response
     */
    public function db(Request $request): \support\Response
    {
        $default_uid = 2;
        $id          = $request->get('id', $default_uid);
        $name        = Db::table('user')->where('id', $id)->value('name');
        return response("hello $name");
    }

    /**
     * 测试消息延迟队列
     * 
     * @param Request $request
     * @return \support\Response
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