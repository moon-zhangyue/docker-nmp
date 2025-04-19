<?php
declare(strict_types=1);

namespace app\job;

use think\queue\Job;
use app\service\ElasticsearchService;

class DeleteGoodsJob
{
    public function fire(Job $job, $data)
    {
        $esService = new ElasticsearchService();
        $client    = $esService->getClient();
        $params    = [
            'index' => 'goods',
            'id'    => $data['sku_id'],
        ];
        try {
            $esService->executeWithRetry(function () use ($client, $params) {
                return $client->delete($params);
            });
            $job->delete();
        } catch (\Exception $e) {
            \think\facade\Log::error('Async delete error: ' . $e->getMessage());
            if ($job->attempts() >= 3) {
                $job->delete();
            } else {
                $job->release(60);
            }
        }
    }
}