<?php
declare(strict_types=1);

namespace think\queue\metrics;

use think\facade\Log;
use think\facade\Cache;

/**
 * Prometheus指标收集器
 * 用于收集队列处理的指标数据，支持导出为Prometheus格式
 */
class PrometheusCollector
{
    // 单例实例
    private static $instance = null;
    
    // 指标数据
    private $metrics = [
        'queue_jobs_total' => [],        // 总处理任务数
        'queue_jobs_success' => [],      // 成功处理任务数
        'queue_jobs_failed' => [],       // 失败任务数
        'queue_jobs_processing_time' => [] // 任务处理时间
    ];
    
    // 标签定义
    private $labels = [
        'connection', // 连接名
        'queue',      // 队列名
    ];
    
    // 指标描述
    private $descriptions = [
        'queue_jobs_total' => 'Total number of queue jobs processed',
        'queue_jobs_success' => 'Number of queue jobs processed successfully',
        'queue_jobs_failed' => 'Number of queue jobs that failed processing',
        'queue_jobs_processing_time' => 'Time spent processing queue jobs'
    ];
    
    // Redis缓存键
    private $cacheKey = 'prometheus_metrics';
    
    /**
     * 私有构造函数，防止外部实例化
     */
    private function __construct()
    {
        // 从Redis加载指标数据
        $this->loadMetricsFromCache();
    }
    
    /**
     * 获取单例实例
     * 
     * @return PrometheusCollector
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    /**
     * 增加指标计数
     * 
     * @param string $metric 指标名称
     * @param array $labels 标签值
     * @param float $value 增加的值
     * @return void
     */
    public function increment(string $metric, array $labels, float $value = 1.0)
    {
        $labelKey = $this->getLabelKey($labels);
        
        if (!isset($this->metrics[$metric][$labelKey])) {
            $this->metrics[$metric][$labelKey] = [
                'value' => 0,
                'labels' => $labels
            ];
        }
        
        $this->metrics[$metric][$labelKey]['value'] += $value;
        
        // 保存到Redis缓存
        $this->saveMetricsToCache();
    }
    
    /**
     * 记录任务处理成功
     * 
     * @param string $connection 连接名
     * @param string $queue 队列名
     * @param float $processingTime 处理时间（秒）
     * @return void
     */
    public function recordSuccess(string $connection, string $queue, float $processingTime = 0.0)
    {
        $labels = [
            'connection' => $connection,
            'queue' => $queue
        ];
        
        $this->increment('queue_jobs_total', $labels);
        $this->increment('queue_jobs_success', $labels);
        $this->increment('queue_jobs_processing_time', $labels, $processingTime);
        
        Log::debug('Recorded queue job success', $labels);
    }
    
    /**
     * 记录任务处理失败
     * 
     * @param string $connection 连接名
     * @param string $queue 队列名
     * @param float $processingTime 处理时间（秒）
     * @return void
     */
    public function recordFailure(string $connection, string $queue, float $processingTime = 0.0)
    {
        $labels = [
            'connection' => $connection,
            'queue' => $queue
        ];
        
        $this->increment('queue_jobs_total', $labels);
        $this->increment('queue_jobs_failed', $labels);
        $this->increment('queue_jobs_processing_time', $labels, $processingTime);
        
        Log::debug('Recorded queue job failure', $labels);
    }
    
    /**
     * 获取标签键
     * 
     * @param array $labels 标签数组
     * @return string
     */
    private function getLabelKey(array $labels): string
    {
        $parts = [];
        
        foreach ($this->labels as $label) {
            $parts[] = $label . '="' . ($labels[$label] ?? '') . '"';
        }
        
        return implode(',', $parts);
    }
    
    /**
     * 导出Prometheus格式的指标数据
     * 
     * @return string
     */
    public function export(): string
    {
        $output = [];
        
        foreach ($this->metrics as $metric => $data) {
            // 添加指标说明
            $output[] = '# HELP ' . $metric . ' ' . ($this->descriptions[$metric] ?? '');
            $output[] = '# TYPE ' . $metric . ' counter';
            
            // 添加指标数据
            foreach ($data as $item) {
                $labelString = $this->formatLabels($item['labels']);
                $output[] = $metric . $labelString . ' ' . $item['value'];
            }
            
            $output[] = '';
        }
        
        return implode("\n", $output);
    }
    
    /**
     * 格式化标签为Prometheus格式
     * 
     * @param array $labels 标签数组
     * @return string
     */
    private function formatLabels(array $labels): string
    {
        if (empty($labels)) {
            return '';
        }
        
        $parts = [];
        
        foreach ($labels as $key => $value) {
            $parts[] = $key . '="' . $value . '"';
        }
        
        return '{' . implode(',', $parts) . '}';
    }
    
    /**
     * 重置所有指标数据
     * 
     * @return void
     */
    public function reset()
    {
        foreach ($this->metrics as $metric => $data) {
            $this->metrics[$metric] = [];
        }
        
        // 清除Redis缓存
        $this->saveMetricsToCache();
    }
    
    /**
     * 从Redis缓存加载指标数据
     * 
     * @return void
     */
    private function loadMetricsFromCache()
    {
        $cachedMetrics = Cache::get($this->cacheKey);
        
        if (!empty($cachedMetrics) && is_array($cachedMetrics)) {
            $this->metrics = $cachedMetrics;
            Log::debug('Loaded metrics from Redis cache');
        }
    }
    
    /**
     * 保存指标数据到Redis缓存
     * 
     * @return void
     */
    private function saveMetricsToCache()
    {
        Cache::set($this->cacheKey, $this->metrics);
        Log::debug('Saved metrics to Redis cache');
    }
    
    /**
     * 获取原始指标数据
     * 
     * @return array
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }
}