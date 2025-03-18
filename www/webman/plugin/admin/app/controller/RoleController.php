<?php

namespace plugin\admin\app\controller;

use plugin\admin\app\common\Auth;
use plugin\admin\app\common\Tree;
use plugin\admin\app\model\Role;
use plugin\admin\app\model\Rule;
use support\exception\BusinessException;
use support\Request;
use support\Response;
use Throwable;

/**
 * 角色管理
 */
class RoleController extends Crud
{
    /**
     * 不需要鉴权的方法
     * @var array
     */
    protected $noNeedAuth = ['select'];

    /**
     * @var Role
     */
    protected $model = null;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->model = new Role;
    }

    /**
     * 浏览
     * @return Response
     * @throws Throwable
     */
    public function index(): Response
    {
        return raw_view('role/index');
    }

    /**
     * 查询
     * @param Request $request
     * @return Response
     * @throws BusinessException
     */
    public function select(Request $request): Response
    {
        // 从请求中获取id
        $id = $request->get('id');
        // 解构赋值，获取查询条件、格式化参数、限制数量、字段选择和排序条件
        [$where, $format, $limit, $field, $order] = $this->selectInput($request);
        // 获取当前作用域的角色ID
        $role_ids = Auth::getScopeRoleIds(true);
        // 如果没有提供id，则将查询条件设置为角色ID在范围内的记录
        if (!$id) {
            $where['id'] = ['in', $role_ids];
        } elseif (!in_array($id, $role_ids)) {
            // 如果提供的id不在角色ID范围内，抛出无权限异常
            throw new BusinessException('无权限');
        }
        // 执行查询操作
        $query = $this->doSelect($where, $field, $order);
        // 根据格式化参数和限制数量，对查询结果进行处理
        return $this->doFormat($query, $format, $limit);

    }

    /**
     * 插入
     * @param Request $request
     * @return Response
     * @throws BusinessException
     * @throws Throwable
     */
    public function insert(Request $request): Response
    {
        // 检查请求方法是否为POST
        if ($request->method() === 'POST') {
            // 处理并验证插入请求输入的数据
            $data = $this->insertInput($request);
            $pid = $data['pid'] ?? null; // 尝试获取父级角色组ID

            // 检查是否选择了父级角色组
            if (!$pid) {
                return $this->json(1, '请选择父级角色组');
            }

            // 检查当前用户是否有权限创建指定父级角色组下的角色组
            if (!Auth::isSupperAdmin() && !in_array($pid, Auth::getScopeRoleIds(true))) {
                return $this->json(1, '父级角色组超出权限范围');
            }

            // 检查规则，确保待插入的角色组规则不违反设定
            $this->checkRules($pid, $data['rules'] ?? '');

            // 执行插入操作，并返回插入的结果
            $id = $this->doInsert($data);
            return $this->json(0, 'ok', ['id' => $id]);
        }

        // 如果请求方法不是POST，则返回角色组插入页面的视图
        return raw_view('role/insert');
    }


    /**
     * 更新
     * @param Request $request
     * @return Response
     * @throws BusinessException|Throwable
     */
    public function update(Request $request): Response
    {
        // 根据请求方法决定处理逻辑
        if ($request->method() === 'GET') {
            // 如果是GET请求，返回角色更新视图
            return raw_view('role/update');
        }

        // 处理POST请求，解析输入数据
        [$id, $data] = $this->updateInput($request);
        // 判断当前用户是否为超级管理员
        $is_supper_admin     = Auth::isSupperAdmin();
        // 获取当前用户权限范围内的角色ID
        $descendant_role_ids = Auth::getScopeRoleIds();
        // 检查是否有权限更新角色信息
        if (!$is_supper_admin && !in_array($id, $descendant_role_ids)) {
            return $this->json(1, '无数据权限');
        }

        // 根据ID查找角色
        $role = Role::find($id);
        // 检查角色是否存在
        if (!$role) {
            return $this->json(1, '数据不存在');
        }

        // 检查是否为超级角色，超级角色不允许更改rules和pid字段
        $is_supper_role = $role->rules === '*';
        if ($is_supper_role) {
            unset($data['rules'], $data['pid']);
        }

        // 处理角色父ID的逻辑
        if (key_exists('pid', $data)) {
            $pid = $data['pid'];
            // 检查父ID是否选择，是否为自己，是否超出权限范围
            if (!$pid) {
                return $this->json(1, '请选择父级角色组');
            }
            if ($pid == $id) {
                return $this->json(1, '父级不能是自己');
            }
            if (!$is_supper_admin && !in_array($pid, Auth::getScopeRoleIds(true))) {
                return $this->json(1, '父级超出权限范围');
            }
        } else {
            $pid = $role->pid;
        }

        // 验证角色规则
        if (!$is_supper_role) {
            $this->checkRules($pid, $data['rules'] ?? '');
        }

        // 执行更新操作
        $this->doUpdate($id, $data);

        // 如果不是超级角色，更新所有子角色的规则，删除已不存在的权限
        if (!$is_supper_role) {
            // 构建角色树，获取子角色信息
            $tree                = new Tree(Role::select(['id', 'pid'])->get());
            $descendant_roles    = $tree->getDescendant([$id]);
            $descendant_role_ids = array_column($descendant_roles, 'id');
            $rule_ids            = $data['rules'] ? explode(',', $data['rules']) : [];
            // 遍历子角色，更新规则
            foreach ($descendant_role_ids as $role_id) {
                $tmp_role        = Role::find($role_id);
                $tmp_rule_ids    = $role->getRuleIds();
                $tmp_rule_ids    = array_intersect(explode(',', $tmp_role->rules), $tmp_rule_ids);
                $tmp_role->rules = implode(',', $tmp_rule_ids);
                $tmp_role->save();
            }
        }

        // 返回成功响应
        return $this->json(0);
    }

    /**
     * 删除
     * @param Request $request
     * @return Response
     * @throws BusinessException
     */
    public function delete(Request $request): Response
    {
        $ids = $this->deleteInput($request);
        if (in_array(1, $ids)) {
            return $this->json(1, '无法删除超级管理员角色');
        }
        if (!Auth::isSupperAdmin() && array_diff($ids, Auth::getScopeRoleIds())) {
            return $this->json(1, '无删除权限');
        }
        $tree        = new Tree(Role::get());
        $descendants = $tree->getDescendant($ids);
        if ($descendants) {
            $ids = array_merge($ids, array_column($descendants, 'id'));
        }
        $this->doDelete($ids);
        return $this->json(0);
    }

    /**
     * 获取角色权限
     * @param Request $request
     * @return Response
     */
    public function rules(Request $request): Response
    {
        $role_id = $request->get('id');
        if (empty($role_id)) {
            return $this->json(0, 'ok', []);
        }
        if (!Auth::isSupperAdmin() && !in_array($role_id, Auth::getScopeRoleIds(true))) {
            return $this->json(1, '角色组超出权限范围');
        }
        $rule_id_string = Role::where('id', $role_id)->value('rules');
        if ($rule_id_string === '') {
            return $this->json(0, 'ok', []);
        }
        $rules   = Rule::get();
        $include = [];
        if ($rule_id_string !== '*') {
            $include = explode(',', $rule_id_string);
        }
        $items = [];
        foreach ($rules as $item) {
            $items[] = [
                'name'  => $item->title ?? $item->name ?? $item->id,
                'value' => (string)$item->id,
                'id'    => $item->id,
                'pid'   => $item->pid,
            ];
        }
        $tree = new Tree($items);
        return $this->json(0, 'ok', $tree->getTree($include));
    }

    /**
     * 检查权限字典是否合法
     * @param int $role_id
     * @param $rule_ids
     * @return void
     * @throws BusinessException
     */
    protected function checkRules(int $role_id, $rule_ids)
    {
        if ($rule_ids) {
            $rule_ids = explode(',', $rule_ids);
            if (in_array('*', $rule_ids)) {
                throw new BusinessException('非法数据');
            }
            $rule_exists = Rule::whereIn('id', $rule_ids)->pluck('id');
            if (count($rule_exists) != count($rule_ids)) {
                throw new BusinessException('权限不存在');
            }
            $rule_id_string = Role::where('id', $role_id)->value('rules');
            if ($rule_id_string === '') {
                throw new BusinessException('数据超出权限范围');
            }
            if ($rule_id_string === '*') {
                return;
            }
            $legal_rule_ids = explode(',', $rule_id_string);
            if (array_diff($rule_ids, $legal_rule_ids)) {
                throw new BusinessException('数据超出权限范围');
            }
        }
    }


}
