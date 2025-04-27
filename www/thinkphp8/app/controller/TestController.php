<?php

declare(strict_types=1);

namespace app\controller;

use think\facade\Log;
use think\Request;
use app\BaseController;

class TestController extends BaseController
{

    /**
     * 测试Elasticsearch日志记录
     *
     * @return \think\Response
     */
    public function testEsLog(): \think\Response
    {
        // 确保所有值都是标量类型（字符串、数字等），不是数组
        Log::info('这是一条 Info 级别的测试日志。{user_id},{order_id}', ['user_id' => 123, 'order_id' => 'SN20231027']);
        Log::warning('这是一条 Warning 级别的测试日志。', ['context' => '一些警告信息']);
        Log::error('这是一条 Error 级别的测试日志。', ['exception' => '模拟异常信息']);

        // 对于数组类型的值，先转换为JSON字符串
        $dataArray = ['key' => 'value', 'nested' => ['a' => 1, 'b' => 2]];
        Log::debug('这是一条 Debug 级别的测试日志。{data}', ['data' => json_encode($dataArray, JSON_UNESCAPED_UNICODE)]);

        // 记录一个在 apart_level 中定义的级别，例如 error
        Log::log('error', '这是一条独立的 Error 级别日志。{details}', ['details' => '需要独立存储的错误详情']);

        return json([
            'code' => 200,
            'msg'  => '日志已记录，请检查 Elasticsearch 或 Kibana。'
        ]);
    }
}
