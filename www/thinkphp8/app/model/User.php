<?php
declare(strict_types=1);

namespace app\model;

use think\Model;
use app\service\ElasticsearchService;

/**
 * 用户模型
 */
class User extends Model
{
    /**
     * 表名
     * 
     * @var string
     */
    protected $name = 'user';

    /**
     * 自动写入时间戳
     * 
     * @var bool
     */
    protected $autoWriteTimestamp = true;

    /**
     * 创建时间字段
     * 
     * @var string
     */
    protected $createTime = 'create_time';

    /**
     * 更新时间字段
     * 
     * @var string
     */
    protected $updateTime = 'update_time';

    /**
     * 隐藏属性
     * 
     * @var array
     */
    protected $hidden = ['password', 'delete_time'];

    /**
     * 用户角色列表
     * 
     * @var array
     */
    public static $roles = [
        'admin'    => '管理员',     // 具有所有权限
        'operator' => '操作员',  // 具有操作权限，可以进行队列操作
        'viewer'   => '查看者'     // 只有查看权限
    ];

    /**
     * 验证用户密码是否正确
     * 
     * @param string $password 明文密码
     * @return bool
     */
    public function validatePassword(string $password): bool
    {
        return password_verify($password, $this->password);
    }

    /**
     * 设置密码
     * 
     * @param string $password 明文密码
     * @return void
     */
    public function setPasswordAttr(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * 获取用户角色标签
     * 
     * @param string $value 角色值
     * @param array $data 行数据
     * @return string
     */
    public function getRoleLabelAttr($value, $data): string
    {
        return self::$roles[$data['role']] ?? '未知角色';
    }

    /**
     * 检查用户是否有指定角色
     * 
     * @param string|array $role 角色或角色数组
     * @return bool
     */
    public function hasRole($role): bool
    {
        // 管理员拥有所有角色权限
        if ($this->role === 'admin') {
            return true;
        }

        if (is_array($role)) {
            return in_array($this->role, $role);
        }

        return $this->role === $role;
    }

    protected static function boot()
    {
        parent::boot();

        // 创建用户时索引到 Elasticsearch
        static::created(function ($user) {
            $esService = new ElasticsearchService();
            $client    = $esService->getClient();
            $params    = [
                'index' => 'users',
                'id'    => $user->id,
                'body'  => [
                    'name'    => $user->name,
                    'email'   => $user->email,
                    'age'     => $user->age,
                    'country' => $user->country,
                ],
            ];
            try {
                $client->index($params);
            } catch (\Exception $e) {
                // 记录错误日志
                \think\facade\Log::error('Elasticsearch index error: ' . $e->getMessage());
            }
        });

        // 更新用户时更新 Elasticsearch 文档
        static::updated(function ($user) {
            $esService = new ElasticsearchService();
            $client    = $esService->getClient();
            $params    = [
                'index' => 'users',
                'id'    => $user->id,
                'body'  => [
                    'doc' => [
                        'name'    => $user->name,
                        'email'   => $user->email,
                        'age'     => $user->age,
                        'country' => $user->country,
                    ],
                ],
            ];
            try {
                $client->update($params);
            } catch (\Exception $e) {
                \think\facade\Log::error('Elasticsearch update error: ' . $e->getMessage());
            }
        });

        // 删除用户时删除 Elasticsearch 文档
        static::deleted(function ($user) {
            $esService = new ElasticsearchService();
            $client    = $esService->getClient();
            $params    = [
                'index' => 'users',
                'id'    => $user->id,
            ];
            try {
                $client->delete($params);
            } catch (\Exception $e) {
                \think\facade\Log::error('Elasticsearch delete error: ' . $e->getMessage());
            }
        });
    }
}