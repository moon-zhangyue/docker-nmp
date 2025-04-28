<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use think\facade\Log; // 引入 Log facade
use think\Response;
use Elasticsearch\ClientBuilder;
use think\facade\Config;

class MonoLog extends BaseController
{
    public function index(): Response
    {
        // --- 日誌記錄範例 ---

        // 簡單的資訊日誌
        Log::info('使用者存取了首頁。');

        // 帶有上下文數據的日誌
        $userId    = 123;
        $sessionId = session_id(); // 範例：獲取 session ID
        Log::info('使用者執行了一個動作：{user_id},{session_id}', [
            'user_id'    => $userId,
            'session_id' => $sessionId
        ]);

        // 警告日誌
        Log::warning('偵測到潛在問題：設定鍵 "site_name" 的快取未命中。');

        // 錯誤日誌 (例如，模擬捕獲到的異常)
        try {
            // 模擬一個可能失敗的操作
            if (rand(0, 1) === 0) {
                throw new \RuntimeException("模拟资料库连接失败");
            }
            $result = "操作成功";

        } catch (\Throwable $e) {
            Log::error('操作失敗：{error_message}-{error_code}-{trace_snippet}', [
                'error_message' => $e->getMessage(),
                'error_code'    => $e->getCode(),
                'trace_snippet' => mb_substr($e->getTraceAsString(), 0, 500) // 記錄部分堆疊追蹤 (使用 mb_substr 處理多位元組字元)
            ]);
            $result = "操作失敗，請檢查日誌。";
        }

        // 嚴重錯誤日誌範例
        // Log::critical('安全警報：偵測到使用者 X 多次登入失敗。', ['ip_address' => $this->request->ip()]);


        // --- 返回回應 ---
        // 將回應文字改為繁體中文
        return Response::create("日誌記錄範例已執行。請檢查您的 Elasticsearch 索引 (例如：logs-YYYY.MM.DD) 以及可能的備用檔案日誌。")->contentType('text/plain');
    }

    public function testFallback()
    {
        // 明確記錄到檔案通道的範例
        Log::channel('file')->info('此訊息僅會記錄到檔案日誌中。');

        // 將回應文字改為繁體中文
        return Response::create("已明確記錄到檔案通道。");
    }
    
    /**
     * 诊断 ES 连接状态
     */
    public function diagnoseEs()
    {
        $result = [];
        
        // 获取 ES 配置
        $esConfig = config('elasticsearch');
        $result['config'] = [
            'hosts' => $esConfig['hosts'] ?? ['未配置'],
            'auth_configured' => !empty($esConfig['auth'][0]) ? '已配置' : '未配置',
            'index_prefix' => $esConfig['index_prefix'] ?? 'logs',
            'timeout' => $esConfig['timeout'] ?? 5,
            'connect_timeout' => $esConfig['connect_timeout'] ?? 3,
        ];
        
        // 测试 ES 连接
        try {
            $clientBuilder = ClientBuilder::create()
                ->setHosts($esConfig['hosts'] ?? ['localhost:9200']);
                
            if (isset($esConfig['auth']) && is_array($esConfig['auth']) && !empty($esConfig['auth'][0])) {
                $clientBuilder->setBasicAuthentication($esConfig['auth'][0], $esConfig['auth'][1] ?? '');
            }
            
            $clientBuilder->setConnectionParams([
                'client' => [
                    'timeout' => $esConfig['timeout'] ?? 5,
                    'connect_timeout' => $esConfig['connect_timeout'] ?? 3
                ]
            ]);
            
            $client = $clientBuilder->build();
            
            // 检查 ES 集群状态
            $pingResult = $client->ping();
            $clusterHealth = $client->cluster()->health();
            
            $result['connection'] = [
                'status' => '成功',
                'ping' => $pingResult ? '成功' : '失败',
                'cluster_status' => $clusterHealth['status'] ?? '未知',
                'cluster_name' => $clusterHealth['cluster_name'] ?? '未知',
                'nodes' => $clusterHealth['number_of_nodes'] ?? 0,
            ];
            
            // 记录一条测试日志
            Log::info('ES 诊断测试日志');
            $result['test_log'] = '已尝试写入测试日志';
            
        } catch (\Throwable $e) {
            $result['connection'] = [
                'status' => '失败',
                'error' => $e->getMessage(),
                'trace' => mb_substr($e->getTraceAsString(), 0, 300)
            ];
        }
        
        // 检查日志配置
        $logConfig = config('log');
        $result['log_config'] = [
            'default_channel' => $logConfig['default'] ?? '未知',
            'close' => $logConfig['close'] ? '已关闭' : '已开启',
            'available_channels' => array_keys($logConfig['channels'] ?? []),
        ];
        
        return json($result);
    }
}

