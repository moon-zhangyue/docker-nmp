<?php
declare(strict_types=1);

namespace app\controller\api;

use app\BaseController;
use think\facade\Log;
use think\facade\Config;
use think\queue\pool\PoolFactory;
use think\Request;
use think\Response;

/**
 * 连接池管理控制器
 */
class Pool extends BaseController
{
    /**
     * 获取连接池状态
     * 
     * @param Request $request
     * @return Response
     */
    public function getStatus(Request $request)
    {
        try {
            // 获取所有连接池状态
            $status = PoolFactory::getAllPoolStatus();
            
            // 获取资源利用率
            $utilization = [];
            foreach ($status as $name => $pool) {
                if ($pool['max'] > 0) {
                    $utilization[$name] = [
                        'percent' => round(($pool['using'] / $pool['max']) * 100, 2),
                        'idle_percent' => round(($pool['idle'] / $pool['max']) * 100, 2)
                    ];
                } else {
                    $utilization[$name] = [
                        'percent' => 0,
                        'idle_percent' => 0
                    ];
                }
            }
            
            return $this->success('获取连接池状态成功', [
                'pools' => $status,
                'utilization' => $utilization,
                'total_pools' => count($status)
            ]);
        } catch (\Exception $e) {
            Log::error('获取连接池状态失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('获取连接池状态失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 更新连接池配置
     * 
     * @param Request $request
     * @return Response
     */
    public function updateConfig(Request $request)
    {
        try {
            // 验证参数
            $validate = validate([
                'name' => 'require|alphaDash',
                'config' => 'require|array'
            ], [
                'name.require' => '连接池名称不能为空',
                'name.alphaDash' => '连接池名称只能包含字母、数字、下划线和破折号',
                'config.require' => '连接池配置不能为空',
                'config.array' => '连接池配置必须是数组'
            ]);
            
            if (!$validate->check($request->post())) {
                return $this->error($validate->getError());
            }
            
            // 获取参数
            $name = $request->post('name');
            $config = $request->post('config');
            
            // 获取队列配置
            $queueConfig = Config::get('queue');
            
            // 检查连接池是否存在
            if (!isset($queueConfig['connections'][$name])) {
                return $this->error('连接池不存在: ' . $name);
            }
            
            // 更新连接池配置
            if (!isset($queueConfig['connections'][$name]['pool'])) {
                $queueConfig['connections'][$name]['pool'] = [];
            }
            
            // 合并配置
            $queueConfig['connections'][$name]['pool'] = array_merge(
                $queueConfig['connections'][$name]['pool'],
                $config
            );
            
            // 保存配置
            $configFile = config_path() . 'queue.php';
            $content = "<?php\n\nreturn " . var_export($queueConfig, true) . ";\n";
            
            // 替换true/false为小写
            $content = str_replace("=> NULL", "=> null", $content);
            $content = str_replace("=> TRUE", "=> true", $content);
            $content = str_replace("=> FALSE", "=> false", $content);
            
            if (file_put_contents($configFile, $content) === false) {
                return $this->error('保存配置失败');
            }
            
            // 记录操作日志
            Log::info('更新连接池配置', [
                'user_id' => $request->user['id'] ?? 0,
                'username' => $request->user['username'] ?? 'unknown',
                'pool' => $name,
                'config' => $config
            ]);
            
            return $this->success('更新连接池配置成功');
        } catch (\Exception $e) {
            Log::error('更新连接池配置失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('更新连接池配置失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 返回成功响应
     * 
     * @param string $message 成功信息
     * @param array $data 数据
     * @return Response
     */
    protected function success(string $message, array $data = []): Response
    {
        return json([
            'code' => 200,
            'message' => $message,
            'data' => $data
        ]);
    }
    
    /**
     * 返回错误响应
     * 
     * @param string $message 错误信息
     * @param int $code 错误码
     * @param array $data 数据
     * @return Response
     */
    protected function error(string $message, int $code = 400, array $data = []): Response
    {
        return json([
            'code' => $code,
            'message' => $message,
            'data' => $data
        ]);
    }
} 