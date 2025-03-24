<?php

namespace app\controller\api;

use app\BaseController;
use think\facade\Log;
use think\facade\Queue;
use think\facade\Config;
use think\queue\pool\PoolFactory;
use think\Request;
use think\Response;

/**
 * 队列管理API控制器
 * 
 * 提供队列管理和监控的API接口
 */
class QueueManager extends BaseController
{
    /**
     * 获取所有队列连接配置
     * 
     * @return Response
     */
    public function getConnections(): Response
    {
        // 获取队列配置
        $config = Config::get('queue');
        
        // 移除敏感信息（密码等）
        $connections = $config['connections'] ?? [];
        
        foreach ($connections as $name => &$connection) {
            if (isset($connection['password'])) {
                $connection['password'] = '******';
            }
            
            if (isset($connection['redis']['password'])) {
                $connection['redis']['password'] = '******';
            }
        }
        
        return $this->success('获取成功', [
            'default' => $config['default'] ?? '',
            'connections' => $connections
        ]);
    }
    
    /**
     * 更新队列连接配置
     * 
     * @param Request $request 请求对象
     * @return Response
     */
    public function updateConnection(Request $request): Response
    {
        try {
            // 验证权限（需要管理员权限）
            if (!$this->hasRole($request, 'admin')) {
                return $this->error('无权限执行此操作', 403);
            }
            
            // 获取连接名称
            $name = $request->param('name');
            
            if (empty($name)) {
                return $this->error('连接名称不能为空', 400);
            }
            
            // 获取当前配置
            $config = Config::get('queue');
            
            // 获取更新数据
            $data = $request->post();
            
            // 更新配置
            $config['connections'][$name] = array_merge(
                $config['connections'][$name] ?? [],
                $data
            );
            
            // 保存配置
            $this->saveConfig('queue', $config);
            
            // 记录操作日志
            Log::info('更新队列连接配置', [
                'user_id' => $request->userId,
                'connection' => $name,
                'changes' => json_encode($data)
            ]);
            
            return $this->success('更新成功');
        } catch (\Exception $e) {
            Log::error('更新队列连接配置失败: ' . $e->getMessage(), [
                'user_id' => $request->userId ?? 0,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->error('更新失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 获取队列状态
     * 
     * @param Request $request 请求对象
     * @return Response
     */
    public function getStatus(Request $request): Response
    {
        // 获取队列名称
        $queue = $request->param('queue');
        
        // 获取队列统计数据
        $stats = $this->getQueueStats($queue);
        
        // 获取连接池状态
        $poolStatus = PoolFactory::getAllPoolStatus();
        
        return $this->success('获取成功', [
            'stats' => $stats,
            'pools' => $poolStatus
        ]);
    }
    
    /**
     * 推送消息到队列
     * 
     * @param Request $request 请求对象
     * @return Response
     */
    public function push(Request $request): Response
    {
        try {
            // 验证请求参数
            $validate = validate([
                'job' => 'require|alphaDash',
                'data' => 'array',
                'queue' => 'alphaDash',
                'delay' => 'integer|egt:0'
            ]);
            
            if (!$validate->check($request->post())) {
                return $this->error('请求参数错误: ' . $validate->getError(), 400);
            }
            
            // 获取参数
            $job = $request->post('job');
            $data = $request->post('data', []);
            $queue = $request->post('queue');
            $delay = $request->post('delay', 0);
            
            // 添加用户ID到数据中，用于追踪
            $data['_user_id'] = $request->userId ?? 0;
            
            // 推送到队列
            if ($delay > 0) {
                $result = Queue::later($delay, $job, $data, $queue);
            } else {
                $result = Queue::push($job, $data, $queue);
            }
            
            if ($result === false) {
                return $this->error('推送失败', 500);
            }
            
            // 记录操作日志
            Log::info('推送消息到队列', [
                'user_id' => $request->userId ?? 0,
                'job' => $job,
                'queue' => $queue,
                'delay' => $delay
            ]);
            
            return $this->success('推送成功', [
                'result' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('推送消息到队列失败: ' . $e->getMessage(), [
                'user_id' => $request->userId ?? 0,
                'error' => $e->getMessage()
            ]);
            
            return $this->error('推送失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 获取死信队列消息
     * 
     * @param Request $request 请求对象
     * @return Response
     */
    public function getDeadLetters(Request $request): Response
    {
        try {
            // 获取队列名称
            $queue = $request->param('queue');
            
            // 获取页码和每页数量
            $page = $request->param('page', 1);
            $limit = $request->param('limit', 20);
            
            // 使用Redis获取死信队列数据
            $redis = app('redis');
            $key = 'dead_letter:' . ($queue ?: 'default');
            
            // 获取总数
            $total = $redis->llen($key);
            
            // 获取数据
            $start = ($page - 1) * $limit;
            $end = $start + $limit - 1;
            $items = $redis->lrange($key, $start, $end);
            
            // 解析数据
            $data = [];
            foreach ($items as $item) {
                $data[] = json_decode($item, true);
            }
            
            return $this->success('获取成功', [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'items' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('获取死信队列消息失败: ' . $e->getMessage());
            return $this->error('获取失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 清空死信队列
     * 
     * @param Request $request 请求对象
     * @return Response
     */
    public function clearDeadLetters(Request $request): Response
    {
        try {
            // 验证权限（需要管理员权限）
            if (!$this->hasRole($request, 'admin')) {
                return $this->error('无权限执行此操作', 403);
            }
            
            // 获取队列名称
            $queue = $request->param('queue');
            
            // 使用Redis清空死信队列
            $redis = app('redis');
            $key = 'dead_letter:' . ($queue ?: 'default');
            $count = $redis->llen($key);
            $redis->del($key);
            
            // 记录操作日志
            Log::info('清空死信队列', [
                'user_id' => $request->userId,
                'queue' => $queue,
                'count' => $count
            ]);
            
            return $this->success('清空成功', [
                'count' => $count
            ]);
        } catch (\Exception $e) {
            Log::error('清空死信队列失败: ' . $e->getMessage());
            return $this->error('清空失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 重试死信队列消息
     * 
     * @param Request $request 请求对象
     * @return Response
     */
    public function retryDeadLetter(Request $request): Response
    {
        try {
            // 验证权限（需要管理员或操作员权限）
            if (!$this->hasRole($request, ['admin', 'operator'])) {
                return $this->error('无权限执行此操作', 403);
            }
            
            // 获取消息ID
            $id = $request->param('id');
            
            if (empty($id)) {
                return $this->error('消息ID不能为空', 400);
            }
            
            // 获取队列名称
            $queue = $request->param('queue');
            
            // 使用Redis查找消息
            $redis = app('redis');
            $key = 'dead_letter:' . ($queue ?: 'default');
            
            // 查找并移除消息
            $found = false;
            $items = $redis->lrange($key, 0, -1);
            
            foreach ($items as $index => $item) {
                $data = json_decode($item, true);
                
                if (isset($data['id']) && $data['id'] == $id) {
                    // 从死信队列移除
                    $redis->lrem($key, 1, $item);
                    
                    // 重新推送到原始队列
                    $originalJob = $data['job'] ?? '';
                    $originalData = $data['data'] ?? [];
                    $originalQueue = $data['queue'] ?? '';
                    
                    Queue::push($originalJob, $originalData, $originalQueue);
                    
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                return $this->error('未找到指定消息', 404);
            }
            
            // 记录操作日志
            Log::info('重试死信队列消息', [
                'user_id' => $request->userId,
                'message_id' => $id,
                'queue' => $queue
            ]);
            
            return $this->success('重试成功');
        } catch (\Exception $e) {
            Log::error('重试死信队列消息失败: ' . $e->getMessage());
            return $this->error('重试失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 获取队列统计数据
     * 
     * @param string|null $queue 队列名称
     * @return array
     */
    protected function getQueueStats(?string $queue = null): array
    {
        try {
            $redis = app('redis');
            $stats = [];
            
            // 获取所有队列名称
            $queueNames = [];
            
            if ($queue) {
                $queueNames[] = $queue;
            } else {
                // 获取配置中定义的所有队列
                $config = Config::get('queue.connections');
                
                foreach ($config as $name => $connection) {
                    if (isset($connection['queue'])) {
                        $queueNames[] = $connection['queue'];
                    }
                }
                
                // 获取Redis中存在的队列
                $keys = $redis->keys('queues:*');
                
                foreach ($keys as $key) {
                    $parts = explode(':', $key);
                    $queueName = $parts[1] ?? '';
                    
                    if (!empty($queueName) && !in_array($queueName, $queueNames)) {
                        $queueNames[] = $queueName;
                    }
                }
            }
            
            // 获取每个队列的统计数据
            foreach ($queueNames as $queueName) {
                $waiting = $redis->llen('queues:' . $queueName);
                $processing = $redis->zcard('queues:' . $queueName . ':processing');
                $failed = $redis->llen('dead_letter:' . $queueName);
                $delayed = $redis->zcard('queues:' . $queueName . ':delayed');
                
                $stats[$queueName] = [
                    'waiting' => $waiting,
                    'processing' => $processing,
                    'failed' => $failed,
                    'delayed' => $delayed,
                    'total' => $waiting + $processing + $delayed
                ];
            }
            
            return $stats;
        } catch (\Exception $e) {
            Log::error('获取队列统计数据失败: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 保存配置
     * 
     * @param string $name 配置名称
     * @param array $config 配置数据
     * @return bool
     */
    protected function saveConfig(string $name, array $config): bool
    {
        $configFile = config_path() . $name . '.php';
        
        // 生成配置内容
        $content = "<?php\n\nreturn " . var_export($config, true) . ";\n";
        
        // 替换true/false为小写
        $content = str_replace("=> NULL", "=> null", $content);
        $content = str_replace("=> TRUE", "=> true", $content);
        $content = str_replace("=> FALSE", "=> false", $content);
        
        // 写入文件
        return file_put_contents($configFile, $content) !== false;
    }
    
    /**
     * 检查用户是否具有指定角色
     * 
     * @param Request $request 请求对象
     * @param string|array $roles 角色或角色数组
     * @return bool
     */
    protected function hasRole(Request $request, $roles): bool
    {
        // 如果未设置用户角色，则没有权限
        if (!isset($request->userRoles)) {
            return false;
        }
        
        // 如果用户是管理员，拥有所有权限
        if (in_array('admin', $request->userRoles)) {
            return true;
        }
        
        // 检查用户是否拥有指定角色之一
        $requiredRoles = is_array($roles) ? $roles : [$roles];
        
        foreach ($requiredRoles as $role) {
            if (in_array($role, $request->userRoles)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 返回成功响应
     * 
     * @param string $message 成功消息
     * @param array $data 响应数据
     * @return Response
     */
    protected function success(string $message, array $data = []): Response
    {
        return Response::create([
            'code' => 0,
            'message' => $message,
            'data' => $data
        ], 'json');
    }
    
    /**
     * 返回错误响应
     * 
     * @param string $message 错误消息
     * @param int $code 错误码
     * @param array $data 响应数据
     * @return Response
     */
    protected function error(string $message, int $code = 500, array $data = []): Response
    {
        return Response::create([
            'code' => $code,
            'message' => $message,
            'data' => $data
        ], 'json', $code);
    }
} 