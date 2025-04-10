<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\Vehicle;
use app\model\MonthlyPass;
use app\model\ParkingRecord;
use think\facade\Db;
use think\Request;

/**
 * 车辆管理控制器
 */
class VehicleController extends BaseController
{
    /**
     * 获取车辆列表
     * 
     * @param Request $request
     * @return \think\Response
     */
    public function index(Request $request)
    {
        $page        = $request->param('page', 1);
        $limit       = $request->param('limit', 10);
        $type        = $request->param('type', '');
        $plateNumber = $request->param('plate_number', '');
        $ownerName   = $request->param('owner_name', '');

        $query = Vehicle::order('id', 'desc');

        // 按车牌号码筛选
        if (!empty($plateNumber)) {
            $query->where('plate_number', 'like', "%{$plateNumber}%");
        }

        // 按车主姓名筛选
        if (!empty($ownerName)) {
            $query->where('owner_name', 'like', "%{$ownerName}%");
        }

        // 按类型筛选
        if ($type !== '') {
            $query->where('type', $type);
        }

        $total    = $query->count();
        $vehicles = $query->page($page, $limit)->select();

        // 加载月租信息
        $vehicles->load(['monthlyPass']);

        return json([
            'code'  => 0,
            'msg'   => 'success',
            'count' => $total,
            'data'  => $vehicles
        ]);
    }

    /**
     * 获取车辆详情
     * 
     * @param string $plateNumber 车牌号码
     * @return \think\Response
     */
    public function detail($plateNumber)
    {
        if (empty($plateNumber)) {
            return json(['code' => 1, 'msg' => '车牌号码不能为空']);
        }

        $vehicle = Vehicle::where('plate_number', $plateNumber)->find();
        if (!$vehicle) {
            return json(['code' => 1, 'msg' => '车辆不存在']);
        }

        // 加载月租信息
        $vehicle->load(['monthlyPass']);

        // 获取最近的停车记录
        $records = ParkingRecord::where('plate_number', $plateNumber)
            ->order('entry_time', 'desc')
            ->limit(5)
            ->select();

        $data                   = $vehicle->toArray();
        $data['recent_records'] = $records;

        return json(['code' => 0, 'msg' => 'success', 'data' => $data]);
    }

    /**
     * 添加车辆
     * 
     * @param Request $request
     * @return \think\Response
     */
    public function add(Request $request)
    {
        $data = $request->post();

        // 验证数据
        $validate = validate([
            'plate_number'  => 'require|max:20',
            'owner_name'    => 'max:50',
            'owner_phone'   => 'max:20',
            'vehicle_brand' => 'max:50',
            'vehicle_color' => 'max:20',
            'type'          => 'in:1,2,3,4'
        ]);

        if (!$validate->check($data)) {
            return json(['code' => 1, 'msg' => $validate->getError()]);
        }

        // 检查车牌号是否已存在
        $exists = Vehicle::where('plate_number', $data['plate_number'])->find();
        if ($exists) {
            return json(['code' => 1, 'msg' => '车牌号已存在']);
        }

        // 创建车辆
        $vehicle = new Vehicle();
        $vehicle->save($data);

        // 如果是月租车辆，创建月租信息
        if (isset($data['type']) && $data['type'] == Vehicle::TYPE_MONTHLY) {
            $monthlyData = $request->post('monthly', []);
            if (!empty($monthlyData)) {
                $monthly                 = new MonthlyPass();
                $monthly->plate_number   = $data['plate_number'];
                $monthly->start_date     = $monthlyData['start_date'] ?? date('Y-m-d');
                $monthly->end_date       = $monthlyData['end_date'] ?? date('Y-m-d', strtotime('+1 month'));
                $monthly->fee            = $monthlyData['fee'] ?? 0;
                $monthly->status         = 1;
                $monthly->payment_method = $monthlyData['payment_method'] ?? '现金';
                $monthly->payment_time   = date('Y-m-d H:i:s');
                $monthly->remark         = $monthlyData['remark'] ?? '';
                $monthly->save();
            }
        }

        return json(['code' => 0, 'msg' => '添加成功', 'data' => $vehicle]);
    }

    /**
     * 更新车辆信息
     * 
     * @param Request $request
     * @param string $plateNumber 车牌号码
     * @return \think\Response
     */
    public function update(Request $request, $plateNumber)
    {
        if (empty($plateNumber)) {
            return json(['code' => 1, 'msg' => '车牌号码不能为空']);
        }

        $vehicle = Vehicle::where('plate_number', $plateNumber)->find();
        if (!$vehicle) {
            return json(['code' => 1, 'msg' => '车辆不存在']);
        }

        $data = $request->put();

        // 验证数据
        $validate = validate([
            'owner_name'    => 'max:50',
            'owner_phone'   => 'max:20',
            'vehicle_brand' => 'max:50',
            'vehicle_color' => 'max:20',
            'type'          => 'in:1,2,3,4'
        ]);

        if (!$validate->check($data)) {
            return json(['code' => 1, 'msg' => $validate->getError()]);
        }

        // 不允许修改车牌号
        if (isset($data['plate_number'])) {
            unset($data['plate_number']);
        }

        // 更新车辆信息
        $vehicle->save($data);

        // 如果类型改为月租车辆，且没有月租信息，则创建
        if (isset($data['type']) && $data['type'] == Vehicle::TYPE_MONTHLY) {
            $monthly = MonthlyPass::where('plate_number', $plateNumber)->find();
            if (!$monthly) {
                $monthlyData = $request->put('monthly', []);
                if (!empty($monthlyData)) {
                    $monthly                 = new MonthlyPass();
                    $monthly->plate_number   = $plateNumber;
                    $monthly->start_date     = $monthlyData['start_date'] ?? date('Y-m-d');
                    $monthly->end_date       = $monthlyData['end_date'] ?? date('Y-m-d', strtotime('+1 month'));
                    $monthly->fee            = $monthlyData['fee'] ?? 0;
                    $monthly->status         = 1;
                    $monthly->payment_method = $monthlyData['payment_method'] ?? '现金';
                    $monthly->payment_time   = date('Y-m-d H:i:s');
                    $monthly->remark         = $monthlyData['remark'] ?? '';
                    $monthly->save();
                }
            }
        }

        // 如果类型从月租车辆改为其他类型，则禁用月租信息
        if (isset($data['type']) && $data['type'] != Vehicle::TYPE_MONTHLY) {
            $monthly = MonthlyPass::where('plate_number', $plateNumber)->find();
            if ($monthly) {
                $monthly->status = 0;
                $monthly->save();
            }
        }

        return json(['code' => 0, 'msg' => '更新成功', 'data' => $vehicle]);
    }

    /**
     * 删除车辆
     * 
     * @param string $plateNumber 车牌号码
     * @return \think\Response
     */
    public function delete($plateNumber)
    {
        if (empty($plateNumber)) {
            return json(['code' => 1, 'msg' => '车牌号码不能为空']);
        }

        $vehicle = Vehicle::where('plate_number', $plateNumber)->find();
        if (!$vehicle) {
            return json(['code' => 1, 'msg' => '车辆不存在']);
        }

        // 检查是否有关联的停车记录
        $count = ParkingRecord::where('plate_number', $plateNumber)->count();
        if ($count > 0) {
            return json(['code' => 1, 'msg' => '该车辆有关联的停车记录，无法删除']);
        }

        // 删除月租信息
        $monthly = MonthlyPass::where('plate_number', $plateNumber)->find();
        if ($monthly) {
            $monthly->delete();
        }

        // 删除车辆
        $vehicle->delete();

        return json(['code' => 0, 'msg' => '删除成功']);
    }

    /**
     * 月租车辆管理
     */

    /**
     * 获取月租车辆列表
     * 
     * @param Request $request
     * @return \think\Response
     */
    public function monthlyList(Request $request)
    {
        $page        = $request->param('page', 1);
        $limit       = $request->param('limit', 10);
        $plateNumber = $request->param('plate_number', '');
        $status      = $request->param('status', '');

        $query = MonthlyPass::with(['vehicle'])->order('id', 'desc');

        // 按车牌号码筛选
        if (!empty($plateNumber)) {
            $query->where('plate_number', 'like', "%{$plateNumber}%");
        }

        // 按状态筛选
        if ($status !== '') {
            $query->where('status', $status);
        }

        $total = $query->count();
        $list  = $query->page($page, $limit)->select();

        // 计算剩余天数
        foreach ($list as &$item) {
            $item->remaining_days = $item->getRemainingDays();
        }

        return json([
            'code'  => 0,
            'msg'   => 'success',
            'count' => $total,
            'data'  => $list
        ]);
    }

    /**
     * 月租续费
     * 
     * @param Request $request
     * @return \think\Response
     */
    public function renewMonthly(Request $request)
    {
        $plateNumber   = $request->post('plate_number');
        $months        = $request->post('months', 1);
        $fee           = $request->post('fee', 0);
        $paymentMethod = $request->post('payment_method', '现金');

        if (empty($plateNumber)) {
            return json(['code' => 1, 'msg' => '车牌号码不能为空']);
        }

        $monthly = MonthlyPass::where('plate_number', $plateNumber)->find();
        if (!$monthly) {
            return json(['code' => 1, 'msg' => '月租信息不存在']);
        }

        // 续费
        $result = $monthly->renew($months);
        if (!$result) {
            return json(['code' => 1, 'msg' => '续费失败']);
        }

        // 更新费用和支付信息
        $monthly->fee            = $fee;
        $monthly->payment_method = $paymentMethod;
        $monthly->payment_time   = date('Y-m-d H:i:s');
        $monthly->save();

        return json(['code' => 0, 'msg' => '续费成功', 'data' => $monthly]);
    }

    /**
     * 添加月租
     * 
     * @param Request $request
     * @return \think\Response
     */
    public function addMonthly(Request $request)
    {
        $data = $request->post();

        // 验证数据
        $validate = validate([
            'plate_number' => 'require|max:20',
            'start_date'   => 'date',
            'end_date'     => 'date',
            'fee'          => 'float'
        ]);

        if (!$validate->check($data)) {
            return json(['code' => 1, 'msg' => $validate->getError()]);
        }

        // 检查车辆是否存在
        $vehicle = Vehicle::where('plate_number', $data['plate_number'])->find();
        if (!$vehicle) {
            // 创建车辆
            $vehicle               = new Vehicle();
            $vehicle->plate_number = $data['plate_number'];
            $vehicle->type         = Vehicle::TYPE_MONTHLY;
            $vehicle->save();
        } else {
            // 更新车辆类型为月租
            $vehicle->type = Vehicle::TYPE_MONTHLY;
            $vehicle->save();
        }

        // 检查月租信息是否已存在
        $exists = MonthlyPass::where('plate_number', $data['plate_number'])->find();
        if ($exists) {
            return json(['code' => 1, 'msg' => '该车辆已有月租信息']);
        }

        // 创建月租信息
        $monthly                 = new MonthlyPass();
        $monthly->plate_number   = $data['plate_number'];
        $monthly->start_date     = $data['start_date'] ?? date('Y-m-d');
        $monthly->end_date       = $data['end_date'] ?? date('Y-m-d', strtotime('+1 month'));
        $monthly->fee            = $data['fee'] ?? 0;
        $monthly->status         = 1;
        $monthly->payment_method = $data['payment_method'] ?? '现金';
        $monthly->payment_time   = date('Y-m-d H:i:s');
        $monthly->remark         = $data['remark'] ?? '';
        $monthly->save();

        return json(['code' => 0, 'msg' => '添加成功', 'data' => $monthly]);
    }
}