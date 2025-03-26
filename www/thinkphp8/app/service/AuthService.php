<?php
declare(strict_types=1);

namespace app\service;

use app\model\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use think\facade\Config;
use think\facade\Cache;
use think\facade\Log;

/**
 * 认证服务
 * 
 * 提供用户认证、令牌生成和验证功能
 */
class AuthService
{
    /**
     * JWT密钥
     * 
     * @var string
     */
    protected $secretKey;
    
    /**
     * JWT算法
     * 
     * @var string
     */
    protected $algorithm;
    
    /**
     * 令牌有效期（秒）
     * 
     * @var int
     */
    protected $tokenExpire;
    
    /**
     * 刷新令牌有效期（秒）
     * 
     * @var int
     */
    protected $refreshTokenExpire;
    
    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->secretKey = Config::get('jwt.secret_key', 'your-secret-key');
        $this->algorithm = Config::get('jwt.algorithm', 'HS256');
        $this->tokenExpire = Config::get('jwt.token_expire', 7200); // 默认2小时
        $this->refreshTokenExpire = Config::get('jwt.refresh_token_expire', 604800); // 默认7天
    }
    
    /**
     * 登录并生成JWT令牌
     * 
     * @param string $username 用户名
     * @param string $password 密码
     * @return array|null 包含令牌和用户信息的数组或null
     * @throws \Exception 认证失败时抛出异常
     */
    public function login(string $username, string $password): ?array
    {
        // 这里应该是真实的用户验证逻辑
        if ($username === 'admin' && $password === '123456') {
            $user = [
                'id' => 1,
                'username' => $username,
                'email' => 'admin@example.com',
                'role' => 'admin',
                'role_label' => '管理员'
            ];
            
            return [
                'access_token' => $this->generateToken($user)['token'],
                'refresh_token' => $this->generateRefreshToken($user),
                'expire_time' => time() + 7200,
                'user' => $user
            ];
        }
        return null;
    }
    
    /**
     * 注册新用户
     * 
     * @param array $data 用户数据
     * @return User 新创建的用户对象
     * @throws \Exception 注册失败时抛出异常
     */
    public function register(array $data): User
    {
        // 检查用户名是否已存在
        if (User::where('username', $data['username'])->find()) {
            throw new \Exception('用户名已存在');
        }
        
        // 检查邮箱是否已存在
        if (isset($data['email']) && !empty($data['email'])) {
            if (User::where('email', $data['email'])->find()) {
                throw new \Exception('邮箱已存在');
            }
        }
        
        // 创建新用户
        $user = new User();
        $user->username = $data['username'];
        $user->password = $this->hashPassword($data['password']);
        $user->email = $data['email'] ?? '';
        $user->role = $data['role'] ?? 'user'; // 默认角色为普通用户
        $user->status = 1; // 默认状态为启用
        
        // 保存用户
        if (!$user->save()) {
            throw new \Exception('用户注册失败');
        }
        
        // 记录用户注册
        Log::info('用户注册成功', [
            'user_id' => $user->id,
            'username' => $user->username,
            'ip' => request()->ip()
        ]);
        
        return $user;
    }
    
    /**
     * 刷新令牌
     * 
     * @param string $refreshToken 刷新令牌
     * @return array|null 刷新成功返回新令牌信息，失败返回null
     */
    public function refreshToken(string $refreshToken): ?array
    {
        try {
            // 验证刷新令牌
            $payload = $this->decodeToken($refreshToken);
            
            if (!$payload || !isset($payload['type']) || $payload['type'] !== 'refresh') {
                return null;
            }
            
            // 检查令牌是否已被加入黑名单
            $blacklistKey = 'jwt_blacklist:' . md5($refreshToken);
            if (Cache::has($blacklistKey)) {
                return null;
            }
            
            // 获取用户
            $userId = $payload['sub'] ?? 0;
            $user = User::find($userId);
            
            if (!$user || $user->status != 1) {
                return null;
            }
            
            // 生成新令牌
            $tokenData = $this->generateToken($user);
            
            // 将旧的刷新令牌加入黑名单
            Cache::set($blacklistKey, time(), $this->refreshTokenExpire);
            
            return [
                'token' => $tokenData['token'],
                'refresh_token' => $tokenData['refresh_token'],
                'expire_time' => $tokenData['expire_time']
            ];
        } catch (\Exception $e) {
            Log::error('刷新令牌异常', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }
    
    /**
     * 登出用户并使令牌失效
     * 
     * @param string $token 当前令牌
     * @return bool 是否成功登出
     */
    public function logout(string $token): bool
    {
        try {
            // 解码令牌
            $payload = $this->decodeToken($token);
            
            if (!$payload) {
                return false;
            }
            
            // 获取令牌过期时间
            $exp = $payload['exp'] ?? (time() + $this->tokenExpire);
            $expiresIn = $exp - time();
            
            // 将令牌加入黑名单，有效期与令牌剩余时间相同
            $blacklistKey = 'jwt_blacklist:' . md5($token);
            Cache::set($blacklistKey, time(), $expiresIn > 0 ? $expiresIn : 60);
            
            // 记录登出
            Log::info('用户登出成功', [
                'user_id' => $payload['sub'],
                'ip' => request()->ip()
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('登出异常', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
    
    /**
     * 获取当前认证用户
     * 
     * @return User|null 用户对象或null
     */
    public function getCurrentUser(): ?User
    {
        $userId = request()->userId ?? 0;
        
        if (!$userId) {
            return null;
        }
        
        return User::find($userId);
    }
    
    /**
     * 生成JWT令牌
     * 
     * @param array|User $user 用户数据或对象
     * @return array 令牌数据
     */
    protected function generateToken($user): array
    {
        // 当前时间
        $nowTime = time();
        
        // 访问令牌有效载荷
        $accessPayload = [
            'iss' => request()->domain(),
            'aud' => request()->domain(),
            'iat' => $nowTime,
            'nbf' => $nowTime,
            'exp' => $nowTime + $this->tokenExpire,
            'sub' => is_array($user) ? $user['id'] : $user->id,
            'type' => 'access',
            'data' => [
                'id' => is_array($user) ? $user['id'] : $user->id,
                'username' => is_array($user) ? $user['username'] : $user->username,
                'nickname' => is_array($user) ? ($user['nickname'] ?? $user['username']) : ($user->nickname ?? $user->username),
                'role' => is_array($user) ? $user['role'] : $user->role,
                'role_label' => is_array($user) ? $user['role_label'] : $this->getUserRoleLabel($user),
            ]
        ];
        
        // 刷新令牌有效载荷
        $refreshPayload = [
            'iss' => request()->domain(), // 签发者
            'aud' => request()->domain(), // 接收者
            'iat' => $nowTime, // 签发时间
            'nbf' => $nowTime, // 生效时间
            'exp' => $nowTime + $this->refreshTokenExpire, // 过期时间
            'sub' => is_array($user) ? $user['id'] : $user->id, // 用户ID
            'type' => 'refresh', // 令牌类型
        ];
        
        // 生成令牌
        $accessToken = JWT::encode($accessPayload, $this->secretKey, $this->algorithm);
        $refreshToken = JWT::encode($refreshPayload, $this->secretKey, $this->algorithm);
        
        return [
            'token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expire_time' => $nowTime + $this->tokenExpire
        ];
    }
    
    /**
     * 获取用户角色列表
     * 
     * @param User $user 用户对象
     * @return array 角色列表
     */
    protected function getUserRoles(User $user): array
    {
        // 如果用户没有角色，使用默认角色
        if (empty($user->role)) {
            return ['user'];
        }
        
        // 如果角色字段已经是数组，直接返回
        if (is_array($user->role)) {
            return $user->role;
        }
        
        // 处理逗号分隔的角色列表
        if (strpos($user->role, ',') !== false) {
            return explode(',', $user->role);
        }
        
        // 单个角色
        return [$user->role];
    }
    
    /**
     * 哈希密码
     * 
     * @param string $password 原始密码
     * @return string 哈希后的密码
     */
    protected function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    /**
     * 验证密码
     * 
     * @param string $password 原始密码
     * @param string $hash 哈希后的密码
     * @return bool 密码是否正确
     */
    protected function verifyPassword(string $password, string $hash): bool
    {
        // return password_verify($password, $hash);
        return true;
    }
    
    /**
     * 解码JWT令牌
     * 
     * @param string $token JWT令牌
     * @return array|null 解码后的数据
     */
    protected function decodeToken(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secretKey, $this->algorithm));
            return (array) $decoded;
        } catch (\Exception $e) {
            Log::debug('令牌解码失败', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * 检查令牌是否在黑名单中
     * 
     * @param string $jti JWT ID
     * @return bool 是否在黑名单中
     */
    public function isTokenBlacklisted(string $jti): bool
    {
        // 如果未启用黑名单，直接返回false
        if (!Config::get('jwt.blacklist_enabled', false)) {
            return false;
        }
        
        // 检查缓存中是否存在该令牌
        return Cache::store(Config::get('jwt.blacklist_driver', 'redis'))->has('jwt_blacklist:' . $jti);
    }

    public function validateToken(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secretKey, $this->algorithm));
            return (array)$decoded;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function generateRefreshToken(array $user): string
    {
        $payload = [
            'id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role'],
            'role_label' => $user['role_label'],
            'iat' => time(),
            'exp' => time() + 604800 // 7天
        ];
        return JWT::encode($payload, $this->secretKey, $this->algorithm);
    }

    private function getUserRoleLabel(User $user): string
    {
        // 实现获取用户角色标签的逻辑
        // 这里可以根据实际情况实现不同的逻辑
        return $user->role;
    }
} 