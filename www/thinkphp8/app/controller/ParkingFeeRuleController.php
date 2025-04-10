<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\ParkingFeeRule;
use think\Request;

/**
 * 停车场收费规则控制器
 */
class ParkingFeeRuleController extends BaseController
{
    /**
     * 获取收费规则列表
     * 
     * @param Request $request
     * @return \think\Response
     */
    public function index(Request $request)
    {
        $page   = $request->param('page', 1);
        $limit  = $request->param('limit', 10);
        $type   = $request->param('type', '');
        $status = $request->param('status', '');

        $query = ParkingFeeRule::order('id', 'asc');

        // 按类型筛选
        if ($type !== '') {
            $query->where('type', $type);
        }

        // 按状态筛选
        if ($status !== '') {
            $query->where('status', $status);
        }

        $total = $query->count();
        $rules = $query->page($page, $limit)->select();

        return json([
            'code'  => 0,
            'msg'   => 'success',
            'count' => $total,
            'data'  => $rules
        ]);
    }

    /**
     * 获取收费规则详情
     * 
     * @param int $id 规则ID
     * @return \think\Response
     */
    public function detail($id)
    {
        $rule = ParkingFeeRule::find($id);
        if (!$rule) {
            return json(['code' => 1, 'msg' => '规则不存在']);
        }

        return json(['code' => 0, 'msg' => 'success', 'data' => $rule]);
    }

    /**
     * 添加收费规则
     * 
     * @param Request $request
     * @return \think\Response
     */
    public function add(Request $request)
    {
        $data = $request->post();

        // 验证数据
        $validate = validate([
            'name'            => 'require|max:50',
            'type'            => 'require|in:1,2,3',
            'fee_per_hour'    => 'float',
            'fixed_fee'       => 'float',
            'free_minutes'    => 'integer',
            'max_fee_per_day' => 'float',
            'status'          => 'in:0,1'
        ]);

        if (!$validate->check($data)) {
            return json(['code' => 1, 'msg' => $validate->getError()]);
        }

        // 检查规则名称是否已存在
        $exists = ParkingFeeRule::where('name', $data['name'])->find();
        if ($exists) {
            return json(['code' => 1, 'msg' => '规则名称已存在']);
        }

        // 处理阶梯收费规则
        if (isset($data['type']) && $data['type'] == ParkingFeeRule::TYPE_TIERED) {
            $tieredRules = $request->post('tiered_rules', []);
            if (!empty($tieredRules)) {
                $data['tiered_rules'] = json_encode($tieredRules);
            }
        }

        // 创建规则
        $rule = new ParkingFeeRule();
        $rule->save($data);

        return json(['code' => 0, 'msg' => '添加成功', 'data' => $rule]);
    }

    /**
     * 更新收费规则
     * 
     * @param Request $request
     * @param int $id 规则ID
     * @return \think\Response
     */
    public function update(Request $request, $id)
    {
        $rule = ParkingFeeRule::find($id);
        if (!$rule) {
            return json(['code' => 1, 'msg' => '规则不存在']);
        }

        $data = $request->put();

        // 验证数据
        $validate = validate([
            'name'            => 'max:50',
            'type'            => 'in:1,2,3',
            'fee_per_hour'    => 'float',
            'fixed_fee'       => 'float',
            'free_minutes'    => 'integer',
            'max_fee_per_day' => 'float',
            'status'          => 'in:0,1'
        ]);

        if (!$validate->check($data)) {
            return json(['code' => 1, 'msg' => $validate->getError()]);
        }

        // 检查规则名称是否已存在
        if (isset($data['name']) && $data['name'] != $rule->name) {
            $exists = ParkingFeeRule::where('name', $data['name'])->find();
            if ($exists) {
                return json(['code' => 1, 'msg' => '规则名称已存在']);
            }
        }

        // 处理阶梯收费规则
        if (isset($data['type']) && $data['type'] == ParkingFeeRule::TYPE_TIERED) {
            $tieredRules = $request->put('tiered_rules', []);
            if (!empty($tieredRules)) {
                $data['tiered_rules'] = json_encode($tieredRules);
            }
        }

        // 更新规则
        $rule->save($data);

        return json(['code' => 0, 'msg' => '更新成功', 'data' => $rule]);
    }

    /**
     * 删除收费规则
     * 
     * @param int $id 规则ID
     * @return \think\Response
     */
    public function delete($id)
    {
        $rule = ParkingFeeRule::find($id);
        if (!$rule) {
            return json(['code' => 1, 'msg' => '规则不存在']);
        }

        // 删除规则
        $rule->delete();

        return json(['code' => 0, 'msg' => '删除成功']);
    }

    /**
     * 启用/禁用收费规则
     * 
     * @param Request $request
     * @return \think\Response
     */
    public function toggleStatus(Request $request)
    {
        $id     = $request->post('id');
        $status = $request->post('status');

        if (empty($id)) {
            return json(['code' => 1, 'msg' => '规则ID不能为空']);
        }

        if (!in_array($status, [0, 1])) {
            return json(['code' => 1, 'msg' => '状态值无效']);
        }

        $rule = ParkingFeeRule::find($id);
        if (!$rule) {
            return json(['code' => 1, 'msg' => '规则不存在']);
        }

        // 更新状态
        $rule->status = $status;
        $rule->save();

        return json(['code' => 0, 'msg' => '操作成功', 'data' => $rule]);
    }

    /**
     * 计算停车费用
     * 
     * @param Request $request
     * @return \think\Response
     */
    public function calculateFee(Request $request)
    {
        $duration    = $request->post('duration', 0); // 停车时长（分钟）
        $vehicleType = $request->post('vehicle_type', 1); // 车辆类型
        $ruleId      = $request->post('rule_id'); // 规则ID

        if (empty($ruleId)) {
            return json(['code' => 1, 'msg' => '规则ID不能为空']);
        }

        $rule = ParkingFeeRule::find($ruleId);
        if (!$rule) {
            return json(['code' => 1, 'msg' => '规则不存在']);
        }

        // 计算费用
        $fee = $rule->calculateFee($duration, $vehicleType);

        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => [
                'duration'     => $duration,
                'vehicle_type' => $vehicleType,
                'rule_id'      => $ruleId,
                'rule_name'    => $rule->name,
                'fee'          => $fee
            ]
        ]);
    }
}