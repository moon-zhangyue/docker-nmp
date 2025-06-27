<?php
declare(strict_types=1);

namespace app\controller\pg;

use app\BaseController;
use app\exception\BusinessException;
use app\service\pg\CartService;
use app\validate\pg\CartValidate;
use think\facade\Log;
use think\Response;

/**
 * 购物车控制器
 */
class CartController extends BaseController
{
    /**
     * 购物车服务
     *
     * @var CartService
     */
    protected $cartService;

    /**
     * 构造函数
     */
    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    /**
     * 获取购物车列表
     *
     * @return Response
     */
    public function list()
    {
        try {
            // 获取当前登录用户ID
            $userId = $this->getUserId();

            // 获取购物车列表
            $result = $this->cartService->getCartList($userId);

            return $this->success('获取成功', $result);
        } catch (BusinessException $e) {
            return $this->error($e->getMessage());
        } catch (\Exception $e) {
            Log::error('获取购物车列表异常：{error}', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->error('获取购物车列表失败');
        }
    }

    /**
     * 添加商品到购物车
     *
     * @return Response
     */
    public function add()
    {
        $data = $this->request->post();

        // 验证数据
        try {
            validate(CartValidate::class)
                ->scene('add')
                ->check($data);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }

        try {
            // 获取当前登录用户ID
            $userId = $this->getUserId();

            // 添加商品到购物车
            $result = $this->cartService->addToCart($userId, (int) $data['sku_id'], (int) ($data['quantity'] ?? 1));

            return $this->success('添加成功', $result);
        } catch (BusinessException $e) {
            return $this->error($e->getMessage());
        } catch (\Exception $e) {
            Log::error('添加商品到购物车异常：{error}', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString(), 'data' => $data]);
            return $this->error('添加商品到购物车失败');
        }
    }

    /**
     * 更新购物车商品数量
     *
     * @param int $id 购物车ID
     * @return Response
     */
    public function updateQuantity(int $id)
    {
        $data = $this->request->put();

        // 验证数据
        try {
            validate(CartValidate::class)
                ->scene('update_quantity')
                ->check($data);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }

        try {
            // 获取当前登录用户ID
            $userId = $this->getUserId();

            // 更新购物车商品数量
            $result = $this->cartService->updateQuantity($userId, $id, (int) $data['quantity']);

            return $this->success('更新成功', $result);
        } catch (BusinessException $e) {
            return $this->error($e->getMessage());
        } catch (\Exception $e) {
            Log::error('更新购物车商品数量异常：{error}', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString(), 'id' => $id, 'data' => $data]);
            return $this->error('更新购物车商品数量失败');
        }
    }

    /**
     * 更新购物车商品选中状态
     *
     * @param int $id 购物车ID
     * @return Response
     */
    public function updateSelected(int $id)
    {
        $data = $this->request->put();

        // 验证数据
        try {
            validate(CartValidate::class)
                ->scene('update_selected')
                ->check($data);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }

        try {
            // 获取当前登录用户ID
            $userId = $this->getUserId();

            // 更新购物车商品选中状态
            $result = $this->cartService->updateSelected($userId, $id, (bool) $data['selected']);

            return $this->success('更新成功', $result);
        } catch (BusinessException $e) {
            return $this->error($e->getMessage());
        } catch (\Exception $e) {
            Log::error('更新购物车商品选中状态异常：{error}', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString(), 'id' => $id, 'data' => $data]);
            return $this->error('更新购物车商品选中状态失败');
        }
    }

    /**
     * 全选/全不选
     *
     * @return Response
     */
    public function selectAll()
    {
        $data = $this->request->put();

        // 验证数据
        try {
            validate(CartValidate::class)
                ->scene('select_all')
                ->check($data);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }

        try {
            // 获取当前登录用户ID
            $userId = $this->getUserId();

            // 全选/全不选
            $result = $this->cartService->selectAll($userId, (bool) $data['selected']);

            return $this->success('操作成功', $result);
        } catch (BusinessException $e) {
            return $this->error($e->getMessage());
        } catch (\Exception $e) {
            Log::error('购物车全选/全不选异常：{error}', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString(), 'data' => $data]);
            return $this->error('操作失败');
        }
    }

    /**
     * 删除购物车商品
     *
     * @param int $id 购物车ID
     * @return Response
     */
    public function delete(int $id)
    {
        try {
            // 获取当前登录用户ID
            $userId = $this->getUserId();

            // 删除购物车商品
            $result = $this->cartService->removeFromCart($userId, $id);

            return $this->success('删除成功', $result);
        } catch (BusinessException $e) {
            return $this->error($e->getMessage());
        } catch (\Exception $e) {
            Log::error('删除购物车商品异常：{error}', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString(), 'id' => $id]);
            return $this->error('删除购物车商品失败');
        }
    }

    /**
     * 清空购物车
     *
     * @return Response
     */
    public function clear()
    {
        try {
            // 获取当前登录用户ID
            $userId = $this->getUserId();

            // 清空购物车
            $result = $this->cartService->clearCart($userId);

            return $this->success('清空成功', $result);
        } catch (BusinessException $e) {
            return $this->error($e->getMessage());
        } catch (\Exception $e) {
            Log::error('清空购物车异常：{error}', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->error('清空购物车失败');
        }
    }

    /**
     * 获取购物车商品数量
     *
     * @return Response
     */
    public function count()
    {
        try {
            // 获取当前登录用户ID
            $userId = $this->getUserId();

            // 获取购物车商品数量
            $count = $this->cartService->getCartCount($userId);

            return $this->success('获取成功', ['count' => $count]);
        } catch (BusinessException $e) {
            return $this->error($e->getMessage());
        } catch (\Exception $e) {
            Log::error('获取购物车商品数量异常：{error}', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->error('获取购物车商品数量失败');
        }
    }

    /**
     * 获取当前登录用户ID
     *
     * @return int
     * @throws BusinessException
     */
    protected function getUserId()
    {
        // 这里简单模拟，实际应从JWT Token中获取用户ID
        $userId = $this->request->header('X-User-Id');

        if (!$userId) {
            throw new BusinessException('未登录或登录已过期', 401);
        }

        return (int) $userId;
    }
}