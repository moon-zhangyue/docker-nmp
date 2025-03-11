<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use think\queue\metrics\PrometheusCollector;
use think\Response;

class Metrics extends BaseController
{
    /**
     * 提供Prometheus指标数据的HTTP端点
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
}