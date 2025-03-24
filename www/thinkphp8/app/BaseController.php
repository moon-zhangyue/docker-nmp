<?php
declare (strict_types = 1);

namespace app;

use think\App;
use think\exception\ValidateException;
use think\Response;
use think\Validate;

/**
 * 控制器基础类
 */
abstract class BaseController
{
    /**
     * Request实例
     * @var \think\Request
     */
    protected $request;

    /**
     * 应用实例
     * @var \think\App
     */
    protected $app;

    /**
     * 是否批量验证
     * @var bool
     */
    protected $batchValidate = false;

    /**
     * 控制器中间件
     * @var array
     */
    protected $middleware = [];

    /**
     * 构造方法
     * @access public
     * @param  App  $app  应用对象
     */
    public function __construct(App $app)
    {
        $this->app     = $app;
        $this->request = $this->app->request;

        // 控制器初始化
        $this->initialize();
    }

    // 初始化
    protected function initialize()
    {}

    /**
     * 验证数据
     * @access protected
     * @param  array        $data     数据
     * @param  string|array $validate 验证器名或者验证规则数组
     * @param  array        $message  提示信息
     * @param  bool         $batch    是否批量验证
     * @return array|string|true
     * @throws ValidateException
     */
    protected function validate(array $data, string|array $validate, array $message = [], bool $batch = false)
    {
        if (is_array($validate)) {
            $v = new Validate();
            $v->rule($validate);
        } else {
            if (strpos($validate, '.')) {
                // 支持场景
                [$validate, $scene] = explode('.', $validate);
            }
            $class = false !== strpos($validate, '\\') ? $validate : $this->app->parseClass('validate', $validate);
            $v     = new $class();
            if (!empty($scene)) {
                $v->scene($scene);
            }
        }

        $v->message($message);

        // 是否批量验证
        if ($batch || $this->batchValidate) {
            $v->batch(true);
        }

        return $v->failException(true)->check($data);
    }

    /**
     * 返回成功响应
     * 
     * @param string $message 成功信息
     * @param array $data 数据
     * @return Response
     */
    protected function success(string $message, array $data = []): Response
    {
        return json([
            'code' => 200,
            'message' => $message,
            'data' => $data
        ]);
    }
    
    /**
     * 返回错误响应
     * 
     * @param string $message 错误信息
     * @param int $code 错误码
     * @param array $data 数据
     * @return Response
     */
    protected function error(string $message, int $code = 400, array $data = []): Response
    {
        return json([
            'code' => $code,
            'message' => $message,
            'data' => $data
        ]);
    }
    
    /**
     * 检查用户是否具有指定角色
     * 
     * @param string|array $roles 角色或角色数组
     * @return bool
     */
    protected function hasRole($roles): bool
    {
        // 如果未设置用户或角色，则没有权限
        if (!isset($this->request->user) || !isset($this->request->user['role'])) {
            return false;
        }
        
        $userRole = $this->request->user['role'];
        
        // 如果用户是管理员，拥有所有权限
        if ($userRole === 'admin') {
            return true;
        }
        
        // 检查用户是否拥有指定角色之一
        $requiredRoles = is_array($roles) ? $roles : [$roles];
        
        return in_array($userRole, $requiredRoles);
    }
}
