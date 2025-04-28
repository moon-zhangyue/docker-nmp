<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use think\facade\Log; // 引入 Log facade
use think\Response;

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
        Log::info('使用者執行了一個動作', [
            'user_id'    => $userId,
            'session_id' => $sessionId,
            'action'     => 'view_homepage'
        ]);

        // 警告日誌
        Log::warning('偵測到潛在問題：設定鍵 "site_name" 的快取未命中。');

        // 錯誤日誌 (例如，模擬捕獲到的異常)
        try {
            // 模擬一個可能失敗的操作
            if (rand(0, 1) === 0) {
                throw new \RuntimeException("模擬資料庫連接失敗。");
            }
            $result = "操作成功";

        } catch (\Throwable $e) {
            Log::error('操作失敗', [
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
}

