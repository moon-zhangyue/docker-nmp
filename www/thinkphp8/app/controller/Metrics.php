<?php

declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use think\facade\Cache;
use think\facade\View;
use think\queue\metrics\PrometheusCollector;
use think\queue\health\HealthCheck;
use think\Response;

/**
 * 队列监控指标控制器
 * 用于提供队列监控指标的Web接口
 */
class Metrics extends BaseController
{
    /**
     * 显示队列监控指标
     */
    public function index()
    {
        // 获取健康检查实例
        $healthCheck = HealthCheck::getInstance();
        $healthStatus = $healthCheck->getHealthStatus();

        // 获取指标收集器实例
        $collector = PrometheusCollector::getInstance();
        $metrics = $collector->getQueueMetrics();

        // 准备视图数据
        $data = [
            'metrics' => $metrics,
            'health' => $healthStatus,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        // 返回视图
        return View::fetch('metrics/index', $data);
    }

    /**
     * 提供Prometheus格式的指标数据
     * 
     * @return Response
     */
    public function prometheus()
    {
        // 获取指标收集器实例
        $collector = PrometheusCollector::getInstance();

        // 获取Prometheus格式的指标数据
        $metrics = $collector->export();

        // 返回纯文本格式的响应，设置正确的Content-Type
        return Response::create($metrics, 'html', 200)
            ->header(['Content-Type' => 'text/plain; version=0.0.4']);
    }

    /**
     * 提供健康检查接口
     */
    public function health()
    {
        $healthCheck = HealthCheck::getInstance();
        $status = $healthCheck->getHealthStatus();

        // 设置HTTP状态码
        $httpStatus = $status['status'] === 'healthy' ? 200 : 503;

        return json($status, $httpStatus);
    }

    /**
     * 重置监控指标
     */
    public function reset()
    {
        Cache::delete('queue_metrics');
        return json(['message' => '队列监控指标已重置', 'success' => true]);
    }
}
