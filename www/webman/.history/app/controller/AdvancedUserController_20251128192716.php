<?php

namespace app\controller;

use app\model\User;
use support\Request;
use support\Redis;
use Webman\Event\Event;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * 高级用户控制器
 * 
 * 演示Webman框架的各种功能特性：
 * 1. Redis缓存使用
 * 2. 数据库ORM操作
 * 3. 分页处理
 * 4. 事件系统使用
 * 5. 验证器使用
 */
class AdvancedUserController
{
    /**
     * 获取用户列表（带缓存和分页）
     * 
     * @param Request $request
     * @return \support\Response
     */
    public function index(Request $request): \support\Response
    {
        // 获取分页参数
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 10);
        
        // 生成缓存键
        $cacheKey = "user_list_page_{$page}_limit_{$limit}";
        
        // 尝试从缓存获取数据
        $cachedData = Redis::get($cacheKey);
        if ($cachedData) {
            $data = json_decode($cachedData, true);
            return json([
                'code' => 0,
                'msg' => 'success (from cache)',
                'data' => $data['items'],
                'pagination' => $data['pagination']
            ]);
        }
        
        // 从数据库获取数据
        $users = User::paginate($limit, ['*'], 'page', $page);
        
        $responseData = [
            'items' => $users->items(),
            'pagination' => [
                'total' => $users->total(),
                'per_page' => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
            ]
        ];
        
        // 将数据存入缓存，有效期60秒
        Redis::setex($cacheKey, 60, json_encode($responseData));
        
        return json([
            'code' => 0,
            'msg' => 'success',
            'data' => $responseData['items'],
            'pagination' => $responseData['pagination']
        ]);
    }
    
    /**
     * 创建用户
     * 
     * @param Request $request
     * @return \support\Response
     */
    public function create(Request $request): \support\Response
    {
        // 数据验证
        $validator = validator($request->post(), [
            'username' => 'required|string|max:50|unique:users',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'phone' => 'nullable|string|max:20',
        ], [
            'username.required' => '用户名不能为空',
            'username.unique' => '用户名已存在',
            'email.required' => '邮箱不能为空',
            'email.email' => '邮箱格式不正确',
            'email.unique' => '邮箱已存在',
            'password.required' => '密码不能为空',
            'password.min' => '密码至少6位',
            'password.confirmed' => '两次输入的密码不一致',
        ]);
        
        if ($validator->fails()) {
            return json([
                'code' => 1,
                'msg' => $validator->errors()->first()
            ]);
        }
        
        // 创建用户
        $user = User::create([
            'username' => $request->post('username'),
            'email' => $request->post('email'),
            'password' => password_hash($request->post('password'), PASSWORD_DEFAULT),
            'phone' => $request->post('phone', ''),
            'status' => 1, // 默认启用状态
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        // 清除用户列表缓存
        $this->clearUserListCache();
        
        // 触发用户注册事件
        Event::emit('user.registered', ['user' => $user]);
        
        // 发送注册通知到队列
        \Webman\RedisQueue\Client::send('user-register-notify', [
            'user_id' => $user->id,
            'username' => $user->username,
            'email' => $user->email
        ]);
        
        return json([
            'code' => 0,
            'msg' => '用户创建成功',
            'data' => $user
        ]);
    }
    
    /**
     * 更新用户信息
     * 
     * @param Request $request
     * @param int $id
     * @return \support\Response
     */
    public function update(Request $request, int $id): \support\Response
    {
        // 查找用户
        $user = User::find($id);
        if (!$user) {
            return json([
                'code' => 1,
                'msg' => '用户不存在'
            ]);
        }
        
        // 数据验证
        $validator = validator($request->post(), [
            'username' => 'sometimes|string|max:50|unique:users,username,' . $id,
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'phone' => 'nullable|string|max:20',
        ]);
        
        if ($validator->fails()) {
            return json([
                'code' => 1,
                'msg' => $validator->errors()->first()
            ]);
        }
        
        // 更新用户信息
        $user->fill($request->post());
        $user->updated_at = date('Y-m-d H:i:s');
        $user->save();
        
        // 清除相关缓存
        $this->clearUserListCache();
        Redis::del("user_{$id}");
        
        return json([
            'code' => 0,
            'msg' => '用户信息更新成功',
            'data' => $user
        ]);
    }
    
    /**
     * 删除用户
     * 
     * @param Request $request
     * @param int $id
     * @return \support\Response
     */
    public function delete(Request $request, int $id): \support\Response
    {
        // 查找用户
        $user = User::find($id);
        if (!$user) {
            return json([
                'code' => 1,
                'msg' => '用户不存在'
            ]);
        }
        
        // 删除用户
        $user->delete();
        
        // 清除相关缓存
        $this->clearUserListCache();
        Redis::del("user_{$id}");
        
        return json([
            'code' => 0,
            'msg' => '用户删除成功'
        ]);
    }
    
    /**
     * 获取用户详情（带缓存）
     * 
     * @param Request $request
     * @param int $id
     * @return \support\Response
     */
    public function show(Request $request, int $id): \support\Response
    {
        // 生成缓存键
        $cacheKey = "user_{$id}";
        
        // 尝试从缓存获取数据
        $cachedData = Redis::get($cacheKey);
        if ($cachedData) {
            $user = json_decode($cachedData, true);
            return json([
                'code' => 0,
                'msg' => 'success (from cache)',
                'data' => $user
            ]);
        }
        
        // 从数据库获取数据
        $user = User::find($id);
        if (!$user) {
            return json([
                'code' => 1,
                'msg' => '用户不存在'
            ]);
        }
        
        // 将数据存入缓存，有效期300秒
        Redis::setex($cacheKey, 300, json_encode($user));
        
        return json([
            'code' => 0,
            'msg' => 'success',
            'data' => $user
        ]);
    }
    
    /**
     * 清除用户列表缓存
     * 
     * @return void
     */
    private function clearUserListCache(): void
    {
        // 清除前10页的缓存
        for ($i = 1; $i <= 10; $i++) {
            Redis::del("user_list_page_{$i}_limit_10");
        }
    }
}