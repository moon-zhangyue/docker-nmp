<?php
// app/controller/RedPacketController.php
namespace app\controller;

use app\service\RedPacketService;
use think\facade\Request;
use think\facade\View;

class RedPacketController
{
    protected $service;

    public function __construct(RedPacketService $service)
    {
        $this->service = $service;
    }
    public function index()
    {
        // 模板输出
        return View::fetch('redpack/index');
    }
    /**
     * 创建红包
     */
    public function create()
    {
        $totalAmount = Request::post('total_amount', 0, 'float');
        $totalCount  = Request::post('total_count', 0, 'int');

        if ($totalAmount <= 0 || $totalCount <= 0) {
            return json(['code' => 0, 'msg' => '参数错误']);
        }

        $redPacketId = $this->service->createRedPacket($totalAmount, $totalCount);
        return json(['code' => 1, 'msg' => '红包创建成功', 'data' => ['red_packet_id' => $redPacketId]]);
    }

    /**
     * 抢红包
     */
    public function grab()
    {
        $redPacketId = Request::post('red_packet_id', 0, 'int');
        $userId      = Request::post('user_id', 0, 'int'); // 假设用户ID从前端传递

        if ($redPacketId <= 0 || $userId <= 0) {
            return json(['code' => 0, 'msg' => '参数错误']);
        }

        $result = $this->service->grabRedPacket($redPacketId, $userId);
        return json($result);
    }
}