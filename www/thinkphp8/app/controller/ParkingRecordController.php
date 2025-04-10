<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\ParkingRecord;
use app\model\Vehicle;
use app\model\ParkingLot;
use app\model\ParkingFeeRule;
use think\facade\Db;
use think\Request;

/**
 * 停车记录控制器
 */
class ParkingRecordController extends BaseController
{
    /**
     * 获取停车记录列表
     * 
     * @param Request $request
     * @return \think\Response
     */
    public function index(Request $request)
    {
        $page        = $request->param('page', 1);
        $limit       = $request->param('limit', 10);
        $status      = $request->param('status', '');
        $plateNumber = $request->param('plate_number', '');
        $startTime   = $request->param('start_time', '');
        $endTime     = $request->param('end_time', '');

        $query = ParkingRecord::order('id', 'desc');

        // 按车牌号码筛选
        if (!empty($plateNumber)) {
            $query->where('plate_number', 'like', "%{$plateNumber}%");
        }

        // 按状态筛选
        if ($status !== '') {
            $query->where('status', $status);
        }

        // 按时间范围筛选
        if (!empty($startTime)) {
            $query->where('entry_time', '>=', $startTime);
        }

        if (!empty($endTime)) {
            $query->where('entry_time', '<=', $endTime);
        }

        $total   = $query->count();
        $records = $query->page($page, $limit)->select();

        // 加载关联数据
        $records->load(['vehicle', 'entryDevice', 'exitDevice']);

        return json([
            'code'  => 0,
            'msg'   => 'success',
            'count' => $total,
            'data'  => $records
        ]);
    }

    /**
     * 获取停车记录详情
     * 
     * @param int $id 记录ID
     * @return \think\Response
     */
    public function detail($id)
    {
        $record = ParkingRecord::find($id);
        if (!$record) {
            return json(['code' => 1, 'msg' => '记录不存在']);
        }

        // 加载关联数据
        $record->load(['vehicle', 'entryDevice', 'exitDevice']);

        return json(['code' => 0, 'msg' => 'success', 'data' => $record]);
    }

    /**
     * 根据车牌号查询当前停车记录
     * 
     * @param string $plateNumber 车牌号码
     * @return \think\Response
     */
    public function getByPlateNumber($plateNumber)
    {
        if (empty($plateNumber)) {
            return json(['code' => 1, 'msg' => '车牌号码不能为空']);
        }

        // 查询最近的未完成停车记录
        $record = ParkingRecord::where('plate_number', $plateNumber)
            ->where('status', 'in', [ParkingRecord::STATUS_ENTRY, ParkingRecord::STATUS_EXIT])
            ->order('entry_time', 'desc')
            ->find();

        if (!$record) {
            return json(['code' => 1, 'msg' => '未找到该车辆的停车记录']);
        }

        // 加载关联数据
        $record->load(['vehicle', 'entryDevice', 'exitDevice']);

        // 如果已经出场但未支付，计算费用
        if ($record->status == ParkingRecord::STATUS_EXIT && $record->payment_status == ParkingRecord::PAYMENT_UNPAID) {
            // 计算停车时长
            $duration         = $record->calculateDuration();
            $record->duration = $duration;

            // 计算停车费用
            $fee         = $record->calculateFee();
            $record->fee = $fee;

            $record->save();
        }

        return json(['code' => 0, 'msg' => 'success', 'data' => $record]);
    }

    /**
     * 支付停车费
     * 
     * @param Request $request
     * @return \think\Response
     */
    public function pay(Request $request)
    {
        $id            = $request->post('id');
        $paymentMethod = $request->post('payment_method', '现金');

        if (empty($id)) {
            return json(['code' => 1, 'msg' => '记录ID不能为空']);
        }

        $record = ParkingRecord::find($id);
        if (!$record) {
            return json(['code' => 1, 'msg' => '记录不存在']);
        }

        // 检查是否已支付
        if ($record->payment_status != ParkingRecord::PAYMENT_UNPAID) {
            return json(['code' => 1, 'msg' => '该记录已支付或免费']);
        }

        // 检查是否已出场
        if ($record->status != ParkingRecord::STATUS_EXIT) {
            return json(['code' => 1, 'msg' => '车辆尚未出场，无法支付']);
        }

        // 在实际应用中，这里应该调用支付接口进行实际支付
        // 这里仅作为示例，模拟支付成功

        // 完成支付
        $result = $record->completePayment($paymentMethod);
        if (!$result) {
            return json(['code' => 1, 'msg' => '支付失败']);
        }

        return json(['code' => 0, 'msg' => '支付成功', 'data' => $record]);
    }

    /**
     * 手动添加停车记录
     * 
     * @param Request $request
     * @return \think\Response
     */
    public function add(Request $request)
    {
        $data = $request->post();

        // 验证数据
        $validate = validate([
            'plate_number'    => 'require|max:20',
            'entry_time'      => 'date',
            'entry_device_id' => 'integer'
        ]);

        if (!$validate->check($data)) {
            return json(['code' => 1, 'msg' => $validate->getError()]);
        }

        // 检查车辆是否存在，不存在则创建
        $vehicle = Vehicle::where('plate_number', $data['plate_number'])->find();
        if (!$vehicle) {
            $vehicle               = new Vehicle();
            $vehicle->plate_number = $data['plate_number'];
            $vehicle->type         = Vehicle::TYPE_NORMAL;
            $vehicle->save();
        }

        // 检查是否有未完成的停车记录
        $exists = ParkingRecord::where('plate_number', $data['plate_number'])
            ->where('status', 'in', [ParkingRecord::STATUS_ENTRY, ParkingRecord::STATUS_EXIT])
            ->find();

        if ($exists) {
            return json(['code' => 1, 'msg' => '该车辆已有未完成的停车记录']);
        }

        // 创建停车记录
        $record                  = new ParkingRecord();
        $record->plate_number    = $data['plate_number'];
        $record->entry_time      = $data['entry_time'] ?? date('Y-m-d H:i:s');
        $record->entry_device_id = $data['entry_device_id'] ?? null;
        $record->status          = ParkingRecord::STATUS_ENTRY;
        $record->save();

        // 更新停车场车位信息
        $parkingLot = ParkingLot::order('id', 'asc')->find();
        if ($parkingLot) {
            $parkingLot->incrementOccupiedSpaces();
        }

        return json(['code' => 0, 'msg' => '添加成功', 'data' => $record]);
    }

    /**
     * 手动更新停车记录（出场）
     * 
     * @param Request $request
     * @param int $id 记录ID
     * @return \think\Response
     */
    public function exit(Request $request, $id)
    {
        $record = ParkingRecord::find($id);
        if (!$record) {
            return json(['code' => 1, 'msg' => '记录不存在']);
        }

        // 检查是否已出场
        if ($record->status != ParkingRecord::STATUS_ENTRY) {
            return json(['code' => 1, 'msg' => '该记录已出场或已完成']);
        }

        $data = $request->put();

        // 更新出场信息
        $record->exit_time      = $data['exit_time'] ?? date('Y-m-d H:i:s');
        $record->exit_device_id = $data['exit_device_id'] ?? null;
        $record->status         = ParkingRecord::STATUS_EXIT;

        // 计算停车时长
        $duration         = $record->calculateDuration();
        $record->duration = $duration;

        // 计算停车费用
        $fee         = $record->calculateFee();
        $record->fee = $fee;

        $record->save();

        // 更新停车场车位信息
        $parkingLot = ParkingLot::order('id', 'asc')->find();
        if ($parkingLot) {
            $parkingLot->decrementOccupiedSpaces();
        }

        return json(['code' => 0, 'msg' => '出场成功', 'data' => $record]);
    }

    /**
     * 删除停车记录
     * 
     * @param int $id 记录ID
     * @return \think\Response
     */
    public function delete($id)
    {
        $record = ParkingRecord::find($id);
        if (!$record) {
            return json(['code' => 1, 'msg' => '记录不存在']);
        }

        // 删除记录
        $record->delete();

        return json(['code' => 0, 'msg' => '删除成功']);
    }

    /**
     * 获取停车场统计信息
     * 
     * @return \think\Response
     */
    public function statistics()
    {
        // 获取停车场信息
        $parkingLot = ParkingLot::order('id', 'asc')->find();

        // 今日进场车辆数
        $todayEntryCount = ParkingRecord::whereDay('entry_time')->count();

        // 今日出场车辆数
        $todayExitCount = ParkingRecord::whereDay('exit_time')->count();

        // 今日收入
        $todayIncome = ParkingRecord::whereDay('payment_time')
            ->where('payment_status', ParkingRecord::PAYMENT_PAID)
            ->sum('fee');

        // 当前在场车辆数
        $currentCount = ParkingRecord::where('status', ParkingRecord::STATUS_ENTRY)->count();

        // 统计各类型车辆数量
        $vehicleTypes = Db::name('vehicle')
            ->field('type, COUNT(*) as count')
            ->group('type')
            ->select();

        $data = [
            'parking_lot'       => $parkingLot,
            'today_entry_count' => $todayEntryCount,
            'today_exit_count'  => $todayExitCount,
            'today_income'      => $todayIncome,
            'current_count'     => $currentCount,
            'vehicle_types'     => $vehicleTypes,
            'occupancy_rate'    => $parkingLot ? $parkingLot->getOccupancyRate() : 0
        ];

        return json(['code' => 0, 'msg' => 'success', 'data' => $data]);
    }
}