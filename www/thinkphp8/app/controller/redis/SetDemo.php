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
 *
 * @OA\Tag(
 *     name="Redis Set",
 *     description="Redis Set类型操作接口"
 * )
 */
class SetDemo extends RedisDemo
{
    /**
     * 演示页面
     *
     * @OA\Get(
     *     path="/redis/set",
     *     summary="Redis Set演示页面",
     *     description="显示Redis Set类型的演示页面",
     *     operationId="setIndex",
     *     tags={"Redis Set"},
     *     @OA\Response(
     *         response=200,
     *         description="成功返回页面",
     *     )
     * )
     */
    public function index()
    {
        return View::fetch('redis/set/index');
    }

    /**
     * 基本用法示例
     *
     * @OA\Get(
     *     path="/redis/set/basic",
     *     summary="Redis Set基本用法示例",
     *     description="演示Redis Set类型的基本操作，包括添加、删除、判断元素是否存在等",
     *     operationId="setBasic",
     *     tags={"Redis Set"},
     *     @OA\Parameter(
     *         name="key",
     *         in="query",
     *         description="Redis键名，默认为'set_demo_basic'",
     *         required=false,
     *         @OA\Schema(type="string", default="set_demo_basic")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="操作成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="code", type="integer", example=1),
     *             @OA\Property(property="msg", type="string", example="Set基本用法演示"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="members", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="size", type="integer", example=3),
     *                 @OA\Property(property="exists_value1", type="boolean", example=true),
     *                 @OA\Property(property="exists_value4", type="boolean", example=false),
     *                 @OA\Property(property="random_member", type="string", example="value2"),
     *                 @OA\Property(property="members_after_remove", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="popped_member", type="string", example="value1"),
     *                 @OA\Property(property="members_after_pop", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="操作失败",
     *         @OA\JsonContent(
     *             @OA\Property(property="code", type="integer", example=0),
     *             @OA\Property(property="msg", type="string", example="Set基本用法演示失败"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function basic()
    {
        try {
            $redis = Redis::set();
            $key   = 'set_demo_basic';

            // 清空之前的测试数据
            $redis->delete($key);

            // 添加元素
            $redis->sAdd($key, ['value1', 'value2', 'value3']);
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
            $popped          = $redis->sPop($key);
            $membersAfterPop = $redis->sMembers($key);

            return $this->success('Set基本用法演示', [
                'members'              => $members,
                'size'                 => $size,
                'exists_value1'        => $exists1,
                'exists_value4'        => $exists4,
                'random_member'        => $random,
                'members_after_remove' => $membersAfterRemove,
                'popped_member'        => $popped,
                'members_after_pop'    => $membersAfterPop,
            ]);
        } catch (\Throwable $e) {
            return $this->error('Set基本用法演示失败：' . $e->getMessage());
        }
    }

    /**
     * 集合运算示例
     *
     * @OA\Get(
     *     path="/redis/set/set-operations",
     *     summary="Redis Set集合运算示例",
     *     description="演示Redis Set类型的集合运算，包括并集、交集和差集操作",
     *     operationId="setOperations",
     *     tags={"Redis Set"},
     *     @OA\Parameter(
     *         name="key1",
     *         in="query",
     *         description="第一个集合的键名，默认为'set_demo_ops_1'",
     *         required=false,
     *         @OA\Schema(type="string", default="set_demo_ops_1")
     *     ),
     *     @OA\Parameter(
     *         name="key2",
     *         in="query",
     *         description="第二个集合的键名，默认为'set_demo_ops_2'",
     *         required=false,
     *         @OA\Schema(type="string", default="set_demo_ops_2")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="操作成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="code", type="integer", example=1),
     *             @OA\Property(property="msg", type="string", example="Set集合运算演示"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="set1", type="array", @OA\Items(type="string"), example={"A", "B", "C", "D"}),
     *                 @OA\Property(property="set2", type="array", @OA\Items(type="string"), example={"C", "D", "E", "F"}),
     *                 @OA\Property(property="union", type="array", @OA\Items(type="string"), example={"A", "B", "C", "D", "E", "F"}),
     *                 @OA\Property(property="intersection", type="array", @OA\Items(type="string"), example={"C", "D"}),
     *                 @OA\Property(property="difference_1_2", type="array", @OA\Items(type="string"), example={"A", "B"}),
     *                 @OA\Property(property="difference_2_1", type="array", @OA\Items(type="string"), example={"E", "F"})
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="操作失败",
     *         @OA\JsonContent(
     *             @OA\Property(property="code", type="integer", example=0),
     *             @OA\Property(property="msg", type="string", example="Set集合运算演示失败"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
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
            $redis->sAdd($key1, ['A', 'B', 'C', 'D']);

            // 添加元素到集合2
            $redis->sAdd($key2, ['C', 'D', 'E', 'F']);

            // 并集
            $union = $redis->sUnion([$key1, $key2]);

            // 交集
            $inter = $redis->sInter([$key1, $key2]);

            // 差集 (key1 - key2)
            $diff1 = $redis->sDiff([$key1, $key2]);

            // 差集 (key2 - key1)
            $diff2 = $redis->sDiff([$key2, $key1]);

            return $this->success('Set集合运算演示', [
                'set1'           => $redis->sMembers($key1),
                'set2'           => $redis->sMembers($key2),
                'union'          => $union,
                'intersection'   => $inter,
                'difference_1_2' => $diff1,
                'difference_2_1' => $diff2,
            ]);
        } catch (\Throwable $e) {
            return $this->error('Set集合运算演示失败：' . $e->getMessage());
        }
    }

    /**
     * 标签系统示例
     *
     * @OA\Get(
     *     path="/redis/set/tag-system",
     *     summary="Redis Set实现标签系统",
     *     description="使用Redis Set实现标签系统，包括添加标签、移除标签、获取项目标签、查找带有特定标签的项目等",
     *     operationId="tagSystem",
     *     tags={"Redis Set"},
     *     @OA\Parameter(
     *         name="action",
     *         in="query",
     *         description="操作类型：add(添加标签)、remove(移除标签)、get_item_tags(获取项目标签)、get_tag_items(获取带有标签的项目)、find_items_with_all_tags(查找具有所有标签的项目)、find_items_with_any_tags(查找具有任一标签的项目)、list(列出示例项目)",
     *         required=false,
     *         @OA\Schema(type="string", default="list")
     *     ),
     *     @OA\Parameter(
     *         name="item_id",
     *         in="query",
     *         description="项目ID",
     *         required=false,
     *         @OA\Schema(type="integer", default=0)
     *     ),
     *     @OA\Parameter(
     *         name="tag",
     *         in="query",
     *         description="标签名称",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="tags",
     *         in="query",
     *         description="多个标签，用逗号分隔",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="操作成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="code", type="integer", example=1),
     *             @OA\Property(property="msg", type="string", example="标签系统操作成功"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="status", type="string", example="success"),
     *                 @OA\Property(property="message", type="string", example="标签 tech 已添加到项目 1"),
     *                 @OA\Property(property="demo_items", type="object"),
     *                 @OA\Property(property="tags", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="items", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="count", type="integer", example=2)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="操作失败",
     *         @OA\JsonContent(
     *             @OA\Property(property="code", type="integer", example=0),
     *             @OA\Property(property="msg", type="string", example="标签系统操作失败"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function tagSystem()
    {
        try {
            $redis  = Redis::set();
            $action = $this->request->param('action', 'list');
            $itemId = $this->request->param('item_id', 0, 'intval');
            $tag    = $this->request->param('tag', '');

            switch ($action) {
                case 'add':
                    // 为项目添加标签
                    if ($itemId > 0 && !empty($tag)) {
                        // 添加项目->标签的映射
                        $redis->sAdd("item:{$itemId}:tags", $tag);

                        // 添加标签->项目的映射
                        $redis->sAdd("tag:{$tag}:items", $itemId);

                        $result = [
                            'status'  => 'success',
                            'message' => "标签 {$tag} 已添加到项目 {$itemId}",
                        ];
                    } else {
                        $result = [
                            'status'  => 'error',
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
                            'status'  => 'success',
                            'message' => "标签 {$tag} 已从项目 {$itemId} 移除",
                        ];
                    } else {
                        $result = [
                            'status'  => 'error',
                            'message' => '项目ID和标签不能为空',
                        ];
                    }
                    break;

                case 'get_item_tags':
                    // 获取项目的所有标签
                    if ($itemId > 0) {
                        $tags   = $redis->sMembers("item:{$itemId}:tags");
                        $result = [
                            'status'  => 'success',
                            'item_id' => $itemId,
                            'tags'    => $tags,
                            'count'   => count($tags),
                        ];
                    } else {
                        $result = [
                            'status'  => 'error',
                            'message' => '项目ID不能为空',
                        ];
                    }
                    break;

                case 'get_tag_items':
                    // 获取带有特定标签的所有项目
                    if (!empty($tag)) {
                        $items  = $redis->sMembers("tag:{$tag}:items");
                        $result = [
                            'status' => 'success',
                            'tag'    => $tag,
                            'items'  => $items,
                            'count'  => count($items),
                        ];
                    } else {
                        $result = [
                            'status'  => 'error',
                            'message' => '标签不能为空',
                        ];
                    }
                    break;

                case 'find_items_with_all_tags':
                    // 查找具有多个标签的项目（交集）
                    $tags = explode(',', $this->request->param('tags', ''));
                    if (!empty($tags)) {
                        $keys = array_map(function ($tag) {
                            return "tag:{$tag}:items";
                        }, $tags);

                        $items  = empty($keys) ? [] : $redis->sInter(...$keys);
                        $result = [
                            'status'    => 'success',
                            'tags'      => $tags,
                            'items'     => $items,
                            'count'     => count($items),
                            'operation' => '交集(AND)',
                        ];
                    } else {
                        $result = [
                            'status'  => 'error',
                            'message' => '标签不能为空',
                        ];
                    }
                    break;

                case 'find_items_with_any_tags':
                    // 查找具有任一标签的项目（并集）
                    $tags = explode(',', $this->request->param('tags', ''));
                    if (!empty($tags)) {
                        $keys = array_map(function ($tag) {
                            return "tag:{$tag}:items";
                        }, $tags);

                        $items  = empty($keys) ? [] : $redis->sUnion(...$keys);
                        $result = [
                            'status'    => 'success',
                            'tags'      => $tags,
                            'items'     => $items,
                            'count'     => count($items),
                            'operation' => '并集(OR)',
                        ];
                    } else {
                        $result = [
                            'status'  => 'error',
                            'message' => '标签不能为空',
                        ];
                    }
                    break;

                case 'list':
                default:
                    // 列出一些示例项目和标签
                    $demoItems = [];
                    for ($i = 1; $i <= 5; $i++) {
                        $itemId             = $i;
                        $demoItems[$itemId] = [
                            'id'   => $itemId,
                            'name' => "示例项目 {$itemId}",
                            'tags' => $redis->sMembers("item:{$itemId}:tags"),
                        ];
                    }

                    $result = [
                        'status'     => 'success',
                        'demo_items' => $demoItems,
                        'help'       => '使用add操作添加标签，使用get_item_tags获取项目标签',
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
     *
     * @OA\Get(
     *     path="/redis/set/ip-access-control",
     *     summary="Redis Set实现IP黑白名单",
     *     description="使用Redis Set实现IP访问控制，包括黑名单和白名单管理",
     *     operationId="ipAccessControl",
     *     tags={"Redis Set"},
     *     @OA\Parameter(
     *         name="action",
     *         in="query",
     *         description="操作类型：add_to_blacklist(添加到黑名单)、remove_from_blacklist(从黑名单移除)、add_to_whitelist(添加到白名单)、remove_from_whitelist(从白名单移除)、get_blacklist(获取黑名单)、get_whitelist(获取白名单)、check(检查IP访问权限)",
     *         required=false,
     *         @OA\Schema(type="string", default="check")
     *     ),
     *     @OA\Parameter(
     *         name="ip",
     *         in="query",
     *         description="IP地址，默认为当前访问IP",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="操作成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="code", type="integer", example=1),
     *             @OA\Property(property="msg", type="string", example="IP访问控制操作成功"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="status", type="string", example="success"),
     *                 @OA\Property(property="message", type="string", example="IP 192.168.1.1 已添加到黑名单"),
     *                 @OA\Property(property="ip", type="string", example="192.168.1.1"),
     *                 @OA\Property(property="in_blacklist", type="boolean", example=true),
     *                 @OA\Property(property="in_whitelist", type="boolean", example=false),
     *                 @OA\Property(property="access_allowed", type="boolean", example=false),
     *                 @OA\Property(property="blacklist", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="whitelist", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="count", type="integer", example=5)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="操作失败",
     *         @OA\JsonContent(
     *             @OA\Property(property="code", type="integer", example=0),
     *             @OA\Property(property="msg", type="string", example="IP访问控制操作失败"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function ipAccessControl()
    {
        try {
            $redis  = Redis::set();
            $action = $this->request->param('action', 'check');
            $ip     = $this->request->param('ip', $this->request->ip());

            $blacklistKey = 'ip:blacklist';
            $whitelistKey = 'ip:whitelist';

            switch ($action) {
                case 'add_to_blacklist':
                    // 添加IP到黑名单
                    $redis->sAdd($blacklistKey, $ip);
                    $result = [
                        'status'  => 'success',
                        'message' => "IP {$ip} 已添加到黑名单",
                    ];
                    break;

                case 'remove_from_blacklist':
                    // 从黑名单移除IP
                    $redis->sRem($blacklistKey, $ip);
                    $result = [
                        'status'  => 'success',
                        'message' => "IP {$ip} 已从黑名单移除",
                    ];
                    break;

                case 'add_to_whitelist':
                    // 添加IP到白名单
                    $redis->sAdd($whitelistKey, $ip);
                    $result = [
                        'status'  => 'success',
                        'message' => "IP {$ip} 已添加到白名单",
                    ];
                    break;

                case 'remove_from_whitelist':
                    // 从白名单移除IP
                    $redis->sRem($whitelistKey, $ip);
                    $result = [
                        'status'  => 'success',
                        'message' => "IP {$ip} 已从白名单移除",
                    ];
                    break;

                case 'get_blacklist':
                    // 获取所有黑名单IP
                    $blacklist = $redis->sMembers($blacklistKey);
                    $result = [
                        'status'    => 'success',
                        'blacklist' => $blacklist,
                        'count'     => count($blacklist),
                    ];
                    break;

                case 'get_whitelist':
                    // 获取所有白名单IP
                    $whitelist = $redis->sMembers($whitelistKey);
                    $result = [
                        'status'    => 'success',
                        'whitelist' => $whitelist,
                        'count'     => count($whitelist),
                    ];
                    break;

                case 'check':
                default:
                    // 检查当前IP的访问权限
                    $inBlacklist = $redis->sIsMember($blacklistKey, $ip);
                    $inWhitelist = $redis->sIsMember($whitelistKey, $ip);

                    $accessAllowed = !$inBlacklist || $inWhitelist;

                    $result = [
                        'status'         => 'success',
                        'ip'             => $ip,
                        'in_blacklist'   => $inBlacklist,
                        'in_whitelist'   => $inWhitelist,
                        'access_allowed' => $accessAllowed,
                        'rule'           => '白名单优先于黑名单，在白名单中的IP即使在黑名单中也允许访问',
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
     *
     * @OA\Get(
     *     path="/redis/set/userfollows",
     *     summary="用户关注关系操作",
     *     description="管理用户之间的关注关系，包括关注、取消关注、查询关注状态等",
     *     operationId="userFollows",
     *     tags={"Redis Set"},
     *     @OA\Parameter(
     *         name="action",
     *         in="query",
     *         description="操作类型：follow(关注)、unfollow(取消关注)、is_following(检查关注状态)、get_following(获取关注列表)、get_followers(获取粉丝列表)、get_mutuals(获取互相关注)、stats(统计信息)",
     *         required=false,
     *         @OA\Schema(type="string", default="stats")
     *     ),
     *     @OA\Parameter(
     *         name="user_id",
     *         in="query",
     *         description="当前用户ID",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="target_id",
     *         in="query",
     *         description="目标用户ID（被关注/取消关注的用户）",
     *         required=false,
     *         @OA\Schema(type="integer", default=0)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="操作成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="code", type="integer", example=1),
     *             @OA\Property(property="msg", type="string", example="用户关注关系操作成功"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="status", type="string", example="success"),
     *                 @OA\Property(property="message", type="string", example="用户 1 已取消关注用户 2")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="操作失败",
     *         @OA\JsonContent(
     *             @OA\Property(property="code", type="integer", example=0),
     *             @OA\Property(property="msg", type="string", example="用户关注关系操作失败"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function userFollows()
    {
        try {
            $redis    = Redis::set();
            $action   = $this->request->param('action', 'stats');
            $userId   = $this->request->param('user_id', 1, 'intval');// 1表示当前用户
            $targetId = $this->request->param('target_id', 0, 'intval');// 0表示当前用户

            switch ($action) {
                case 'follow':
                    // 关注用户
                    if ($userId > 0 && $targetId > 0 && $userId != $targetId) {
                        // 将目标用户添加到当前用户的关注集合中
                        $redis->sAdd("user:{$userId}:following", $targetId);

                        // 将当前用户添加到目标用户的粉丝集合中
                        $redis->sAdd("user:{$targetId}:followers", $userId);

                        $result = [
                            'status'  => 'success',
                            'message' => "用户 {$userId} 已关注用户 {$targetId}",
                        ];
                    } else {
                        $result = [
                            'status'  => 'error',
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
                            'status'  => 'success',
                            'message' => "用户 {$userId} 已取消关注用户 {$targetId}",
                        ];
                    } else {
                        $result = [
                            'status'  => 'error',
                            'message' => '用户ID不能为空',
                        ];
                    }
                    break;

                case 'is_following':
                    // 检查是否已关注
                    if ($userId > 0 && $targetId > 0) {
                        $isFollowing = $redis->sIsMember("user:{$userId}:following", $targetId);
                        $result      = [
                            'status'       => 'success',
                            'user_id'      => $userId,
                            'target_id'    => $targetId,
                            'is_following' => $isFollowing,
                        ];
                    } else {
                        $result = [
                            'status'  => 'error',
                            'message' => '用户ID不能为空',
                        ];
                    }
                    break;

                case 'get_following':
                    // 获取用户关注的所有人
                    if ($userId > 0) {
                        $following = $redis->sMembers("user:{$userId}:following");
                        $result    = [
                            'status'    => 'success',
                            'user_id'   => $userId,
                            'following' => $following,
                            'count'     => count($following),
                        ];
                    } else {
                        $result = [
                            'status'  => 'error',
                            'message' => '用户ID不能为空',
                        ];
                    }
                    break;

                case 'get_followers':
                    // 获取用户的所有粉丝
                    if ($userId > 0) {
                        $followers = $redis->sMembers("user:{$userId}:followers");
                        $result    = [
                            'status'    => 'success',
                            'user_id'   => $userId,
                            'followers' => $followers,
                            'count'     => count($followers),
                        ];
                    } else {
                        $result = [
                            'status'  => 'error',
                            'message' => '用户ID不能为空',
                        ];
                    }
                    break;

                case 'get_mutuals':
                    // 获取互相关注的用户
                    if ($userId > 0) {
                        $following = "user:{$userId}:following";
                        $followers = "user:{$userId}:followers";

                        $mutuals = $redis->sInter([$following, $followers]);
                        $result  = [
                            'status'         => 'success',
                            'user_id'        => $userId,
                            'mutual_follows' => $mutuals,
                            'count'          => count($mutuals),
                        ];
                    } else {
                        $result = [
                            'status'  => 'error',
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

                        $following    = "user:{$userId}:following";
                        $followers    = "user:{$userId}:followers";
                        $mutualsCount = count($redis->sInter([$following, $followers]));

                        $result = [
                            'status'               => 'success',
                            'user_id'              => $userId,
                            'following_count'      => $followingCount,
                            'followers_count'      => $followersCount,
                            'mutual_follows_count' => $mutualsCount,
                        ];
                    } else {
                        $result = [
                            'status'  => 'error',
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

    /**
     * 随机抽奖示例
     *
     * 使用Redis Set实现随机抽奖功能
     *
     * @OA\Get(
     *     path="/redis/set/random-prize",
     *     summary="随机抽奖功能",
     *     description="使用Redis Set实现随机抽奖功能，包括初始化奖品池、参与抽奖、抽奖和查看统计信息",
     *     operationId="randomPrize",
     *     tags={"Redis Set"},
     *     @OA\Parameter(
     *         name="action",
     *         in="query",
     *         description="操作类型：init(初始化奖品池)、join(参与抽奖)、draw(抽奖)、stats(统计信息)",
     *         required=false,
     *         @OA\Schema(type="string", default="draw")
     *     ),
     *     @OA\Parameter(
     *         name="user_id",
     *         in="query",
     *         description="用户ID",
     *         required=false,
     *         @OA\Schema(type="integer", default=0)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="操作成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="code", type="integer", example=1),
     *             @OA\Property(property="msg", type="string", example="随机抽奖操作成功"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="status", type="string", example="success"),
     *                 @OA\Property(property="message", type="string", example="抽奖成功"),
     *                 @OA\Property(property="user_id", type="integer", example=1001),
     *                 @OA\Property(property="prize", type="string", example="iphone"),
     *                 @OA\Property(property="prize_name", type="string", example="苹果手机"),
     *                 @OA\Property(property="is_winner", type="boolean", example=true),
     *                 @OA\Property(property="remaining_prizes", type="integer", example=691)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="操作失败",
     *         @OA\JsonContent(
     *             @OA\Property(property="code", type="integer", example=0),
     *             @OA\Property(property="msg", type="string", example="随机抽奖操作失败"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function randomPrize()
    {
        try {
            $redis  = Redis::set();
            $action = $this->request->param('action', 'draw');
            $userId = $this->request->param('user_id', 0, 'intval');

            // 奖品池键名
            $prizePoolKey = 'prize:pool';
            // 已参与用户键名
            $participantsKey = 'prize:participants';
            // 中奖用户键名
            $winnersKey = 'prize:winners';

            switch ($action) {
                case 'init':
                    // 初始化奖品池
                    $redis->delete($prizePoolKey);
                    $redis->delete($participantsKey);
                    $redis->delete($winnersKey);

                    // 添加奖品到奖品池
                    $prizes = [
                        'iphone'        => '苹果手机',
                        'macbook'       => 'MacBook Pro',
                        'airpods'       => 'AirPods',
                        'ipad'          => 'iPad Pro',
                        'watch'         => 'Apple Watch',
                        'gift_card_100' => '100元礼品卡',
                        'gift_card_50'  => '50元礼品卡',
                        'gift_card_20'  => '20元礼品卡',
                        'gift_card_10'  => '10元礼品卡',
                        'thanks'        => '谢谢参与'
                    ];

                    // 设置不同奖品的数量
                    $prizeCount = [
                        'iphone'        => 1,
                        'macbook'       => 1,
                        'airpods'       => 3,
                        'ipad'          => 2,
                        'watch'         => 5,
                        'gift_card_100' => 10,
                        'gift_card_50'  => 20,
                        'gift_card_20'  => 50,
                        'gift_card_10'  => 100,
                        'thanks'        => 500
                    ];

                    // 将奖品添加到奖品池中
                    foreach ($prizeCount as $prize => $count) {
                        for ($i = 0; $i < $count; $i++) {
                            $redis->sAdd($prizePoolKey, $prize);
                        }
                    }

                    // 获取奖品池大小
                    $poolSize = $redis->sCard($prizePoolKey);

                    $result = [
                        'status'       => 'success',
                        'message'      => '奖品池初始化成功',
                        'pool_size'    => $poolSize,
                        'prizes'       => $prizes,
                        'prize_count'  => $prizeCount,
                        'total_prizes' => array_sum($prizeCount)
                    ];
                    break;

                case 'join':
                    // 用户参与抽奖
                    if ($userId <= 0) {
                        return $this->error('用户ID不能为空');
                    }

                    // 检查用户是否已参与
                    if ($redis->sIsMember($participantsKey, $userId)) {
                        return $this->error('您已经参与过抽奖，不能重复参与');
                    }

                    // 检查奖品池是否为空
                    $poolSize = $redis->sCard($prizePoolKey);
                    if ($poolSize <= 0) {
                        return $this->error('抽奖活动已结束，奖品已抽完');
                    }

                    // 添加用户到参与者列表
                    $redis->sAdd($participantsKey, $userId);

                    $result = [
                        'status'           => 'success',
                        'message'          => '成功参与抽奖',
                        'user_id'          => $userId,
                        'remaining_prizes' => $poolSize
                    ];
                    break;

                case 'draw':
                    // 抽奖
                    if ($userId <= 0) {
                        return $this->error('用户ID不能为空');
                    }

                    // 检查用户是否已参与
                    if (!$redis->sIsMember($participantsKey, $userId)) {
                        return $this->error('您还未参与抽奖，请先参与');
                    }

                    // 检查用户是否已中奖
                    if ($redis->sIsMember($winnersKey, $userId)) {
                        return $this->error('您已经中奖，不能重复抽奖');
                    }

                    // 检查奖品池是否为空
                    $poolSize = $redis->sCard($prizePoolKey);
                    if ($poolSize <= 0) {
                        return $this->error('抽奖活动已结束，奖品已抽完');
                    }

                    // 随机抽取一个奖品
                    $prize = $redis->sPop($prizePoolKey);

                    // 如果抽到奖品，将用户添加到中奖名单
                    if ($prize && $prize !== 'thanks') {
                        $redis->sAdd($winnersKey, $userId);
                    }

                    // 奖品名称映射
                    $prizeNames = [
                        'iphone'        => '苹果手机',
                        'macbook'       => 'MacBook Pro',
                        'airpods'       => 'AirPods',
                        'ipad'          => 'iPad Pro',
                        'watch'         => 'Apple Watch',
                        'gift_card_100' => '100元礼品卡',
                        'gift_card_50'  => '50元礼品卡',
                        'gift_card_20'  => '20元礼品卡',
                        'gift_card_10'  => '10元礼品卡',
                        'thanks'        => '谢谢参与'
                    ];

                    $result = [
                        'status'           => 'success',
                        'message'          => '抽奖成功',
                        'user_id'          => $userId,
                        'prize'            => $prize,
                        'prize_name'       => $prizeNames[$prize] ?? $prize,
                        'is_winner'        => ($prize && $prize !== 'thanks'),
                        'remaining_prizes' => $poolSize - 1
                    ];
                    break;

                case 'stats':
                    // 抽奖统计
                    $poolSize = $redis->sCard($prizePoolKey);
                    $participantsCount = $redis->sCard($participantsKey);
                    $winnersCount = $redis->sCard($winnersKey);

                    // 获取所有中奖用户
                    $winners = $redis->sMembers($winnersKey);

                    // 获取剩余奖品分布
                    $remainingPrizes = [];
                    $allPrizes = $redis->sMembers($prizePoolKey);
                    foreach ($allPrizes as $prize) {
                        if (!isset($remainingPrizes[$prize])) {
                            $remainingPrizes[$prize] = 0;
                        }
                        $remainingPrizes[$prize]++;
                    }

                    $result = [
                        'status'             => 'success',
                        'pool_size'          => $poolSize,
                        'participants_count' => $participantsCount,
                        'winners_count'      => $winnersCount,
                        'winners'            => $winners,
                        'remaining_prizes'   => $remainingPrizes
                    ];
                    break;

                default:
                    return $this->error('未知操作');
            }

            return $this->success('随机抽奖操作成功', $result);
        } catch (\Throwable $e) {
            return $this->error('随机抽奖操作失败：' . $e->getMessage());
        }
    }
}