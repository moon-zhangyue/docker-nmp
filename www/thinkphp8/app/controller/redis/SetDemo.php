<?php
declare(strict_types=1);

namespace app\controller\redis;

use app\controller\RedisDemo;
use app\facade\Redis;
use think\facade\View;

/**
 * Redis Set类型演示控制器
 * 
 * 演示Redis Set类型的常见应用场景
 */
class SetDemo extends RedisDemo
{
    /**
     * 演示页面
     */
    public function index()
    {
        return View::fetch('redis/set/index');
    }
    
    /**
     * 基本用法示例
     */
    public function basic()
    {
        try {
            $redis = Redis::set();
            $key = 'set_demo_basic';
            
            // 清空之前的测试数据
            $redis->delete($key);
            
            // 添加元素
            $redis->sAdd($key, 'value1');
            $redis->sAdd($key, 'value2');
            $redis->sAdd($key, 'value3');
            $redis->sAdd($key, 'value1'); // 重复添加，将被忽略
            
            // 获取所有元素
            $members = $redis->sMembers($key);
            
            // 获取集合大小
            $size = $redis->sCard($key);
            
            // 判断元素是否存在
            $exists1 = $redis->sIsMember($key, 'value1');
            $exists4 = $redis->sIsMember($key, 'value4');
            
            // 随机获取元素
            $random = $redis->sRandMember($key);
            
            // 移除元素
            $redis->sRem($key, 'value3');
            $membersAfterRemove = $redis->sMembers($key);
            
            // 弹出一个元素
            $popped = $redis->sPop($key);
            $membersAfterPop = $redis->sMembers($key);
            
            return $this->success('Set基本用法演示', [
                'members' => $members,
                'size' => $size,
                'exists_value1' => $exists1,
                'exists_value4' => $exists4,
                'random_member' => $random,
                'members_after_remove' => $membersAfterRemove,
                'popped_member' => $popped,
                'members_after_pop' => $membersAfterPop,
            ]);
        } catch (\Throwable $e) {
            return $this->error('Set基本用法演示失败：' . $e->getMessage());
        }
    }
    
    /**
     * 集合运算示例
     */
    public function setOperations()
    {
        try {
            $redis = Redis::set();
            
            // 创建两个测试集合
            $key1 = 'set_demo_ops_1';
            $key2 = 'set_demo_ops_2';
            
            // 清空之前的测试数据
            $redis->delete($key1);
            $redis->delete($key2);
            
            // 添加元素到集合1
            $redis->sAdd($key1, 'A', 'B', 'C', 'D');
            
            // 添加元素到集合2
            $redis->sAdd($key2, 'C', 'D', 'E', 'F');
            
            // 并集
            $union = $redis->sUnion($key1, $key2);
            
            // 交集
            $inter = $redis->sInter($key1, $key2);
            
            // 差集 (key1 - key2)
            $diff1 = $redis->sDiff($key1, $key2);
            
            // 差集 (key2 - key1)
            $diff2 = $redis->sDiff($key2, $key1);
            
            return $this->success('Set集合运算演示', [
                'set1' => $redis->sMembers($key1),
                'set2' => $redis->sMembers($key2),
                'union' => $union,
                'intersection' => $inter,
                'difference_1_2' => $diff1,
                'difference_2_1' => $diff2,
            ]);
        } catch (\Throwable $e) {
            return $this->error('Set集合运算演示失败：' . $e->getMessage());
        }
    }
    
    /**
     * 标签系统示例
     */
    public function tagSystem()
    {
        try {
            $redis = Redis::set();
            $action = $this->request->param('action', 'list');
            $itemId = $this->request->param('item_id', 0, 'intval');
            $tag = $this->request->param('tag', '');
            
            switch ($action) {
                case 'add':
                    // 为项目添加标签
                    if ($itemId > 0 && !empty($tag)) {
                        // 添加项目->标签的映射
                        $redis->sAdd("item:{$itemId}:tags", $tag);
                        
                        // 添加标签->项目的映射
                        $redis->sAdd("tag:{$tag}:items", $itemId);
                        
                        $result = [
                            'status' => 'success',
                            'message' => "标签 {$tag} 已添加到项目 {$itemId}",
                        ];
                    } else {
                        $result = [
                            'status' => 'error',
                            'message' => '项目ID和标签不能为空',
                        ];
                    }
                    break;
                    
                case 'remove':
                    // 移除项目的标签
                    if ($itemId > 0 && !empty($tag)) {
                        // 移除项目->标签的映射
                        $redis->sRem("item:{$itemId}:tags", $tag);
                        
                        // 移除标签->项目的映射
                        $redis->sRem("tag:{$tag}:items", $itemId);
                        
                        $result = [
                            'status' => 'success',
                            'message' => "标签 {$tag} 已从项目 {$itemId} 移除",
                        ];
                    } else {
                        $result = [
                            'status' => 'error',
                            'message' => '项目ID和标签不能为空',
                        ];
                    }
                    break;
                    
                case 'get_item_tags':
                    // 获取项目的所有标签
                    if ($itemId > 0) {
                        $tags = $redis->sMembers("item:{$itemId}:tags");
                        $result = [
                            'status' => 'success',
                            'item_id' => $itemId,
                            'tags' => $tags,
                            'count' => count($tags),
                        ];
                    } else {
                        $result = [
                            'status' => 'error',
                            'message' => '项目ID不能为空',
                        ];
                    }
                    break;
                    
                case 'get_tag_items':
                    // 获取带有特定标签的所有项目
                    if (!empty($tag)) {
                        $items = $redis->sMembers("tag:{$tag}:items");
                        $result = [
                            'status' => 'success',
                            'tag' => $tag,
                            'items' => $items,
                            'count' => count($items),
                        ];
                    } else {
                        $result = [
                            'status' => 'error',
                            'message' => '标签不能为空',
                        ];
                    }
                    break;
                    
                case 'find_items_with_all_tags':
                    // 查找具有多个标签的项目（交集）
                    $tags = explode(',', $this->request->param('tags', ''));
                    if (!empty($tags)) {
                        $keys = array_map(function($tag) {
                            return "tag:{$tag}:items";
                        }, $tags);
                        
                        $items = empty($keys) ? [] : $redis->sInter(...$keys);
                        $result = [
                            'status' => 'success',
                            'tags' => $tags,
                            'items' => $items,
                            'count' => count($items),
                            'operation' => '交集(AND)',
                        ];
                    } else {
                        $result = [
                            'status' => 'error',
                            'message' => '标签不能为空',
                        ];
                    }
                    break;
                    
                case 'find_items_with_any_tags':
                    // 查找具有任一标签的项目（并集）
                    $tags = explode(',', $this->request->param('tags', ''));
                    if (!empty($tags)) {
                        $keys = array_map(function($tag) {
                            return "tag:{$tag}:items";
                        }, $tags);
                        
                        $items = empty($keys) ? [] : $redis->sUnion(...$keys);
                        $result = [
                            'status' => 'success',
                            'tags' => $tags,
                            'items' => $items,
                            'count' => count($items),
                            'operation' => '并集(OR)',
                        ];
                    } else {
                        $result = [
                            'status' => 'error',
                            'message' => '标签不能为空',
                        ];
                    }
                    break;
                    
                case 'list':
                default:
                    // 列出一些示例项目和标签
                    $demoItems = [];
                    for ($i = 1; $i <= 5; $i++) {
                        $itemId = $i;
                        $demoItems[$itemId] = [
                            'id' => $itemId,
                            'name' => "示例项目 {$itemId}",
                            'tags' => $redis->sMembers("item:{$itemId}:tags"),
                        ];
                    }
                    
                    $result = [
                        'status' => 'success',
                        'demo_items' => $demoItems,
                        'help' => '使用add操作添加标签，使用get_item_tags获取项目标签',
                    ];
                    break;
            }
            
            return $this->success('标签系统操作成功', $result);
        } catch (\Throwable $e) {
            return $this->error('标签系统操作失败：' . $e->getMessage());
        }
    }
    
    /**
     * IP黑白名单示例
     */
    public function ipAccessControl()
    {
        try {
            $redis = Redis::set();
            $action = $this->request->param('action', 'check');
            $ip = $this->request->param('ip', $this->request->ip());
            
            $blacklistKey = 'ip:blacklist';
            $whitelistKey = 'ip:whitelist';
            
            switch ($action) {
                case 'add_to_blacklist':
                    // 添加IP到黑名单
                    $redis->sAdd($blacklistKey, $ip);
                    $result = [
                        'status' => 'success',
                        'message' => "IP {$ip} 已添加到黑名单",
                    ];
                    break;
                    
                case 'remove_from_blacklist':
                    // 从黑名单移除IP
                    $redis->sRem($blacklistKey, $ip);
                    $result = [
                        'status' => 'success',
                        'message' => "IP {$ip} 已从黑名单移除",
                    ];
                    break;
                    
                case 'add_to_whitelist':
                    // 添加IP到白名单
                    $redis->sAdd($whitelistKey, $ip);
                    $result = [
                        'status' => 'success',
                        'message' => "IP {$ip} 已添加到白名单",
                    ];
                    break;
                    
                case 'remove_from_whitelist':
                    // 从白名单移除IP
                    $redis->sRem($whitelistKey, $ip);
                    $result = [
                        'status' => 'success',
                        'message' => "IP {$ip} 已从白名单移除",
                    ];
                    break;
                    
                case 'get_blacklist':
                    // 获取所有黑名单IP
                    $blacklist = $redis->sMembers($blacklistKey);
                    $result = [
                        'status' => 'success',
                        'blacklist' => $blacklist,
                        'count' => count($blacklist),
                    ];
                    break;
                    
                case 'get_whitelist':
                    // 获取所有白名单IP
                    $whitelist = $redis->sMembers($whitelistKey);
                    $result = [
                        'status' => 'success',
                        'whitelist' => $whitelist,
                        'count' => count($whitelist),
                    ];
                    break;
                    
                case 'check':
                default:
                    // 检查当前IP的访问权限
                    $inBlacklist = $redis->sIsMember($blacklistKey, $ip);
                    $inWhitelist = $redis->sIsMember($whitelistKey, $ip);
                    
                    $accessAllowed = !$inBlacklist || $inWhitelist;
                    
                    $result = [
                        'status' => 'success',
                        'ip' => $ip,
                        'in_blacklist' => $inBlacklist,
                        'in_whitelist' => $inWhitelist,
                        'access_allowed' => $accessAllowed,
                        'rule' => '白名单优先于黑名单，在白名单中的IP即使在黑名单中也允许访问',
                    ];
                    break;
            }
            
            return $this->success('IP访问控制操作成功', $result);
        } catch (\Throwable $e) {
            return $this->error('IP访问控制操作失败：' . $e->getMessage());
        }
    }
    
    /**
     * 用户关注关系示例
     */
    public function userFollows()
    {
        try {
            $redis = Redis::set();
            $action = $this->request->param('action', 'stats');
            $userId = $this->request->param('user_id', 1, 'intval');
            $targetId = $this->request->param('target_id', 0, 'intval');
            
            switch ($action) {
                case 'follow':
                    // 关注用户
                    if ($userId > 0 && $targetId > 0 && $userId != $targetId) {
                        // 将目标用户添加到当前用户的关注集合中
                        $redis->sAdd("user:{$userId}:following", $targetId);
                        
                        // 将当前用户添加到目标用户的粉丝集合中
                        $redis->sAdd("user:{$targetId}:followers", $userId);
                        
                        $result = [
                            'status' => 'success',
                            'message' => "用户 {$userId} 已关注用户 {$targetId}",
                        ];
                    } else {
                        $result = [
                            'status' => 'error',
                            'message' => '用户ID不能为空且不能关注自己',
                        ];
                    }
                    break;
                    
                case 'unfollow':
                    // 取消关注
                    if ($userId > 0 && $targetId > 0) {
                        // 从当前用户的关注集合中移除目标用户
                        $redis->sRem("user:{$userId}:following", $targetId);
                        
                        // 从目标用户的粉丝集合中移除当前用户
                        $redis->sRem("user:{$targetId}:followers", $userId);
                        
                        $result = [
                            'status' => 'success',
                            'message' => "用户 {$userId} 已取消关注用户 {$targetId}",
                        ];
                    } else {
                        $result = [
                            'status' => 'error',
                            'message' => '用户ID不能为空',
                        ];
                    }
                    break;
                    
                case 'is_following':
                    // 检查是否已关注
                    if ($userId > 0 && $targetId > 0) {
                        $isFollowing = $redis->sIsMember("user:{$userId}:following", $targetId);
                        $result = [
                            'status' => 'success',
                            'user_id' => $userId,
                            'target_id' => $targetId,
                            'is_following' => $isFollowing,
                        ];
                    } else {
                        $result = [
                            'status' => 'error',
                            'message' => '用户ID不能为空',
                        ];
                    }
                    break;
                    
                case 'get_following':
                    // 获取用户关注的所有人
                    if ($userId > 0) {
                        $following = $redis->sMembers("user:{$userId}:following");
                        $result = [
                            'status' => 'success',
                            'user_id' => $userId,
                            'following' => $following,
                            'count' => count($following),
                        ];
                    } else {
                        $result = [
                            'status' => 'error',
                            'message' => '用户ID不能为空',
                        ];
                    }
                    break;
                    
                case 'get_followers':
                    // 获取用户的所有粉丝
                    if ($userId > 0) {
                        $followers = $redis->sMembers("user:{$userId}:followers");
                        $result = [
                            'status' => 'success',
                            'user_id' => $userId,
                            'followers' => $followers,
                            'count' => count($followers),
                        ];
                    } else {
                        $result = [
                            'status' => 'error',
                            'message' => '用户ID不能为空',
                        ];
                    }
                    break;
                    
                case 'get_mutuals':
                    // 获取互相关注的用户
                    if ($userId > 0) {
                        $following = "user:{$userId}:following";
                        $followers = "user:{$userId}:followers";
                        
                        $mutuals = $redis->sInter($following, $followers);
                        $result = [
                            'status' => 'success',
                            'user_id' => $userId,
                            'mutual_follows' => $mutuals,
                            'count' => count($mutuals),
                        ];
                    } else {
                        $result = [
                            'status' => 'error',
                            'message' => '用户ID不能为空',
                        ];
                    }
                    break;
                    
                case 'stats':
                default:
                    // 获取用户的关注统计信息
                    if ($userId > 0) {
                        $followingCount = $redis->sCard("user:{$userId}:following");
                        $followersCount = $redis->sCard("user:{$userId}:followers");
                        
                        $following = "user:{$userId}:following";
                        $followers = "user:{$userId}:followers";
                        $mutualsCount = count($redis->sInter($following, $followers));
                        
                        $result = [
                            'status' => 'success',
                            'user_id' => $userId,
                            'following_count' => $followingCount,
                            'followers_count' => $followersCount,
                            'mutual_follows_count' => $mutualsCount,
                        ];
                    } else {
                        $result = [
                            'status' => 'error',
                            'message' => '用户ID不能为空',
                        ];
                    }
                    break;
            }
            
            return $this->success('用户关注关系操作成功', $result);
        } catch (\Throwable $e) {
            return $this->error('用户关注关系操作失败：' . $e->getMessage());
        }
    }
} 