<?php

declare(strict_types=1);

namespace app\common\log\driver;

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Elasticsearch\Common\Exceptions\ElasticsearchException;
use think\facade\Log as ThinkLog; // 使用不同的別名以避免衝突

/**
 * ThinkPHP 8 的 Elasticsearch 日誌驅動
 *
 * 將日誌發送到 Elasticsearch 實例。
 */
class ElasticsearchLogDriver
{
    /**
     * 日誌設定。
     * @var array
     */
    protected array $config = [
        'hosts'           => ['elasticsearch:9200'], // Elasticsearch 主機
        'index_prefix'    => 'logs',    // 索引名稱前綴 (例如：logs-YYYY.MM.DD)
        'index_format'    => 'Y.m.d',          // 索引後綴的日期格式
        'type'            => '_doc',             // Elasticsearch 文件類型 (現代 ES 使用 '_doc')
        'timeout'         => 1,                  // 連接超時時間 (秒)
        'connect_timeout' => 1,               // 請求超時時間 (秒)
        // 如果需要，可添加其他 Elasticsearch 客戶端選項 (例如：身份驗證)
        // 'auth' => ['username', 'password']
    ];

    /**
     * Elasticsearch 客戶端實例。
     * @var Client|null
     */
    protected ?Client $client = null;

    /**
     * 建構子。
     *
     * @param array $config 設定選項。
     */
    public function __construct(array $config = [])
    {
        // 合併提供的設定與預設值
        $this->config = array_merge($this->config, $config);

        try {
            $clientBuilder = ClientBuilder::create()->setHosts($this->config['hosts']);

            // 如果設定了身份驗證
            if (isset($this->config['auth']) && is_array($this->config['auth'])) {
                $clientBuilder->setBasicAuthentication($this->config['auth'][0], $this->config['auth'][1]);
            }

            $clientBuilder->setConnectionParams([
                'client' => [
                    'timeout'         => $this->config['timeout'],
                    'connect_timeout' => $this->config['connect_timeout']
                ]
            ]);
            $clientBuilder->setRetries(0); // 日誌記錄失敗時不自動重試

            $this->client = $clientBuilder->build();
        } catch (\Throwable $e) {
            // 將初始化錯誤記錄到備用日誌 (例如：檔案)
            // 在此直接使用 ThinkLog 可能會導致無限迴圈 (如果預設驅動是此驅動)
            error_log("ElasticsearchLogDriver: 初始化 Elasticsearch 客戶端失敗: " . $e->getMessage());
            $this->client = null; // 確保初始化失敗時 client 為 null
        }
    }

    /**
     * 將日誌訊息儲存到 Elasticsearch。
     *
     * @param array $log 按級別分類的日誌數據 (例如：['info' => [...], 'error' => [...]])。
     * @return bool 是否嘗試了儲存操作 (不保證所有訊息都成功)。
     */
    public function save(array $log): bool
    {
        if (!$this->client) {
            error_log("ElasticsearchLogDriver: 客戶端未初始化，無法儲存日誌。");
            return false; // 客戶端初始化失敗
        }

        // 根據日期生成動態索引名稱
        $indexName = $this->config['index_prefix'] . '-' . date($this->config['index_format']);

        // 準備 bulk request 參數
        $params   = ['body' => []];
        $logCount = 0;

        // 從 ThinkPHP 的 Log context 獲取上下文 (如果可用)
        $globalContext = ThinkLog::getConte(); // 重命名了 ThinkLog facade 的用法

        foreach ($log as $level => $messages) {
            foreach ($messages as $message) {
                // 準備日誌文件結構
                $logEntry = [
                    '@timestamp'  => date('c'), // ISO 8601 格式，ES 標準
                    'level'       => strtoupper($level),
                    'message'     => $this->formatMessage($message), // 格式化訊息本文
                    'context'     => [], // 上下文的佔位符
                    'channel'     => $this->config['channel'] ?? 'default', // 如果可用，添加通道資訊
                    // 您可以在此處添加更多固定欄位，例如應用程式名稱
                    'application' => env('APP_NAME', 'ThinkPHPApp')
                ];

                // 處理上下文數據
                // ThinkPHP 通常將上下文作為最後一個元素傳遞 (如果是陣列)
                $contextData = $this->extractContext($message);
                if (!empty($contextData)) {
                    $logEntry['context'] = $contextData;
                }

                // 合併全域上下文 (如果有)
                if (!empty($globalContext)) {
                    $logEntry['context'] = array_merge($globalContext, $logEntry['context']);
                }


                // 為 bulk request 添加文件元數據
                $params['body'][] = [
                    'index' => [
                        '_index' => $indexName,
                        // '_type' => $this->config['type'], // _type 在較新的 ES 版本中已棄用
                    ]
                ];
                // 添加實際的日誌文件
                $params['body'][] = $logEntry;
                $logCount++;
            }
        }

        // 僅在有日誌需要發送時才發送
        if ($logCount > 0) {
            try {
                // 使用 bulk API 高效地發送日誌
                $response = $this->client->bulk($params);

                // 可選：檢查回應中是否有錯誤
                if (isset($response['errors']) && $response['errors'] === true) {
                    $this->handleBulkErrors($response);
                }
            } catch (ElasticsearchException $e) {
                // 將 Elasticsearch 錯誤記錄到備用日誌
                $paramsJson = json_encode($params);
                if ($paramsJson === false) {
                    $paramsJson = '[[params 無法被 json_encode 編碼]]';
                }
                $errMsg = ($e instanceof \Throwable) ? $e->getMessage() : (string) $e;
                error_log("ElasticsearchLogDriver: 發送日誌到 Elasticsearch 失敗: " . $errMsg . " Data: " . $paramsJson);
                return false; // 表示失敗
            } catch (\Throwable $e) {
                // 記錄其他潛在錯誤
                error_log("ElasticsearchLogDriver: 發生意外錯誤: " . $e->getMessage());
                return false;
            }
        }

        return true; // 表示日誌已處理 (或嘗試處理)
    }

    /**
     * 格式化日誌訊息。處理陣列或物件。
     *
     * @param mixed $message
     * @return string
     */
    protected function formatMessage($message): string
    {
        if (is_array($message)) {
            // 檢查最後一個元素是否為上下文陣列
            if (isset($message[count($message) - 1]) && is_array($message[count($message) - 1])) {
                // 假設實際訊息是第一個（或多個）元素
                $msgPart = count($message) > 1 ? array_slice($message, 0, -1) : $message[0];
                return $this->convertToString($msgPart);
            } else {
                // 如果最後一個元素不是陣列，則將整個陣列轉換為字串
                return $this->convertToString($message);
            }
        }
        // 對非陣列直接轉換
        return $this->convertToString($message);
    }

    /**
     * 如果訊息陣列的最後一個元素是陣列，則提取它作為上下文。
     *
     * @param mixed $message
     * @return array
     */
    protected function extractContext($message): array
    {
        if (is_array($message)) {
            $lastElement = end($message); // 獲取最後一個元素
            // 檢查最後一個元素是否真的是一個陣列 (上下文)
            if (is_array($lastElement)) {
                return $lastElement;
            }
        }
        return []; // 如果沒有上下文陣列，返回空陣列
    }


    /**
     * 將各種數據類型轉換為 message 欄位的字串。
     *
     * @param mixed $data
     * @return string
     */
    protected function convertToString($data): string
    {
        if (is_string($data)) {
            return $data; // 字串直接返回
        }
        if (is_scalar($data)) {
            return (string) $data; // 純量類型轉為字串
        }
        // 嘗試編碼陣列/物件，並優雅地處理失敗
        // JSON_UNESCAPED_UNICODE: 確保中文字符不被轉義
        // JSON_UNESCAPED_SLASHES: 確保斜線不被轉義
        // JSON_PRETTY_PRINT: 使 JSON 更易讀 (可選)
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '[[無法編碼的數據]]';
    }

    /**
     * 記錄 bulk API 回應中報告的錯誤。
     *
     * @param array $response bulk API 的回應。
     */
    protected function handleBulkErrors(array $response): void
    {
        foreach ($response['items'] ?? [] as $item) {
            $operation = key($item); // 例如：'index'
            if (isset($item[$operation]['error'])) {
                $error = $item[$operation]['error'];
                error_log(sprintf(
                    "ElasticsearchLogDriver: Bulk 操作錯誤。索引: %s, ID: %s, 狀態: %s, 類型: %s, 原因: %s",
                    $item[$operation]['_index'] ?? 'N/A',
                    $item[$operation]['_id'] ?? 'N/A',
                    $item[$operation]['status'] ?? 'N/A',
                    $error['type'] ?? 'N/A',
                    $error['reason'] ?? 'N/A'
                ));
            }
        }
    }
}
