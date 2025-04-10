<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\GateDevice;
use app\model\ParkingRecord;
use app\model\Vehicle;
use app\model\ParkingLot;
use think\facade\Db;
use think\Request;

/**
 * 闸机控制器
 */
class GateController extends BaseController
{
    /**
     * 获取闸机设备列表
     * 
     * @return \think\Response
     */
    public function deviceList()
    {
        $devices = GateDevice::order('id', 'asc')->select();
        return json(['code' => 0, 'msg' => 'success', 'data' => $devices]);
    }

    /**
     * 获取闸机设备详情
     * 
     * @param int $id 设备ID
     * @return \think\Response
     */
    public function deviceDetail($id)
    {
        $device = GateDevice::find($id);
        if (!$device) {
            return json(['code' => 1, 'msg' => '设备不存在']);
        }

        return json(['code' => 0, 'msg' => 'success', 'data' => $device]);
    }

    /**
     * 添加闸机设备
     * 
     * @param Request $request
     * @return \think\Response
     */
    public function addDevice(Request $request)
    {
        $data = $request->post();

        // 验证数据
        $validate = validate([
            'name'       => 'require|max:50',
            'ip_address' => 'require|ip',
            'port'       => 'integer|between:1,65535',
            'location'   => 'max:100',
            'type'       => 'require|in:1,2,3'
        ]);

        if (!$validate->check($data)) {
            return json(['code' => 1, 'msg' => $validate->getError()]);
        }

        // 检查设备名称是否已存在
        $exists = GateDevice::where('name', $data['name'])->find();
        if ($exists) {
            return json(['code' => 1, 'msg' => '设备名称已存在']);
        }

        // 检查IP地址是否已存在
        $exists = GateDevice::where('ip_address', $data['ip_address'])->find();
        if ($exists) {
            return json(['code' => 1, 'msg' => 'IP地址已存在']);
        }

        // 创建设备
        $device = new GateDevice();
        $device->save($data);

        return json(['code' => 0, 'msg' => '添加成功', 'data' => $device]);
    }

    /**
     * 更新闸机设备
     * 
     * @param Request $request
     * @param int $id 设备ID
     * @return \think\Response
     */
    public function updateDevice(Request $request, $id)
    {
        $device = GateDevice::find($id);
        if (!$device) {
            return json(['code' => 1, 'msg' => '设备不存在']);
        }

        $data = $request->put();

        // 验证数据
        $validate = validate([
            'name'       => 'max:50',
            'ip_address' => 'ip',
            'port'       => 'integer|between:1,65535',
            'location'   => 'max:100',
            'type'       => 'in:1,2,3',
            'status'     => 'in:0,1'
        ]);

        if (!$validate->check($data)) {
            return json(['code' => 1, 'msg' => $validate->getError()]);
        }

        // 检查设备名称是否已存在
        if (isset($data['name']) && $data['name'] != $device->name) {
            $exists = GateDevice::where('name', $data['name'])->find();
            if ($exists) {
                return json(['code' => 1, 'msg' => '设备名称已存在']);
            }
        }

        // 检查IP地址是否已存在
        if (isset($data['ip_address']) && $data['ip_address'] != $device->ip_address) {
            $exists = GateDevice::where('ip_address', $data['ip_address'])->find();
            if ($exists) {
                return json(['code' => 1, 'msg' => 'IP地址已存在']);
            }
        }

        // 更新设备
        $device->save($data);

        return json(['code' => 0, 'msg' => '更新成功', 'data' => $device]);
    }

    /**
     * 删除闸机设备
     * 
     * @param int $id 设备ID
     * @return \think\Response
     */
    public function deleteDevice($id)
    {
        $device = GateDevice::find($id);
        if (!$device) {
            return json(['code' => 1, 'msg' => '设备不存在']);
        }

        // 检查设备是否有关联的停车记录
        $count = ParkingRecord::where('entry_device_id', $id)
            ->whereOr('exit_device_id', $id)
            ->count();

        if ($count > 0) {
            return json(['code' => 1, 'msg' => '设备已有关联的停车记录，无法删除']);
        }

        // 删除设备
        $device->delete();

        return json(['code' => 0, 'msg' => '删除成功']);
    }

    /**
     * 开闸
     * 
     * @param Request $request
     * @return \think\Response
     */
    public function openGate(Request $request)
    {
        $deviceId    = $request->post('device_id');
        $plateNumber = $request->post('plate_number');

        if (empty($deviceId)) {
            return json(['code' => 1, 'msg' => '设备ID不能为空']);
        }

        $device = GateDevice::find($deviceId);
        if (!$device) {
            return json(['code' => 1, 'msg' => '设备不存在']);
        }

        if ($device->status == GateDevice::STATUS_OFFLINE) {
            return json(['code' => 1, 'msg' => '设备离线，无法开闸']);
        }

        // 在实际应用中，这里应该调用硬件接口控制闸机开启
        // 这里仅作为示例，模拟开闸操作

        // 记录开闸日志
        $log = [
            'device_id'      => $deviceId,
            'plate_number'   => $plateNumber,
            'operation'      => 'open',
            'operation_time' => date('Y-m-d H:i:s'),
            'operator'       => 'system',
            'result'         => 'success'
        ];

        // 如果是入口闸机，创建停车记录
        if ($device->type == GateDevice::TYPE_ENTRANCE || $device->type == GateDevice::TYPE_BOTH) {
            // 检查车辆是否存在，不存在则创建
            $vehicle = Vehicle::where('plate_number', $plateNumber)->find();
            if (!$vehicle && !empty($plateNumber)) {
                $vehicle               = new Vehicle();
                $vehicle->plate_number = $plateNumber;
                $vehicle->type         = Vehicle::TYPE_NORMAL;
                $vehicle->save();
            }

            // 检查是否有未完成的停车记录
            $record = ParkingRecord::where('plate_number', $plateNumber)
                ->where('status', 'in', [ParkingRecord::STATUS_ENTRY, ParkingRecord::STATUS_EXIT])
                ->find();

            if (!$record && !empty($plateNumber)) {
                // 创建新的停车记录
                $record                  = new ParkingRecord();
                $record->plate_number    = $plateNumber;
                $record->entry_time      = date('Y-m-d H:i:s');
                $record->entry_device_id = $deviceId;
                $record->status          = ParkingRecord::STATUS_ENTRY;
                $record->save();

                // 更新停车场车位信息
                $parkingLot = ParkingLot::order('id', 'asc')->find();
                if ($parkingLot) {
                    $parkingLot->incrementOccupiedSpaces();
                }
            }
        }

        // 如果是出口闸机，更新停车记录
        if ($device->type == GateDevice::TYPE_EXIT || $device->type == GateDevice::TYPE_BOTH) {
            // 查找最近的未完成的停车记录
            $record = ParkingRecord::where('plate_number', $plateNumber)
                ->where('status', ParkingRecord::STATUS_ENTRY)
                ->order('entry_time', 'desc')
                ->find();

            if ($record) {
                // 更新停车记录
                $record->exit_time      = date('Y-m-d H:i:s');
                $record->exit_device_id = $deviceId;
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
            }
        }

        return json(['code' => 0, 'msg' => '开闸成功']);
    }

    /**
     * 关闸
     * 
     * @param Request $request
     * @return \think\Response
     */
    public function closeGate(Request $request)
    {
        $deviceId = $request->post('device_id');

        if (empty($deviceId)) {
            return json(['code' => 1, 'msg' => '设备ID不能为空']);
        }

        $device = GateDevice::find($deviceId);
        if (!$device) {
            return json(['code' => 1, 'msg' => '设备不存在']);
        }

        if ($device->status == GateDevice::STATUS_OFFLINE) {
            return json(['code' => 1, 'msg' => '设备离线，无法关闸']);
        }

        // 在实际应用中，这里应该调用硬件接口控制闸机关闭
        // 这里仅作为示例，模拟关闸操作

        // 记录关闸日志
        $log = [
            'device_id'      => $deviceId,
            'operation'      => 'close',
            'operation_time' => date('Y-m-d H:i:s'),
            'operator'       => 'system',
            'result'         => 'success'
        ];

        return json(['code' => 0, 'msg' => '关闸成功']);
    }

    /**
     * 设备心跳
     * 
     * @param Request $request
     * @return \think\Response
     */
    public function deviceHeartbeat(Request $request)
    {
        $deviceId = $request->post('device_id');
        $status   = $request->post('status', 1);

        if (empty($deviceId)) {
            return json(['code' => 1, 'msg' => '设备ID不能为空']);
        }

        $device = GateDevice::find($deviceId);
        if (!$device) {
            return json(['code' => 1, 'msg' => '设备不存在']);
        }

        // 更新设备状态和心跳时间
        $device->status         = $status;
        $device->last_heartbeat = date('Y-m-d H:i:s');
        $device->save();

        return json(['code' => 0, 'msg' => 'success']);
    }
}