<?php
declare(strict_types=1);

namespace app\controller\api;

use app\BaseController;
use think\facade\Log;
use think\facade\Cache;
use think\facade\Config;
use think\queue\pool\PoolFactory;
use think\Request;
use think\Response;

/**
 * 系统监控控制器
 */
class Monitoring extends BaseController
{
    /**
     * 获取系统指标
     * 
     * @param Request $request
     * @return Response
     */
    public function getMetrics(Request $request)
    {
        try {
            // 获取系统资源使用情况
            $metrics = [
                'cpu' => $this->getCpuUsage(),
                'memory' => $this->getMemoryUsage(),
                'disk' => $this->getDiskUsage(),
                'queue' => $this->getQueueMetrics(),
                'pool' => PoolFactory::getAllPoolStatus(),
                'time' => time(),
                'uptime' => $this->getUptime()
            ];
            
            return $this->success('获取系统指标成功', $metrics);
        } catch (\Exception $e) {
            Log::error('获取系统指标失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('获取系统指标失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 获取系统健康状态
     * 
     * @param Request $request
     * @return Response
     */
    public function getHealth(Request $request)
    {
        try {
            // 检查数据库连接
            $dbStatus = $this->checkDatabaseConnection();
            
            // 检查Redis连接
            $redisStatus = $this->checkRedisConnection();
            
            // 检查Kafka连接
            $kafkaStatus = $this->checkKafkaConnection();
            
            // 综合检查结果
            $isHealthy = $dbStatus['status'] && $redisStatus['status'] && $kafkaStatus['status'];
            
            $health = [
                'status' => $isHealthy ? 'ok' : 'error',
                'timestamp' => date('Y-m-d H:i:s'),
                'services' => [
                    'database' => $dbStatus,
                    'redis' => $redisStatus,
                    'kafka' => $kafkaStatus
                ]
            ];
            
            // 如果是探针检查，只返回状态码
            if ($request->param('probe') == 'true') {
                return $isHealthy 
                    ? Response::create('ok', 'html', 200)
                    : Response::create('error', 'html', 500);
            }
            
            return $this->success('健康检查完成', $health);
        } catch (\Exception $e) {
            Log::error('健康检查失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            if ($request->param('probe') == 'true') {
                return Response::create('error', 'html', 500);
            }
            
            return $this->error('健康检查失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 获取消费者状态
     * 
     * @param Request $request
     * @return Response
     */
    public function getConsumers(Request $request)
    {
        try {
            // 获取连接池
            $pool = PoolFactory::getPool('kafka');
            $producer = $pool->getObject();
            
            // 获取超时设置
            $timeout = $request->param('timeout', 10000);
            
            // 获取消费者组参数
            $group = $request->param('group');
            
            // 获取元数据
            $metadata = $producer->getMetadata(false, null, (int)$timeout);
            
            // 获取消费者组列表
            $adminClient = $producer->newAdminClient();
            $groups = $adminClient->listGroups($timeout);
            
            // 处理消费者组信息
            $consumers = [];
            foreach ($groups as $consumerGroup) {
                // 如果指定了组，只返回该组的信息
                if ($group && $consumerGroup->getName() != $group) {
                    continue;
                }
                
                // 获取组详情
                $members = [];
                foreach ($consumerGroup->getMembers() as $member) {
                    $assignment = $member->getAssignment();
                    $topics = [];
                    
                    if ($assignment) {
                        foreach ($assignment->getTopicPartitions() as $topicPartition) {
                            $topic = $topicPartition->getTopic();
                            $partition = $topicPartition->getPartition();
                            
                            if (!isset($topics[$topic])) {
                                $topics[$topic] = [];
                            }
                            
                            $topics[$topic][] = $partition;
                        }
                    }
                    
                    $members[] = [
                        'id' => $member->getMemberId(),
                        'client_id' => $member->getClientId(),
                        'client_host' => $member->getClientHost(),
                        'topics' => $topics
                    ];
                }
                
                $consumers[] = [
                    'group_id' => $consumerGroup->getName(),
                    'state' => $consumerGroup->getState(),
                    'protocol' => $consumerGroup->getProtocol(),
                    'protocol_type' => $consumerGroup->getProtocolType(),
                    'members' => $members,
                    'member_count' => count($members)
                ];
            }
            
            // 归还连接
            $pool->recycleObj($producer);
            
            return $this->success('获取消费者状态成功', [
                'consumers' => $consumers,
                'total' => count($consumers)
            ]);
        } catch (\Exception $e) {
            Log::error('获取消费者状态失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('获取消费者状态失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 检查数据库连接
     * 
     * @return array
     */
    protected function checkDatabaseConnection(): array
    {
        try {
            $startTime = microtime(true);
            \think\facade\Db::query('SELECT 1');
            $endTime = microtime(true);
            
            return [
                'status' => true,
                'message' => '数据库连接正常',
                'response_time' => round(($endTime - $startTime) * 1000, 2) // ms
            ];
        } catch (\Exception $e) {
            Log::error('数据库连接检查失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'status' => false,
                'message' => '数据库连接失败: ' . $e->getMessage(),
                'response_time' => 0
            ];
        }
    }
    
    /**
     * 检查Redis连接
     * 
     * @return array
     */
    protected function checkRedisConnection(): array
    {
        try {
            $startTime = microtime(true);
            Cache::set('health_check', time(), 60);
            $value = Cache::get('health_check');
            $endTime = microtime(true);
            
            return [
                'status' => $value !== false,
                'message' => $value !== false ? 'Redis连接正常' : 'Redis读写失败',
                'response_time' => round(($endTime - $startTime) * 1000, 2) // ms
            ];
        } catch (\Exception $e) {
            Log::error('Redis连接检查失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'status' => false,
                'message' => 'Redis连接失败: ' . $e->getMessage(),
                'response_time' => 0
            ];
        }
    }
    
    /**
     * 检查Kafka连接
     * 
     * @return array
     */
    protected function checkKafkaConnection(): array
    {
        try {
            $startTime = microtime(true);
            
            // 获取连接池
            $pool = PoolFactory::getPool('kafka');
            $producer = $pool->getObject();
            
            // 获取元数据
            $metadata = $producer->getMetadata(true, null, 5000);
            $endTime = microtime(true);
            
            // 归还连接
            $pool->recycleObj($producer);
            
            return [
                'status' => true,
                'message' => 'Kafka连接正常',
                'response_time' => round(($endTime - $startTime) * 1000, 2), // ms
                'broker_count' => count($metadata->getBrokers())
            ];
        } catch (\Exception $e) {
            Log::error('Kafka连接检查失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'status' => false,
                'message' => 'Kafka连接失败: ' . $e->getMessage(),
                'response_time' => 0,
                'broker_count' => 0
            ];
        }
    }
    
    /**
     * 获取CPU使用率
     * 
     * @return array
     */
    protected function getCpuUsage(): array
    {
        try {
            if (function_exists('sys_getloadavg')) {
                $load = sys_getloadavg();
                return [
                    'load_avg' => [
                        '1min' => $load[0],
                        '5min' => $load[1],
                        '15min' => $load[2]
                    ]
                ];
            }
            
            return [
                'load_avg' => [
                    '1min' => 0,
                    '5min' => 0,
                    '15min' => 0
                ]
            ];
        } catch (\Exception $e) {
            Log::error('获取CPU使用率失败', [
                'error' => $e->getMessage()
            ]);
            return [
                'load_avg' => [
                    '1min' => 0,
                    '5min' => 0,
                    '15min' => 0
                ],
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 获取内存使用情况
     * 
     * @return array
     */
    protected function getMemoryUsage(): array
    {
        try {
            // PHP内存使用
            $memoryUsage = memory_get_usage(true);
            $memoryPeakUsage = memory_get_peak_usage(true);
            
            return [
                'php' => [
                    'current' => $this->formatBytes($memoryUsage),
                    'peak' => $this->formatBytes($memoryPeakUsage)
                ]
            ];
        } catch (\Exception $e) {
            Log::error('获取内存使用情况失败', [
                'error' => $e->getMessage()
            ]);
            return [
                'php' => [
                    'current' => '0 B',
                    'peak' => '0 B'
                ],
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 获取磁盘使用情况
     * 
     * @return array
     */
    protected function getDiskUsage(): array
    {
        try {
            $rootPath = root_path();
            $totalSpace = disk_total_space($rootPath);
            $freeSpace = disk_free_space($rootPath);
            $usedSpace = $totalSpace - $freeSpace;
            $usedPercent = round(($usedSpace / $totalSpace) * 100, 2);
            
            return [
                'total' => $this->formatBytes($totalSpace),
                'free' => $this->formatBytes($freeSpace),
                'used' => $this->formatBytes($usedSpace),
                'used_percent' => $usedPercent . '%'
            ];
        } catch (\Exception $e) {
            Log::error('获取磁盘使用情况失败', [
                'error' => $e->getMessage()
            ]);
            return [
                'total' => '0 B',
                'free' => '0 B',
                'used' => '0 B',
                'used_percent' => '0%',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 获取队列指标
     * 
     * @return array
     */
    protected function getQueueMetrics(): array
    {
        try {
            $redis = app('redis');
            $metrics = [];
            
            // 获取所有队列名称
            $queueNames = [];
            
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
            
            // 获取每个队列的统计数据
            foreach ($queueNames as $queueName) {
                $waiting = $redis->llen('queues:' . $queueName);
                $processing = $redis->zcard('queues:' . $queueName . ':processing');
                $failed = $redis->llen('dead_letter:' . $queueName);
                $delayed = $redis->zcard('queues:' . $queueName . ':delayed');
                
                $metrics[$queueName] = [
                    'waiting' => $waiting,
                    'processing' => $processing,
                    'failed' => $failed,
                    'delayed' => $delayed,
                    'total' => $waiting + $processing + $delayed
                ];
            }
            
            return $metrics;
        } catch (\Exception $e) {
            Log::error('获取队列指标失败', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * 获取系统运行时间
     * 
     * @return array
     */
    protected function getUptime(): array
    {
        try {
            $uptime = 0;
            
            // 尝试从系统获取
            if (function_exists('shell_exec')) {
                $uptimeStr = shell_exec('uptime -p');
                if ($uptimeStr) {
                    return [
                        'system' => trim($uptimeStr),
                    ];
                }
            }
            
            // 应用启动时间
            $appStartTime = file_exists(root_path() . 'runtime/.start_time') 
                ? file_get_contents(root_path() . 'runtime/.start_time') 
                : 0;
            
            if ($appStartTime > 0) {
                $uptime = time() - $appStartTime;
            }
            
            return [
                'application' => $this->formatUptime($uptime)
            ];
        } catch (\Exception $e) {
            Log::error('获取系统运行时间失败', [
                'error' => $e->getMessage()
            ]);
            return [
                'application' => '未知',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 格式化字节数
     * 
     * @param int $bytes 字节数
     * @param int $precision 精度
     * @return string
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
    
    /**
     * 格式化运行时间
     * 
     * @param int $seconds 秒数
     * @return string
     */
    protected function formatUptime(int $seconds): string
    {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;
        
        $result = '';
        
        if ($days > 0) {
            $result .= $days . ' 天 ';
        }
        
        if ($hours > 0 || $days > 0) {
            $result .= $hours . ' 小时 ';
        }
        
        if ($minutes > 0 || $hours > 0 || $days > 0) {
            $result .= $minutes . ' 分钟 ';
        }
        
        $result .= $seconds . ' 秒';
        
        return $result;
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