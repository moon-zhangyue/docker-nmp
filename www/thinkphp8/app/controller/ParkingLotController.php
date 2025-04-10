<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\ParkingLot;
use think\Request;

/**
 * 停车场管理控制器
 */
class ParkingLotController extends BaseController
{
    /**
     * 获取停车场列表
     * 
     * @param Request $request
     * @return \think\Response
     */
    public function index(Request $request)
    {
        $page   = $request->param('page', 1);
        $limit  = $request->param('limit', 10);
        $status = $request->param('status', '');

        $query = ParkingLot::order('id', 'asc');

        // 按状态筛选
        if ($status !== '') {
            $query->where('status', $status);
        }

        $total       = $query->count();
        $parkingLots = $query->page($page, $limit)->select();

        // 计算占用率
        foreach ($parkingLots as &$lot) {
            $lot->occupancy_rate   = $lot->getOccupancyRate();
            $lot->available_spaces = $lot->getAvailableSpaces();
        }

        return json([
            'code'  => 0,
            'msg'   => 'success',
            'count' => $total,
            'data'  => $parkingLots
        ]);
    }

    /**
     * 获取停车场详情
     * 
     * @param int $id 停车场ID
     * @return \think\Response
     */
    public function detail($id)
    {
        $parkingLot = ParkingLot::find($id);
        if (!$parkingLot) {
            return json(['code' => 1, 'msg' => '停车场不存在']);
        }

        // 计算占用率和可用车位
        $parkingLot->occupancy_rate   = $parkingLot->getOccupancyRate();
        $parkingLot->available_spaces = $parkingLot->getAvailableSpaces();

        return json(['code' => 0, 'msg' => 'success', 'data' => $parkingLot]);
    }

    /**
     * 添加停车场
     * 
     * @param Request $request
     * @return \think\Response
     */
    public function add(Request $request)
    {
        $data = $request->post();

        // 验证数据
        $validate = validate([
            'name'                 => 'require|max:100',
            'address'              => 'max:255',
            'total_spaces'         => 'require|integer|min:0',
            'business_hours_start' => 'date',
            'business_hours_end'   => 'date',
            'contact_person'       => 'max:50',
            'contact_phone'        => 'max:20',
            'status'               => 'in:0,1'
        ]);

        if (!$validate->check($data)) {
            return json(['code' => 1, 'msg' => $validate->getError()]);
        }

        // 检查停车场名称是否已存在
        $exists = ParkingLot::where('name', $data['name'])->find();
        if ($exists) {
            return json(['code' => 1, 'msg' => '停车场名称已存在']);
        }

        // 创建停车场
        $parkingLot = new ParkingLot();
        $parkingLot->save($data);

        return json(['code' => 0, 'msg' => '添加成功', 'data' => $parkingLot]);
    }

    /**
     * 更新停车场信息
     * 
     * @param Request $request
     * @param int $id 停车场ID
     * @return \think\Response
     */
    public function update(Request $request, $id)
    {
        $parkingLot = ParkingLot::find($id);
        if (!$parkingLot) {
            return json(['code' => 1, 'msg' => '停车场不存在']);
        }

        $data = $request->put();

        // 验证数据
        $validate = validate([
            'name'                 => 'max:100',
            'address'              => 'max:255',
            'total_spaces'         => 'integer|min:0',
            'business_hours_start' => 'date',
            'business_hours_end'   => 'date',
            'contact_person'       => 'max:50',
            'contact_phone'        => 'max:20',
            'status'               => 'in:0,1'
        ]);

        if (!$validate->check($data)) {
            return json(['code' => 1, 'msg' => $validate->getError()]);
        }

        // 检查停车场名称是否已存在
        if (isset($data['name']) && $data['name'] != $parkingLot->name) {
            $exists = ParkingLot::where('name', $data['name'])->find();
            if ($exists) {
                return json(['code' => 1, 'msg' => '停车场名称已存在']);
            }
        }

        // 更新停车场信息
        $parkingLot->save($data);

        return json(['code' => 0, 'msg' => '更新成功', 'data' => $parkingLot]);
    }

    /**
     * 删除停车场
     * 
     * @param int $id 停车场ID
     * @return \think\Response
     */
    public function delete($id)
    {
        $parkingLot = ParkingLot::find($id);
        if (!$parkingLot) {
            return json(['code' => 1, 'msg' => '停车场不存在']);
        }

        // 删除停车场
        $parkingLot->delete();

        return json(['code' => 0, 'msg' => '删除成功']);
    }

    /**
     * 更新车位占用情况
     * 
     * @param Request $request
     * @return \think\Response
     */
    public function updateOccupiedSpaces(Request $request)
    {
        $id    = $request->post('id');
        $count = $request->post('occupied_spaces', 0);

        if (empty($id)) {
            return json(['code' => 1, 'msg' => '停车场ID不能为空']);
        }

        $parkingLot = ParkingLot::find($id);
        if (!$parkingLot) {
            return json(['code' => 1, 'msg' => '停车场不存在']);
        }

        // 更新已占用车位数
        $result = $parkingLot->updateOccupiedSpaces($count);
        if (!$result) {
            return json(['code' => 1, 'msg' => '更新失败']);
        }

        return json(['code' => 0, 'msg' => '更新成功', 'data' => $parkingLot]);
    }

    /**
     * 获取停车场状态
     * 
     * @return \think\Response
     */
    public function status()
    {
        $parkingLot = ParkingLot::order('id', 'asc')->find();
        if (!$parkingLot) {
            return json(['code' => 1, 'msg' => '停车场不存在']);
        }

        // 计算占用率和可用车位
        $parkingLot->occupancy_rate   = $parkingLot->getOccupancyRate();
        $parkingLot->available_spaces = $parkingLot->getAvailableSpaces();
        $parkingLot->is_full          = $parkingLot->isFull();

        return json(['code' => 0, 'msg' => 'success', 'data' => $parkingLot]);
    }
}